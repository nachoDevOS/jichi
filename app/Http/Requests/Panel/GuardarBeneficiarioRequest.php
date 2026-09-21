<?php

namespace App\Http\Requests\Panel;

use App\Models\Beneficiario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas de validación para crear y editar un beneficiario.
 */
class GuardarBeneficiarioRequest extends FormRequest
{
    /**
     * El permiso ya lo revisa el middleware de la ruta (ver routes/panel.php),
     * así que acá no hace falta volver a preguntar.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // En el formulario de edición Laravel inyecta el modelo por la ruta. Se
        // necesita para excluir al propio registro de la regla de unicidad: sin
        // eso, guardar sin cambiar la cédula diría «ya existe».
        $beneficiario = $this->route('beneficiario');
        $idActual = $beneficiario instanceof Beneficiario ? $beneficiario->id : null;

        return [
            /*
             * Unicidad de la cédula.
             */
            'ci' => [
                'required', 'string', 'max:30',
                Rule::unique('beneficiarios', 'ci')
                    ->where(fn ($q) => $q->whereNull('deleted_at'))
                    ->ignore($idActual),
            ],
            'complemento' => ['nullable', 'string', 'max:5'],

            // Se valida contra la lista del SEGIP: son nueve códigos fijos y
            // aceptar cualquier cosa dejaría carnets que dicen «XX».
            // Contra la TABLA y no contra el config: `departamentos` es la
            // fuente, y la base tiene la FK que lo exige igual.
            'departamento_id' => ['nullable', 'integer', Rule::exists('departamentos', 'id')],

            /*
             * El nombre, partido como viene en la cédula.
             */
            'primerNombre' => ['required', 'string', 'max:60'],
            'segundoNombre' => ['nullable', 'string', 'max:60'],
            'apellidoPaterno' => ['required', 'string', 'max:60'],
            'apellidoMaterno' => ['nullable', 'string', 'max:60'],
            'apellidoCasado' => ['nullable', 'string', 'max:60'],

            // Obligatoria: la columna es NOT NULL y además se imprime en el
            // carnet. `before:today` descarta el error de tipeo más común, que
            // es escribir el año en curso en vez del de nacimiento.
            'fechaNacimiento' => ['required', 'date', 'before:today'],

            'genero' => ['nullable', Rule::in(['masculino', 'femenino'])],
            'nacionalidad' => ['nullable', 'string', 'max:255'],

            'direccion' => ['nullable', 'string', 'max:1000'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            // Lista abierta a propósito: el beneficiario puede vivir fuera del
            // Beni y las ocho provincias son solo las de acá.
            'provincia' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],

            // La foto se imprime en el carnet. El peso y los formatos salen de
            // config/jichi.php, que es donde vive el límite único del sistema.
            'foto' => [
                'nullable',
                'image',
                'mimes:'.implode(',', config('jichi.archivos.extensiones_imagen')),
                'max:'.config('jichi.archivos.max_kb'),
            ],
            'quitar_foto' => ['boolean'],
        ];
    }

    /**
     * Mensajes en español y con lenguaje de ventanilla, no de programador.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ci.required' => 'El número de cédula es obligatorio.',
            'ci.unique' => 'Ya existe un beneficiario registrado con esa cédula.',
            'departamento_id.exists' => 'El lugar de expedición no es un departamento válido.',
            'primerNombre.required' => 'El primer nombre es obligatorio.',
            'apellidoPaterno.required' => 'El apellido paterno es obligatorio.',
            'fechaNacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fechaNacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'foto.image' => 'La foto debe ser una imagen.',
            'foto.max' => 'La foto no puede pesar más de '.round(config('jichi.archivos.max_kb') / 1024, 1).' MB.',
        ];
    }

    /**
     * Normaliza los datos ANTES de validar.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'ci' => trim((string) $this->input('ci')),
            'complemento' => filled($this->input('complemento'))
                ? strtoupper(trim((string) $this->input('complemento')))
                : null,
            // Llega como texto del `<select>`; vacío es «no se declaró».
            'departamento_id' => filled($this->input('departamento_id'))
                ? (int) $this->input('departamento_id')
                : null,
            'email' => filled($this->input('email'))
                ? strtolower(trim((string) $this->input('email')))
                : null,
            'quitar_foto' => $this->boolean('quitar_foto'),
        ]);

        foreach (['primerNombre', 'segundoNombre', 'apellidoPaterno', 'apellidoMaterno', 'apellidoCasado'] as $parte) {
            $this->merge([
                $parte => filled($this->input($parte)) ? trim((string) $this->input($parte)) : null,
            ]);
        }
    }

    /**
     * Nombres legibles de los campos, para los mensajes automáticos.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ci' => 'cédula de identidad',
            'departamento_id' => 'lugar de expedición',
            'primerNombre' => 'primer nombre',
            'segundoNombre' => 'segundo nombre',
            'apellidoPaterno' => 'apellido paterno',
            'apellidoMaterno' => 'apellido materno',
            'apellidoCasado' => 'apellido de casada',
            'fechaNacimiento' => 'fecha de nacimiento',
            'direccion' => 'dirección',
            'telefono' => 'teléfono',
        ];
    }
}

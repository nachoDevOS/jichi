<?php

namespace App\Http\Requests\Panel;

use App\Models\Solicitante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas de validación para crear y editar un solicitante.
 *
 * ¿POR QUÉ UN FORM REQUEST Y NO VALIDAR EN EL CONTROLADOR?
 *
 * Porque crear y editar comparten exactamente las mismas reglas. Si se
 * validara dentro del controlador habría que escribirlas dos veces, y el día
 * que cambie una (por ejemplo, que el teléfono pase a ser obligatorio) hay que
 * acordarse de tocar los dos lados. Acá se escriben una sola vez.
 *
 * Laravel lo ejecuta ANTES de entrar al método del controlador. Si algo falla,
 * el controlador nunca se ejecuta: Laravel redirige de vuelta al formulario
 * con los errores, e Inertia los deja disponibles en React dentro de `errors`.
 */
class GuardarSolicitanteRequest extends FormRequest
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
        // En el formulario de edición Laravel inyecta el modelo por la ruta.
        // Se necesita para excluir al propio registro de la regla de unicidad:
        // si no, al guardar sin cambiar la cédula diría "ya existe".
        $solicitante = $this->route('solicitante');
        $idActual = $solicitante instanceof Solicitante ? $solicitante->id : null;

        return [
            /*
             * Unicidad de la cédula.
             *
             * La regla replica el índice único parcial de la base de datos
             * (ver la migración 2026_09_08_100000): la combinación
             * ci_nit + complemento no puede repetirse entre registros VIVOS.
             *
             * whereNull('deleted_at') es lo que deja fuera a los borrados,
             * y ->ignore() excluye al registro que se está editando.
             */
            'ci_nit' => [
                'required', 'string', 'max:30',
                Rule::unique('solicitantes', 'ci_nit')
                    ->where(fn ($q) => $q
                        ->whereNull('deleted_at')
                        ->where('complemento', $this->input('complemento')))
                    ->ignore($idActual),
            ],
            'complemento' => ['nullable', 'string', 'max:5'],

            // Se valida contra la lista del SEGIP: son nueve códigos fijos y
            // aceptar cualquier cosa dejaría credenciales que dicen «XX».
            'expedido' => ['nullable', Rule::in(array_keys(config('jichi.expedido')))],

            /*
             * El nombre, partido como viene en la cédula.
             *
             * Solo el primer nombre es obligatorio. El segundo no lo es porque
             * mucha gente no tiene, y de los apellidos se exige AL MENOS UNO,
             * no los dos: hay pescadores registrados con un solo apellido, y
             * pedir los dos los dejaría fuera del sistema. Eso lo resuelve
             * `required_without`, que solo exige el paterno cuando el materno
             * viene vacío.
             */
            'primerNombre' => ['required', 'string', 'max:60'],
            'segundoNombre' => ['nullable', 'string', 'max:60'],
            'apellidoPaterno' => ['required_without:apellidoMaterno', 'nullable', 'string', 'max:60'],
            'apellidoMaterno' => ['nullable', 'string', 'max:60'],
            'apellidoCasada' => ['nullable', 'string', 'max:60'],

            'fechaNacimiento' => ['nullable', 'date', 'before:today'],
            'genero' => ['nullable', Rule::in(['masculino', 'femenino'])],
            'nacionalidad' => ['nullable', 'string', 'max:255'],

            'direccion' => ['nullable', 'string', 'max:1000'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            // Lista abierta a propósito: el pescador puede vivir fuera del
            // Beni y las ocho provincias son solo las de acá.
            'provincia' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],

            // La foto sirve para las credenciales (cédula de pescador). El
            // peso y los formatos salen de config/jichi.php, que es donde vive
            // el límite único del sistema.
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
            'ci_nit.required' => 'El número de cédula es obligatorio.',
            'ci_nit.unique' => 'Ya existe un solicitante registrado con esa cédula y complemento.',
            'expedido.in' => 'El lugar de expedición no es un departamento válido.',
            'primerNombre.required' => 'El primer nombre es obligatorio.',
            'apellidoPaterno.required_without' => 'Indique al menos un apellido.',
            'fechaNacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'foto.image' => 'La foto debe ser una imagen.',
            'foto.max' => 'La foto no puede pesar más de '.round(config('jichi.archivos.max_kb') / 1024, 1).' MB.',
        ];
    }

    /**
     * Normaliza los datos ANTES de validar.
     *
     * Ventanilla escribe con espacios de más y en minúsculas; acá se limpia
     * una sola vez para que la base guarde siempre el mismo formato.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'ci_nit' => trim((string) $this->input('ci_nit')),
            'complemento' => filled($this->input('complemento'))
                ? strtoupper(trim((string) $this->input('complemento')))
                : null,
            'expedido' => filled($this->input('expedido'))
                ? strtoupper(trim((string) $this->input('expedido')))
                : null,
            'email' => filled($this->input('email'))
                ? strtolower(trim((string) $this->input('email')))
                : null,
            'quitar_foto' => $this->boolean('quitar_foto'),
        ]);

        // Las cinco partes del nombre se limpian igual, en un solo lugar. Un
        // espacio de más al final no se ve en pantalla pero sí viaja a
        // nombreCompleto y ensucia el nombre impreso en la credencial.
        foreach (['primerNombre', 'segundoNombre', 'apellidoPaterno', 'apellidoMaterno', 'apellidoCasada'] as $parte) {
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
            'ci_nit' => 'cédula de identidad',
            'expedido' => 'lugar de expedición',
            'primerNombre' => 'primer nombre',
            'segundoNombre' => 'segundo nombre',
            'apellidoPaterno' => 'apellido paterno',
            'apellidoMaterno' => 'apellido materno',
            'apellidoCasada' => 'apellido de casada',
            'fechaNacimiento' => 'fecha de nacimiento',
            'direccion' => 'dirección',
            'telefono' => 'teléfono',
        ];
    }
}

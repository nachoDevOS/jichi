<?php

namespace App\Http\Requests\Panel;

use App\Enums\EstadoAsociacion;
use App\Models\Asociacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para crear y editar una asociación.
 */
class GuardarAsociacionRequest extends FormRequest
{
    /** El permiso ya lo revisa el middleware de la ruta. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $asociacion = $this->route('asociacion');
        $idActual = $asociacion instanceof Asociacion ? $asociacion->id : null;

        return [
            /*
             * DOS ASOCIACIONES NO PUEDEN LLAMARSE IGUAL.
             */
            'nombre' => [
                'required', 'string', 'max:160',
                Rule::unique('asociaciones', 'nombre')
                    ->where(fn ($q) => $q->whereNull('deleted_at'))
                    ->ignore($idActual),
            ],

            /*
             * La sigla es opcional porque muchas asociaciones chicas no tienen
             * una registrada. Cuando existe, es lo que entra en el renglón
             * angosto del carnet, y por eso el tope es corto: 20 caracteres ya
             * no son una sigla.
             */
            'sigla' => ['nullable', 'string', 'max:20'],

            /*
             * LA FICHA DEL GREMIO. Las claves son las de `Asociacion::CAMPOS`
             * y nada más: `prepareForValidation()` descarta el resto, así que
             * lo que llegue de más del navegador no entra en la columna.
             */
            'datos' => ['nullable', 'array'],
            'datos.*' => ['nullable', 'string', 'max:255'],
            'datos.correo' => ['nullable', 'email', 'max:255'],
            'datos.fundacion' => ['nullable', 'date', 'before_or_equal:today'],

            'estado' => ['required', Rule::enum(EstadoAsociacion::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la asociación es obligatorio.',
            'nombre.unique' => 'Ya existe una asociación registrada con ese nombre.',
            'estado.required' => 'Indique si la asociación está activa.',
            'datos.correo.email' => 'El correo de la asociación no tiene un formato válido.',
            'datos.fundacion.before_or_equal' => 'La fecha de fundación no puede ser futura.',
            'datos.*.max' => 'Cada dato de la asociación no puede pasar de 255 caracteres.',
        ];
    }

    /**
     * La sigla va en MAYÚSCULAS siempre.
     *
     * No es cosmética: se imprime en el carnet y en las guías, y una lista
     * donde conviven «ASOPESCA» y «Asopesca» se lee como dos gremios distintos.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'sigla' => filled($this->input('sigla'))
                ? mb_strtoupper(trim((string) $this->input('sigla')))
                : null,
            // Una asociación nueva nace activa: es lo que se quiere el 99% de
            // las veces y evita que el formulario obligue a elegir lo obvio.
            'estado' => $this->input('estado') ?: EstadoAsociacion::Activo->value,

            'datos' => $this->fichaLimpia(),
        ]);
    }

    /**
     * La ficha, con SOLO las claves conocidas y sin los campos vacíos.
     *
     * Las dos cosas importan. Filtrar por `Asociacion::CAMPOS` impide que algo
     * escrito en el navegador entre en la columna; sacar los vacíos evita
     * guardar `{"telefono": "", "correo": ""}`, que después obliga a preguntar
     * dos veces —existe la clave, pero no dice nada—. Sin ninguna clave se
     * guarda NULL, que es «no se cargó la ficha».
     *
     * @return array<string, string>|null
     */
    private function fichaLimpia(): ?array
    {
        $entrada = $this->input('datos');

        if (! is_array($entrada)) {
            return null;
        }

        $ficha = collect($entrada)
            ->only(array_keys(Asociacion::CAMPOS))
            ->map(fn ($valor): string => trim((string) $valor))
            ->filter(fn (string $valor): bool => $valor !== '')
            ->all();

        return $ficha === [] ? null : $ficha;
    }
}

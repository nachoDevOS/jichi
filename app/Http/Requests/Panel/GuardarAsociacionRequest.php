<?php

namespace App\Http\Requests\Panel;

use App\Enums\EstadoAsociacion;
use App\Models\Asociacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para crear y editar una asociación.
 *
 * Mismo criterio que GuardarBeneficiarioRequest: crear y editar comparten las
 * reglas, así que se escriben una sola vez y el día que cambien no hay dos
 * lados que sincronizar.
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
             *
             * La regla replica el índice único PARCIAL de la base
             * (`asociaciones_nombre_unico`), que solo mira las filas vivas. El
             * `whereNull('deleted_at')` es lo que reproduce esa parcialidad:
             * sin él, un nombre liberado por una baja seguiría bloqueado acá y
             * la pantalla diría «ya existe» sobre algo que la base aceptaría.
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
        ]);
    }
}

<?php

namespace App\Http\Requests\Panel;

use App\Enums\TipoActor;
use App\Models\TipoCarnet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para CORREGIR una credencial pendiente.
 *
 * Casi las mismas que al emitir, con dos diferencias: el titular no viaja —no
 * se corrige de quién es un carnet, eso es otro carnet— y los adjuntos son
 * opcionales, porque lo normal es no volver a subir lo que ya está.
 */
class EditarCarnetRequest extends FormRequest
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
        return [
            /*
             * Solo asociaciones ACTIVAS y no dadas de baja. Una inactiva sigue
             * existiendo —los carnets viejos apuntan a ella— pero no se puede
             * elegir para emitir.
             */
            'asociacion_id' => [
                'required', 'integer',
                Rule::exists('asociaciones', 'id')
                    ->where('estado', 'activo')
                    ->whereNull('deleted_at'),
            ],

            /*
             * EL TIPO DECIDE LA ACTIVIDAD. No se pregunta aparte: cada tipo del
             * catálogo declara su `tipo_actor`, y el controlador lo lee de ahí.
             */
            'tipo_carnet_id' => [
                'required', 'integer',
                Rule::exists('tipos_carnet', 'id')->where('estado', true),
            ],

            /*
             * LA BOLSA MADRE. Opcional: si no viene, el servicio toma el cupo
             * vigente de la persona. En un comercializador está PROHIBIDA —no
             * extrae, así que no lleva cupo—, y quién es comercializador lo
             * dice el TIPO elegido.
             */
            'aprovechamiento_id' => [
                'nullable', 'integer',
                Rule::prohibitedIf(fn (): bool => $this->tipoElegido()?->tipo_actor === TipoActor::Comercializador),
                Rule::exists('aprovechamientos_pesq', 'id')->whereNull('deleted_at'),
            ],

            /*
             * LA FECHA EN QUE SE PIDIÓ, no la de emisión: esa la escribe la
             * aprobación. Futura no, porque todavía no ocurrió; pasada sí,
             * para poner al día lo emitido en papel.
             */
            'fecha_solicitud' => ['required', 'date', 'before_or_equal:today'],

            /*
             * LOS DOS PAPELES QUE RESPALDAN LA EMISIÓN: la cédula y la carta de
             * la asociación. El tope de 3 MB es el mismo que aplica
             * StorageController —la última línea de defensa—; acá está para que
             * el mensaje diga qué pasó en vez de un error de subida.
             */
            'archivo_ci' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:3072'],
            'archivo_asociacion' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:3072'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asociacion_id.required' => 'Elija la asociación que la certifica.',
            'asociacion_id.exists' => 'Esa asociación no existe o está inactiva.',
            'tipo_carnet_id.required' => 'Elija el tipo de carnet.',
            'tipo_carnet_id.exists' => 'Ese tipo de carnet no existe o está fuera de uso.',
            'aprovechamiento_id.prohibited' => 'Un carnet de comercializador no lleva cupo de pesca.',
            'aprovechamiento_id.exists' => 'Ese aprovechamiento no existe o fue dado de baja.',
            'fecha_solicitud.required' => 'Indique la fecha de la solicitud.',
            'fecha_solicitud.before_or_equal' => 'La fecha de la solicitud no puede ser futura.',
            'archivo_ci.mimes' => 'La cédula tiene que ser una imagen (JPG, PNG, WEBP) o un PDF.',
            'archivo_ci.max' => 'La cédula no puede pasar de 3 MB.',
            'archivo_asociacion.mimes' => 'El documento de la asociación tiene que ser una imagen '.
                '(JPG, PNG, WEBP) o un PDF.',
            'archivo_asociacion.max' => 'El documento de la asociación no puede pasar de 3 MB.',
        ];
    }

    /** El tipo que se eligió, o null si no vino o no existe. */
    private function tipoElegido(): ?TipoCarnet
    {
        return TipoCarnet::query()->find($this->input('tipo_carnet_id'));
    }

    protected function prepareForValidation(): void
    {
        // Lo normal es emitir hoy; sin este valor por defecto la validación
        // rechazaría un campo que no hace falta preguntar.
        $this->merge([
            'fecha_solicitud' => $this->input('fecha_solicitud') ?: now()->toDateString(),
        ]);
    }
}

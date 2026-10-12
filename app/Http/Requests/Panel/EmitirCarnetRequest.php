<?php

namespace App\Http\Requests\Panel;

use App\Enums\TipoActor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para emitir una credencial.
 */
class EmitirCarnetRequest extends FormRequest
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
            // El whereNull deja fuera a los dados de baja: la tabla usa borrado
            // lógico, así que sin él el id de una ficha muerta pasaría.
            'beneficiario_id' => [
                'required', 'integer',
                Rule::exists('beneficiarios', 'id')->whereNull('deleted_at'),
            ],

            // Solo ACTIVAS: una inactiva sigue existiendo —los carnets viejos
            // apuntan a ella— pero no se puede elegir para emitir.
            'asociacion_id' => [
                'required', 'integer',
                Rule::exists('asociaciones', 'id')
                    ->where('estado', 'activo')
                    ->whereNull('deleted_at'),
            ],

            // La actividad decide qué emite el carnet y qué fila de Aranceles cobra.
            'tipo_actor' => ['required', Rule::enum(TipoActor::class)],

            // Una tarifa del servicio «Carnet» de SIREB: si se puede cobrar lo decide el servicio.
            'tarifa_sireb' => ['required', 'uuid'],

            // Opcional: sin ella el servicio toma el cupo vigente de la persona.
            // En un comercializador está PROHIBIDA: no extrae, no lleva cupo.
            'aprovechamiento_id' => [
                'nullable', 'integer',
                Rule::prohibitedIf(fn (): bool => $this->input('tipo_actor') === TipoActor::Comercializador->value),
                Rule::exists('aprovechamientos_pesq', 'id')->whereNull('deleted_at'),
            ],

            // La fecha en que se PIDIÓ; la de emisión la escribe la aprobación.
            // Pasada sí, para poner al día lo emitido en papel.
            'fecha_solicitud' => ['required', 'date', 'before_or_equal:today'],

            // El tope de 3 MB lo aplica igual StorageController; acá está para que
            // el mensaje diga qué pasó en vez de un error de subida.
            'archivo_ci' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:3072'],
            'archivo_asociacion' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:3072'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'beneficiario_id.required' => 'Elija a la persona que recibe la credencial.',
            'beneficiario_id.exists' => 'Esa persona no está en el padrón o fue dada de baja.',
            'asociacion_id.required' => 'Elija la asociación que la certifica.',
            'asociacion_id.exists' => 'Esa asociación no existe o está inactiva.',
            'tipo_actor.required' => 'Elija la actividad del carnet.',
            'tipo_actor.enum' => 'Esa actividad no existe.',
            'tarifa_sireb.required' => 'Elija la tarifa del carnet.',
            'tarifa_sireb.uuid' => 'Esa tarifa no es de SIREB.',
            'aprovechamiento_id.prohibited' => 'Un carnet de comercializador no lleva cupo de pesca.',
            'aprovechamiento_id.exists' => 'Ese aprovechamiento no existe o fue dado de baja.',
            'fecha_solicitud.required' => 'Indique la fecha de la solicitud.',
            'fecha_solicitud.before_or_equal' => 'La fecha de la solicitud no puede ser futura.',
            'archivo_ci.required' => 'Adjunte la cédula del titular.',
            'archivo_ci.mimes' => 'La cédula tiene que ser una imagen (JPG, PNG, WEBP) o un PDF.',
            'archivo_ci.max' => 'La cédula no puede pasar de 3 MB.',
            'archivo_asociacion.required' => 'Adjunte la carta o certificación de la asociación.',
            'archivo_asociacion.mimes' => 'El documento de la asociación tiene que ser una imagen '.
                '(JPG, PNG, WEBP) o un PDF.',
            'archivo_asociacion.max' => 'El documento de la asociación no puede pasar de 3 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Lo normal es emitir hoy; sin este valor por defecto la validación
        // rechazaría un campo que no hace falta preguntar.
        $this->merge([
            'fecha_solicitud' => $this->input('fecha_solicitud') ?: now()->toDateString(),
            // SIREB manda los ids en minúscula: así se comparan tal cual.
            'tarifa_sireb' => mb_strtolower(trim((string) $this->input('tarifa_sireb'))),
        ]);
    }
}

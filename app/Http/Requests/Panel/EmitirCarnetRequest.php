<?php

namespace App\Http\Requests\Panel;

use App\Enums\EstadoAsociacion;
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
                    ->where('estado', EstadoAsociacion::Activo->value)
                    ->whereNull('deleted_at'),
            ],

            // La actividad decide qué emite el carnet y qué fila de Aranceles cobra.
            'tipo_actor' => ['required', Rule::enum(TipoActor::class)],

            // Opcional: sin ella el servicio toma el cupo vigente de la persona.
            // En un comercializador está PROHIBIDA: no extrae, no lleva cupo.
            'aprovechamiento_id' => [
                'nullable', 'integer',
                Rule::prohibitedIf(fn (): bool => $this->input('tipo_actor') === TipoActor::Comercializador->value),
                Rule::exists('aprovechamientos_pesq', 'id')->whereNull('deleted_at'),
            ],

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
            'aprovechamiento_id.prohibited' => 'Un carnet de comercializador no lleva cupo de pesca.',
            'aprovechamiento_id.exists' => 'Ese aprovechamiento no existe o fue dado de baja.',
            'archivo_ci.required' => 'Adjunte la cédula del titular.',
            'archivo_ci.mimes' => 'La cédula tiene que ser una imagen (JPG, PNG, WEBP) o un PDF.',
            'archivo_ci.max' => 'La cédula no puede pasar de 3 MB.',
            'archivo_asociacion.required' => 'Adjunte la carta o certificación de la asociación.',
            'archivo_asociacion.mimes' => 'El documento de la asociación tiene que ser una imagen '.
                '(JPG, PNG, WEBP) o un PDF.',
            'archivo_asociacion.max' => 'El documento de la asociación no puede pasar de 3 MB.',
        ];
    }
}

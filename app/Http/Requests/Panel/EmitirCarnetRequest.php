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

            'tipo_carnet_id' => [
                'required', 'integer',
                Rule::exists('tipos_carnet', 'id')->where('estado', true),
            ],

            /*
             * LA ACTIVIDAD ES UN ENUM Y NO UN CATÁLOGO, y por eso se valida
             * contra la clase de PHP.
             */
            'tipo_actor' => ['required', Rule::enum(TipoActor::class)],

            /*
             * No se emite con fecha futura: el carnet vence con la gestión, así
             * que una del año que viene daría una credencial que arranca
             * vencida. Pasada sí, para poner al día lo emitido en papel.
             */
            'fecha_emision' => ['required', 'date', 'before_or_equal:today'],
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
            'tipo_carnet_id.required' => 'Elija el tipo de carnet.',
            'tipo_carnet_id.exists' => 'Ese tipo de carnet no existe o está fuera de uso.',
            'tipo_actor.required' => 'Indique si el carnet es de pescador o de comercializador.',
            'fecha_emision.required' => 'Indique la fecha de emisión.',
            'fecha_emision.before_or_equal' => 'La fecha de emisión no puede ser futura.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Lo normal es emitir hoy; sin este valor por defecto la validación
        // rechazaría un campo que no hace falta preguntar.
        $this->merge([
            'fecha_emision' => $this->input('fecha_emision') ?: now()->toDateString(),
        ]);
    }
}

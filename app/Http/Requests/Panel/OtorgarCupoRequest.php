<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para otorgar una bolsa madre.
 */
class OtorgarCupoRequest extends FormRequest
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
             * `exists` con el whereNull: un beneficiario dado de baja no puede
             * recibir un cupo nuevo. La tabla usa borrado lógico, así que sin
             * esa condición el id de una ficha dada de baja pasaría el control.
             */
            'beneficiario_id' => [
                'required', 'integer',
                Rule::exists('beneficiarios', 'id')->whereNull('deleted_at'),
            ],

            /*
             * Solo tramos VIGENTES. Un tramo derogado sigue existiendo en la
             * tabla —los cupos otorgados apuntan a él— pero no se puede elegir.
             */
            'categoria_aprov_id' => [
                'required', 'integer',
                Rule::exists('categorias_aprovechamiento', 'id')->where('estado', true),
            ],

            /*
             * LA FECHA EN QUE SE PIDIÓ, no la de otorgamiento: esa la escribe
             * la aprobación. Futura no, porque todavía no ocurrió.
             */
            'fecha_solicitud' => ['required', 'date', 'before_or_equal:today'],

            /*
             * OBLIGATORIO. Es el renglón «Tipo de Embarcación» de la
             * autorización de pesca: sin él, el papel que el sistema imprime
             * sale con un hueco que después alguien llena a mano.
             */
            'tipo_embarcacion' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'beneficiario_id.required' => 'Elija a la persona que recibe el cupo.',
            'beneficiario_id.exists' => 'Esa persona no está en el padrón o fue dada de baja.',
            'categoria_aprov_id.required' => 'Elija el tramo de la escala que corresponde.',
            'categoria_aprov_id.exists' => 'Ese tramo de la escala no existe o fue derogado.',
            'fecha_solicitud.required' => 'Indique la fecha de la solicitud.',
            'fecha_solicitud.before_or_equal' => 'La fecha de la solicitud no puede ser futura.',
            'tipo_embarcacion.required' => 'Indique el tipo de embarcación: va impreso en la autorización.',
            'tipo_embarcacion.max' => 'El tipo de embarcación no puede pasar de 120 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Lo normal es que se pida hoy; el campo llega vacío si el operador no
        // lo tocó, y sin este valor por defecto la validación lo rechazaría por
        // algo que no hace falta preguntar.
        $this->merge([
            'fecha_solicitud' => $this->input('fecha_solicitud') ?: now()->toDateString(),

            // Sin espacios de más: «  canoa » y «canoa» son lo mismo, y con el
            // espacio el `required` daría por bueno un campo en blanco.
            'tipo_embarcacion' => trim((string) $this->input('tipo_embarcacion')),
        ]);
    }
}

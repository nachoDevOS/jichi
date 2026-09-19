<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para emitir una guía de movimiento.
 *
 * ----------------------------------------------------------------------------
 *  ACÁ SOLO SE VALIDA LA FORMA. LAS REGLAS VIVEN EN EL SERVICIO
 * ----------------------------------------------------------------------------
 *
 * Que el carnet HABILITE a emitir guías —que sea de comercializador y esté
 * vigente— lo decide `EmitirGuiaService::emitir()`: son dos condiciones que
 * dependen de la fecha de hoy y no se pueden expresar en una regla de
 * validación.
 */
class EmitirGuiaRequest extends FormRequest
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
            'carnet_id' => ['required', 'integer', Rule::exists('carnets', 'id')],

            /*
             * EL CÓDIGO DEL TALONARIO, único GLOBAL.
             *
             * La regla replica el índice único de la base. Que esté duplicada
             * acá y en el servicio no es redundancia inútil: esta pinta el
             * mensaje bajo el campo, la del servicio cubre la carrera entre dos
             * ventanillas, y el índice es la red que garantiza.
             */
            'codigo_guia' => [
                'required', 'string', 'max:40',
                Rule::unique('guias_movimiento', 'codigo_guia'),
            ],

            'origen' => ['required', 'string', 'max:160'],
            'destino' => ['required', 'string', 'max:160'],

            /*
             * El peso declarado al salir. `gt:0` porque una guía de cero kilos
             * no ampara nada y solo gastaría una hoja del talonario.
             *
             * Al CERRAR se puede corregir contra la balanza del destino, que es
             * donde el número se vuelve real.
             */
            'peso_total_kg' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],

            /*
             * LA MARCA QUE VALE PLATA: con ella el arancel se cobra al 50%.
             *
             * Va como booleano y no como un catálogo de «tipo de producto»
             * porque la resolución solo distingue dos casos, y un catálogo
             * abriría la puerta a que alguien agregue una fila con descuento sin
             * que haya resolución detrás.
             */
            'es_piscicultura' => ['required', 'boolean'],

            /*
             * NO se emite con fecha futura: la guía ampara el traslado desde ese
             * momento, y adelantarla daría un papel que empieza a valer antes de
             * existir. Pasada sí, para poner al día lo emitido en papel.
             */
            'fecha_emision' => ['required', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'carnet_id.required' => 'Elija el carnet del comercializador.',
            'carnet_id.exists' => 'Ese carnet no existe.',
            'codigo_guia.required' => 'Escriba el código de la hoja del talonario.',
            'codigo_guia.unique' => 'Ya existe una guía con ese código. Verifique la hoja que tiene en la mano.',
            'origen.required' => 'Indique desde dónde sale la carga.',
            'destino.required' => 'Indique a dónde va la carga.',
            'peso_total_kg.required' => 'Indique el peso total de la carga.',
            'peso_total_kg.gt' => 'El peso tiene que ser mayor que cero.',
            'peso_total_kg.decimal' => 'El peso lleva como máximo dos decimales.',
            'es_piscicultura.required' => 'Indique si el producto es de piscicultura.',
            'fecha_emision.before_or_equal' => 'La fecha de emisión no puede ser futura.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // El código va en MAYÚSCULAS: se imprime y se dicta, y una lista
            // donde conviven «gui-001» y «GUI-001» se lee como dos papeles.
            'codigo_guia' => mb_strtoupper(trim((string) $this->input('codigo_guia'))),
            'origen' => trim((string) $this->input('origen')),
            'destino' => trim((string) $this->input('destino')),
            'es_piscicultura' => $this->boolean('es_piscicultura'),
            // La guía se emite en el momento: se manda la HORA además del día,
            // porque los cinco días de validez se cuentan desde ese instante.
            'fecha_emision' => $this->input('fecha_emision') ?: now()->toDateTimeString(),
        ]);
    }
}

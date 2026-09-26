<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para emitir un permiso de faena.
 */
class EmitirFaenaRequest extends FormRequest
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
            // Que exista. Que HABILITE lo decide el servicio: son tres condiciones
            // que dependen de la fecha de hoy y del saldo.
            'carnet_id' => ['required', 'integer', Rule::exists('carnets', 'id')],

            // El número NO viene del formulario: lo genera el correlativo.

            // `gt:0`: una faena de cero kilos no autoriza nada y gastaría una hoja.
            'kilos_extraidos' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],

            // Las fechas NO vienen del formulario: las escribe la aprobación.

            /*
             * LOS RENGLONES DEL PAPEL. Nullable porque el formulario se llena a
             * mano y llega incompleto: la obligatoriedad es del trámite en
             * ventanilla, no de la tabla.
             */
            'embarcacion' => ['nullable', 'string', 'max:150'],
            'propietario' => ['nullable', 'string', 'max:150'],
            'comandante_barco' => ['nullable', 'string', 'max:150'],
            'matricula_naval' => ['nullable', 'string', 'max:50'],
            'nro_kardex' => ['nullable', 'string', 'max:50'],
            'region_desde' => ['nullable', 'string', 'max:150'],
            'region_hasta' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'carnet_id.required' => 'Elija el carnet del pescador.',
            'carnet_id.exists' => 'Ese carnet no existe.',
            'kilos_extraidos.required' => 'Indique cuántos kilos autoriza la faena.',
            'kilos_extraidos.gt' => 'La faena tiene que autorizar kilos: escriba un número mayor que cero.',
            'kilos_extraidos.decimal' => 'Los kilos llevan como máximo dos decimales.',
        ];
    }
}

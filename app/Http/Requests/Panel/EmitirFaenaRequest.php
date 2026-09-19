<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para emitir un permiso de faena.
 *
 * ----------------------------------------------------------------------------
 *  ACÁ SOLO SE VALIDA LA FORMA. EL CUPO SE COMPRUEBA EN EL SERVICIO
 * ----------------------------------------------------------------------------
 *
 * Que los kilos entren en el saldo NO se comprueba acá, y no es un olvido: el
 * saldo puede moverlo otra ventanilla en el mismo segundo, así que esa
 * comparación tiene que correr DENTRO de la transacción y con la fila del
 * aprovechamiento bloqueada. Ver EmitirFaenaService::emitir().
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
            /*
             * El carnet tiene que existir; que HABILITE a emitir faenas lo
             * decide el servicio, mirando el tipo de actor, la vigencia y el
             * cupo. Acá no se puede: son tres condiciones que dependen de la
             * fecha de hoy y del saldo.
             */
            'carnet_id' => ['required', 'integer', Rule::exists('carnets', 'id')],

            /*
             * EL NÚMERO DEL TALONARIO. Entero y positivo, nada más: que no se
             * repita dentro del cupo lo comprueba el servicio y lo garantiza el
             * índice único `(aprovechamiento_id, numero_faena)`.
             */
            'numero_faena' => ['required', 'integer', 'min:1', 'max:999999'],

            /*
             * Los kilos declarados. `gt:0` porque una faena de cero kilos no
             * autoriza nada y solo gastaría una hoja del talonario.
             */
            'kilos_extraidos' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],

            /*
             * NO SE EMITE CON FECHA FUTURA: la faena autoriza a estar pescando
             * desde ese día, y una fecha adelantada daría un permiso que empieza
             * a valer antes de que el papel exista.
             *
             * Pasada sí, para poner al día lo emitido en papel.
             */
            'fecha_salida' => ['required', 'date', 'before_or_equal:today'],
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
            'numero_faena.required' => 'Escriba el número de la hoja del talonario.',
            'numero_faena.min' => 'El número del talonario arranca en 1.',
            'kilos_extraidos.required' => 'Indique cuántos kilos autoriza la faena.',
            'kilos_extraidos.gt' => 'La faena tiene que autorizar kilos: escriba un número mayor que cero.',
            'kilos_extraidos.decimal' => 'Los kilos llevan como máximo dos decimales.',
            'fecha_salida.required' => 'Indique la fecha de salida.',
            'fecha_salida.before_or_equal' => 'La fecha de salida no puede ser futura.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fecha_salida' => $this->input('fecha_salida') ?: now()->toDateString(),
        ]);
    }
}

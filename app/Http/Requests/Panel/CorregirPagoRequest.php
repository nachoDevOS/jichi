<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para CORREGIR un depósito, con dos diferencias contra la carga:
 */
class CorregirPagoRequest extends FormRequest
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
            'monto_parcial' => ['required', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,2'],

            'nro_transaccion' => [
                // SOLO DÍGITOS, y va `digits_between` y no `numeric`: la boleta
                // suele empezar con ceros y `numeric` se los comería.
                'required', 'string', 'digits_between:1,60',
                Rule::unique('pagos', 'nro_transaccion')
                    ->ignore($this->route('pago'))
                    ->whereNull('deleted_at'),
            ],

            // La que dice la boleta. Futura no: todavía no ocurrió.
            'fecha_deposito' => ['required', 'date', 'before_or_equal:today'],

            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:3072'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto_parcial.required' => 'Indique cuánto fue este depósito.',
            'monto_parcial.gt' => 'El depósito tiene que ser mayor que cero.',
            'monto_parcial.decimal' => 'Los montos llevan como máximo dos decimales.',
            'nro_transaccion.required' => 'Escriba el número de la boleta del banco.',
            'nro_transaccion.digits_between' => 'El número de la boleta lleva solo dígitos.',
            'nro_transaccion.unique' => 'Esa boleta ya está cargada en otro cobro: '.
                'una misma transacción no puede respaldar dos pagos.',
            'fecha_deposito.required' => 'Indique la fecha que figura en la boleta.',
            'fecha_deposito.before_or_equal' => 'La fecha del depósito no puede ser futura.',
            'comprobante.mimes' => 'La boleta tiene que ser una imagen (JPG, PNG, WEBP) o un PDF.',
            'comprobante.max' => 'La boleta no puede pesar más de 3 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Sin espacios y en mayúscula: «A-123» y «a 123» pasarían las dos.
        if (filled($this->input('nro_transaccion'))) {
            $this->merge([
                'nro_transaccion' => mb_strtoupper(
                    preg_replace('/\s+/', '', (string) $this->input('nro_transaccion')),
                ),
            ]);
        }
    }
}

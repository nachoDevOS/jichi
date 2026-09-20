<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para cargar UNO O VARIOS depósitos contra un aprovechamiento.
 */
class RegistrarPagoCupoRequest extends FormRequest
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
             * AL MENOS UNO. Enviar el formulario vacío gastaría un número del
             * correlativo de recibos para no decir nada.
             */
            'pagos' => ['required', 'array', 'min:1', 'max:20'],

            'pagos.*.monto' => ['required', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,2'],

            /*
             * LA BOLETA ES SIEMPRE OBLIGATORIA: todo pago es un depósito
             * bancario —no hay efectivo ni QR— y sin ella lo único que respalda
             * el cobro es que alguien lo tipeó.
             */
            'pagos.*.nro_transaccion' => [
                // SOLO DÍGITOS, y va `digits_between` y no `numeric`: la boleta
                // suele empezar con ceros y `numeric` se los comería.
                'required', 'string', 'digits_between:1,60',
                // Contra la tabla...
                Rule::unique('pagos', 'nro_transaccion')->whereNull('deleted_at'),
                // ...y contra las otras secciones del mismo formulario.
                'distinct:ignore_case',
            ],

            // La fecha que dice la boleta, no la de hoy: un depósito del viernes
            // puede cargarse el lunes. Futura no, porque todavía no ocurrió.
            'pagos.*.fecha_deposito' => ['required', 'date', 'before_or_equal:today'],

            'pagos.*.comprobante' => [
                'required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:3072',
            ],

            /*
             * ¿EL OPERADOR PIDIÓ ENVIARLO A REVISIÓN EN EL MISMO ACTO?
             */
            'enviar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pagos.required' => 'Agregue al menos un depósito.',
            'pagos.min' => 'Agregue al menos un depósito.',
            'pagos.max' => 'No se pueden cargar más de 20 depósitos de una vez.',
            'pagos.*.monto.required' => 'Indique cuánto fue este depósito.',
            'pagos.*.monto.gt' => 'Cada depósito tiene que ser mayor que cero.',
            'pagos.*.monto.decimal' => 'Los montos llevan como máximo dos decimales.',
            'pagos.*.nro_transaccion.required' => 'Escriba el número de la boleta del banco.',
            'pagos.*.nro_transaccion.digits_between' => 'El número de la boleta lleva solo dígitos.',
            'pagos.*.nro_transaccion.unique' => 'Esa boleta ya está cargada en otro cobro: '.
                'una misma transacción no puede respaldar dos pagos.',
            'pagos.*.nro_transaccion.distinct' => 'Repitió el mismo número de boleta en dos depósitos.',
            'pagos.*.fecha_deposito.required' => 'Indique la fecha que figura en la boleta.',
            'pagos.*.fecha_deposito.before_or_equal' => 'La fecha del depósito no puede ser futura.',
            'pagos.*.comprobante.required' => 'Adjunte la boleta del depósito.',
            'pagos.*.comprobante.mimes' => 'La boleta tiene que ser una imagen (JPG, PNG, WEBP) o un PDF.',
            'pagos.*.comprobante.max' => 'La boleta no puede pesar más de 3 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $pagos = $this->input('pagos');

        if (! is_array($pagos)) {
            return;
        }

        // Sin espacios y en mayúscula: la misma boleta tipeada «A-123» y
        // «a 123» pasaría dos veces el control de unicidad.
        foreach ($pagos as $i => $pago) {
            $pagos[$i]['nro_transaccion'] = filled($pago['nro_transaccion'] ?? null)
                ? mb_strtoupper(preg_replace('/\s+/', '', (string) $pago['nro_transaccion']))
                : null;
        }

        $this->merge([
            'pagos' => $pagos,
            // Llega como texto desde un FormData: sin esto, «false» sería `true`.
            'enviar' => $this->boolean('enviar'),
        ]);
    }
}

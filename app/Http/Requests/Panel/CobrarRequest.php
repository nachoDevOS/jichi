<?php

namespace App\Http\Requests\Panel;

use App\Services\CobrarService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para emitir un cobro.
 */
class CobrarRequest extends FormRequest
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
            // Al menos una línea: un recibo vacío gastaría un número del
            // correlativo para no decir nada.
            'lineas' => ['required', 'array', 'min:1'],

            // Contra la LISTA BLANCA del servicio y no contra nombres de clase:
            // mandada directo, cualquiera escribiría otra desde el navegador.
            'lineas.*.tipo' => ['required', Rule::in(array_keys(CobrarService::COBRABLES))],
            'lineas.*.id' => ['required', 'integer', 'min:1'],
            'lineas.*.monto' => ['required', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,2'],

            /*
             *  LA BOLETA ES SIEMPRE OBLIGATORIA
             */
            'nro_transaccion' => [
                // SOLO DÍGITOS, y va `digits_between` y no `numeric`: la boleta
                // suele empezar con ceros y `numeric` se los comería.
                'required', 'string', 'digits_between:1,60',
                // Único entre los pagos VIVOS: impide cargar la misma boleta dos
                // veces, que es la forma más fácil de dar por pagado lo que no se pagó.
                Rule::unique('pagos', 'nro_transaccion')->whereNull('deleted_at'),
            ],

            // La que dice la BOLETA, no la de hoy: un depósito del viernes puede
            // cargarse el lunes. Futura no.
            'fecha_deposito' => ['required', 'date', 'before_or_equal:today'],

            // El tope de 3 MB es el mismo que aplica StorageController, que es
            // la última línea de defensa. Acá el mensaje dice qué pasó.
            'comprobante' => [
                'required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:3072',
            ],

            // Opcional: sin él, el servicio arma uno con los trámites cobrados.
            'concepto' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lineas.required' => 'Elija al menos un trámite para cobrar.',
            'lineas.min' => 'Elija al menos un trámite para cobrar.',
            'lineas.*.monto.required' => 'Indique cuánto se cobra de cada trámite.',
            'lineas.*.monto.gt' => 'Cada abono tiene que ser mayor que cero.',
            'lineas.*.monto.decimal' => 'Los montos llevan como máximo dos decimales.',
            'nro_transaccion.required' => 'Escriba el número de la boleta del banco.',
            'nro_transaccion.digits_between' => 'El número de la boleta lleva solo dígitos.',
            'fecha_deposito.required' => 'Indique la fecha que figura en la boleta.',
            'fecha_deposito.before_or_equal' => 'La fecha del depósito no puede ser futura.',
            'nro_transaccion.unique' => 'Esa boleta ya está cargada en otro cobro. Revise el número: '.
                'una misma transacción no puede respaldar dos pagos.',
            'comprobante.required' => 'Adjunte la boleta del depósito.',
            'comprobante.mimes' => 'La boleta tiene que ser una imagen (JPG, PNG, WEBP) o un PDF.',
            'comprobante.max' => 'La boleta no puede pesar más de 3 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // El número va sin espacios y en mayúscula: la misma boleta tipeada
            // «A-123» y «a 123» pasaría dos veces el control de unicidad.
            'nro_transaccion' => filled($this->input('nro_transaccion'))
                ? mb_strtoupper(preg_replace('/\s+/', '', (string) $this->input('nro_transaccion')))
                : null,
            'concepto' => filled($this->input('concepto')) ? trim((string) $this->input('concepto')) : null,
        ]);
    }
}

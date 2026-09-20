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
            /*
             * AL MENOS UNA LÍNEA. Un recibo sin nada cobrado gastaría un número
             * del correlativo para no decir nada, y la serie quedaría con un
             * hueco que después alguien tiene que explicar.
             */
            'lineas' => ['required', 'array', 'min:1'],

            /*
             * EL TIPO SE VALIDA CONTRA LA LISTA BLANCA del servicio, no contra
             * nombres de clase. `pagos.pagable_type` guarda una clase: si el
             * formulario la mandara directo, cualquiera podría escribir otra en
             * el navegador y el sistema crearía filas apuntando a cualquier
             * tabla.
             */
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
                /*
                 * ÚNICO entre los pagos VIVOS. Es lo que impide cargar la misma
                 * boleta dos veces —contra el mismo trámite o contra otro—, que
                 * es la forma más fácil de dar por pagado algo que no se pagó.
                 */
                Rule::unique('pagos', 'nro_transaccion')->whereNull('deleted_at'),
            ],

            /*
             * LA FECHA QUE DICE LA BOLETA, no la de hoy: un depósito del viernes
             * puede cargarse el lunes. Futura no, porque todavía no ocurrió.
             */
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

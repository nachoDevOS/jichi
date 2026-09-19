<?php

namespace App\Http\Requests\Panel;

use App\Enums\MetodoPago;
use App\Services\CobrarService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para emitir un cobro.
 *
 * ----------------------------------------------------------------------------
 *  ACÁ SOLO SE VALIDA LA FORMA. LOS SALDOS SE COMPRUEBAN EN EL SERVICIO
 * ----------------------------------------------------------------------------
 *
 * Que el monto no exceda lo que se debe NO se comprueba acá, y no es un olvido:
 * el saldo puede moverlo otra ventanilla en el mismo segundo, así que esa
 * comparación tiene que correr DENTRO de la transacción y con la fila del
 * trámite bloqueada. Ver CobrarService::resolver().
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

            'metodo_pago' => ['required', Rule::enum(MetodoPago::class)],

            /*
             * Los datos del comprobante se COPIAN al recibo y no se leen del
             * beneficiario cada vez: el papel puede ir a nombre de un tercero
             * —la empresa que paga por el pescador— y además tiene que seguir
             * diciendo lo mismo dentro de cinco años aunque la ficha se corrija.
             */
            'nit_ci_factura' => ['required', 'string', 'max:30'],
            'nombre_factura' => ['required', 'string', 'max:160'],

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
            'metodo_pago.required' => 'Indique por dónde entró el dinero.',
            'nit_ci_factura.required' => 'Escriba el NIT o CI para el comprobante.',
            'nombre_factura.required' => 'Escriba a nombre de quién se emite el comprobante.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nit_ci_factura' => trim((string) $this->input('nit_ci_factura')),
            'nombre_factura' => trim((string) $this->input('nombre_factura')),
            'concepto' => filled($this->input('concepto')) ? trim((string) $this->input('concepto')) : null,
        ]);
    }
}

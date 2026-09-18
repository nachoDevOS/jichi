<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para CORREGIR un depósito ya cargado.
 *
 * Es el formulario de «me equivoqué al tipear»: el monto que no coincide con la
 * boleta, el número de transacción con un dígito de menos, la fecha del día en
 * que se cargó en vez de la del depósito. Corregir es la única salida, porque
 * los pagos no se anulan ni se borran — ver Pago::admiteCorreccion().
 *
 * ----------------------------------------------------------------------------
 *  ES CASI RegistrarPagoRequest, CON DOS DIFERENCIAS
 * ----------------------------------------------------------------------------
 *
 *   LA BOLETA ES OPCIONAL. Lo más común es corregir un número y dejar el
 *   archivo como está; exigirlo obligaría a volver a escanear un papel que ya
 *   está bien, igual que pasa con los adjuntos al editar el expediente.
 *
 *   EL NÚMERO SE COMPARA CONTRA LAS DEMÁS FILAS, no contra todas. Sin excluir
 *   la que se está editando, guardar sin tocar el número respondería «ese
 *   número ya fue registrado» señalando al propio depósito.
 *
 * NO SE VALIDA CONTRA EL SALDO, por lo mismo que en el alta: un depósito puede
 * venir por unos bolivianos de más, y —al revés— corregir uno hacia abajo puede
 * dejar el expediente sin cubrir. Eso último NO es un error de carga: es la
 * realidad del expediente, y quien lo frena es la comprobación de envío a
 * revisión. Ver Tramite::faltantesParaRevision().
 */
class CorregirPagoRequest extends FormRequest
{
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
             * Mismas reglas de forma que el alta: solo dígitos y columna de
             * texto, porque un número de transacción puede empezar con ceros y
             * guardado como entero los pierde. El porqué largo está en
             * RegistrarPagoRequest.
             */
            'nro_transaccion' => [
                'required',
                'string',
                'regex:/^[0-9]+$/',
                'max:50',
                // `ignore` con la fila que se está editando: es su propia
                // coincidencia. El servicio hace la misma salvedad, y el índice
                // único de la tabla es quien garantiza de verdad.
                Rule::unique('pagos', 'nro_transaccion')->ignore($this->route('pago')?->id),
            ],

            'monto' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],

            // OPCIONAL: se reemplaza la que salió ilegible y, si no viene, se
            // conserva la que ya estaba.
            'comprobante' => [
                'nullable',
                'file',
                'mimes:'.implode(',', config('jichi.archivos.extensiones')),
                'max:'.config('jichi.archivos.max_kb'),
            ],

            'fecha_pago' => ['nullable', 'date', 'before_or_equal:today'],

            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mb = round(config('jichi.archivos.max_kb') / 1024, 1);

        return [
            'nro_transaccion.required' => 'Escriba el número de transacción del depósito.',
            'nro_transaccion.regex' => 'El número de transacción son solo dígitos, sin letras, espacios ni guiones.',
            'nro_transaccion.unique' => 'Ese número de transacción ya fue registrado en otro pago.',
            'monto.required' => 'Indique el monto del depósito.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.min' => 'El monto del depósito debe ser mayor a cero.',
            'comprobante.mimes' => 'La boleta debe ser PDF o imagen.',
            'comprobante.max' => "La boleta no puede pesar más de {$mb} MB.",
            'fecha_pago.before_or_equal' => 'La fecha del depósito no puede ser futura.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nro_transaccion' => 'número de transacción',
            'comprobante' => 'boleta del depósito',
            'fecha_pago' => 'fecha del depósito',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nro_transaccion' => trim((string) $this->input('nro_transaccion')),
        ]);
    }
}

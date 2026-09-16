<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas para cargar UN depósito sobre un trámite ya registrado.
 *
 * Es el formulario que usa ventanilla cuando el pescador vuelve con la segunda
 * boleta. La primera —si la trajo el día del alta— entra por
 * RegistrarSolicitudRequest.
 *
 * NO SE VALIDA CONTRA EL SALDO. Podría parecer natural exigir que el monto no
 * supere lo que falta, pero rechazar un depósito por unos bolivianos de más
 * dejaría al pescador sin poder tramitar por un error del cajero del banco. El
 * excedente queda a la vista en la ficha y se resuelve en mostrador.
 */
class RegistrarPagoRequest extends FormRequest
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
             * El número de transacción es único en TODO el sistema, no por
             * trámite: un mismo depósito no puede usarse para pagar dos
             * expedientes distintos.
             *
             * Esta regla da el mensaje legible; quien garantiza de verdad es el
             * índice único de la tabla, porque entre validar y escribir otra
             * petición puede meter la misma boleta. Ver
             * PagoTramiteService::registrar().
             */
            'nro_transaccion' => ['required', 'string', 'max:50', 'unique:pagos,nro_transaccion'],

            // min:0.01 y no min:0 — un pago de cero no es un pago, es una fila
            // que ensucia el historial y hace creer que se cobró algo.
            'monto' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],

            'comprobante' => [
                'required',
                'file',
                // PDF además de imagen: los bancos entregan el respaldo de una
                // transferencia como PDF.
                'mimes:'.implode(',', config('jichi.archivos.extensiones')),
                'max:'.config('jichi.archivos.max_kb'),
            ],

            // La fecha del depósito no es la de carga —una boleta del viernes se
            // registra el lunes— pero tampoco puede ser futura.
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
            'nro_transaccion.unique' => 'Ese número de transacción ya fue registrado en otro pago.',
            'monto.required' => 'Indique el monto del depósito.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.min' => 'El monto del depósito debe ser mayor a cero.',
            'comprobante.required' => 'Adjunte la boleta del depósito.',
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

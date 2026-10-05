<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El pago que se carga en SIREB: lo único que SIREB acepta es el N° y el banco.
 * Los largos son los de su API.
 */
class CargarPagoRequest extends FormRequest
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
            'numero_transaccion' => ['required', 'string', 'max:50'],
            'banco' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_transaccion.required' => 'Indique el N° de transacción del depósito.',
            'numero_transaccion.max' => 'El N° de transacción no puede pasar de 50 caracteres.',
            'banco.required' => 'Indique el banco donde se pagó.',
            'banco.max' => 'El banco no puede pasar de 100 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_transaccion' => trim((string) $this->input('numero_transaccion')),
            'banco' => trim((string) $this->input('banco')),
        ]);
    }
}

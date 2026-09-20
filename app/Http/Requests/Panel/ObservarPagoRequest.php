<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas para OBSERVAR un depósito.
 */
class ObservarPagoRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Escriba qué no cuadra con esta boleta, para que ventanilla sepa qué corregir.',
            'motivo.min' => 'La observación tiene que explicar algo: escriba al menos 10 caracteres.',
            'motivo.max' => 'La observación no puede pasar de 500 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
    }
}

<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas para RECHAZAR una guía presentada a revisión.
 */
class RechazarGuiaRequest extends FormRequest
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
            'motivo.required' => 'Escriba por qué se rechaza, para que ventanilla sepa qué corregir.',
            'motivo.min' => 'El motivo tiene que explicar algo: escriba al menos 10 caracteres.',
            'motivo.max' => 'El motivo no puede pasar de 500 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
    }
}

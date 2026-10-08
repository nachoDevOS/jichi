<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El motivo obligatorio de eliminar, revocar o reponer cualquier trámite: queda
 * en la auditoría y es lo que explica después el hueco en la serie.
 */
class MotivoRequest extends FormRequest
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
            'motivo.required' => 'Escriba el motivo.',
            'motivo.min' => 'El motivo tiene que explicar la decisión: escriba al menos 10 caracteres.',
            'motivo.max' => 'El motivo no puede pasar de 500 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
    }
}

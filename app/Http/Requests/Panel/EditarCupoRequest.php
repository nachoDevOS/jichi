<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Corregir una autorización: solo la embarcación. El tramo, los kilos y el monto
 * quedan como se otorgaron; para otro tramo se elimina y se otorga de nuevo.
 */
class EditarCupoRequest extends FormRequest
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
            'tipo_embarcacion' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_embarcacion.required' => 'Indique el tipo de embarcación: va impreso en la autorización.',
            'tipo_embarcacion.max' => 'El tipo de embarcación no puede pasar de 120 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Sin espacios de más: con el espacio, el `required` daría por bueno un campo en blanco.
        $this->merge(['tipo_embarcacion' => trim((string) $this->input('tipo_embarcacion'))]);
    }
}

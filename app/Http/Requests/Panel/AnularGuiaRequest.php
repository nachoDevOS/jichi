<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas para anular una guía.
 *
 * ----------------------------------------------------------------------------
 *  UN SOLO CAMPO, Y ES EL MOTIVO
 * ----------------------------------------------------------------------------
 *
 * El código sale de un talonario de papel que puede estar circulando dentro de
 * un camión. Anular quema ese número para siempre —no se desanula— y deja un
 * hueco en la serie que alguien va a tener que explicar dentro de seis meses.
 *
 * El mínimo de 10 caracteres está para que no se resuelva con «ok». No
 * garantiza que el motivo sirva, pero sí que alguien haya tenido que escribir
 * una frase.
 */
class AnularGuiaRequest extends FormRequest
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
            'motivo.required' => 'Escriba el motivo de la anulación.',
            'motivo.min' => 'El motivo tiene que explicar la decisión: escriba al menos 10 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
    }
}

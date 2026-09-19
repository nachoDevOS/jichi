<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas para revocar una credencial.
 *
 * ----------------------------------------------------------------------------
 *  UN SOLO CAMPO, Y ES EL MOTIVO
 * ----------------------------------------------------------------------------
 *
 * Revocar es una SANCIÓN y no se revierte: el plástico queda en la calle sin
 * valer, y la verificación pública va a decir «REVOCADO» a quien lo consulte.
 * Sin un motivo escrito, dentro de seis meses nadie puede explicar por qué esa
 * persona perdió su credencial.
 *
 * El mínimo de 10 caracteres está para que no se resuelva con «ok». No
 * garantiza que el motivo sirva, pero sí que alguien haya tenido que escribir
 * una frase.
 */
class RevocarCarnetRequest extends FormRequest
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
            'motivo.required' => 'Escriba el motivo de la revocación.',
            'motivo.min' => 'El motivo tiene que explicar la decisión: escriba al menos 10 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
    }
}

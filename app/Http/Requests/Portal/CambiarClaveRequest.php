<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * La clave que elige el beneficiario. Pide la actual aunque sea la temporal:
 * una sesión olvidada abierta no alcanza para cambiarla.
 */
class CambiarClaveRequest extends FormRequest
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
            'actual' => ['required', 'string', 'current_password:web'],
            // Solo letras y números: fácil de escribir en cualquier celular (08/10/2026).
            'password' => ['required', 'string', 'confirmed', 'different:actual', 'regex:/^[\pL\pN]+$/u', Password::min(8)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'actual.required' => 'Ingrese la contraseña que tiene ahora.',
            'actual.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'Ingrese la contraseña nueva.',
            'password.confirmed' => 'La confirmación no coincide con la contraseña nueva.',
            'password.different' => 'La contraseña nueva tiene que ser distinta de la actual.',
            'password.min' => 'La contraseña nueva tiene que tener al menos 8 caracteres.',
            'password.letters' => 'La contraseña nueva tiene que tener al menos una letra.',
            'password.numbers' => 'La contraseña nueva tiene que tener al menos un número.',
            'password.regex' => 'Use solo letras y números, sin signos ni espacios.',
        ];
    }
}

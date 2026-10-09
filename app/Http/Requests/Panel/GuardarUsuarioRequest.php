<?php

namespace App\Http\Requests\Panel;

use App\Enums\RolSistema;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Alta y edición de un funcionario desde Seguridad › Usuarios. Con Ibare encendido
 * entra por su `mamore_id`; correo y clave sirven solo al administrador (emergencia).
 */
class GuardarUsuarioRequest extends FormRequest
{
    /** El permiso ya lo revisa el middleware de la ruta. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['nombre', 'email', 'mamore_id'])
            ->mapWithKeys(fn (string $c): array => [$c => trim((string) $this->input($c)) ?: null])
            ->all());
    }

    /** Sin Ibare todos entran por correo; con Ibare, solo el administrador. */
    public function llevaClave(): bool
    {
        return ! config('jichi.ibare.activo') || $this->input('rol') === RolSistema::Administrador->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ibare = (bool) config('jichi.ibare.activo');
        $sinClave = ! $this->llevaClave();
        /** @var User|null $usuario */
        $usuario = $this->route('usuario');
        // Al editar, la clave en blanco deja la que tiene; se exige solo si todavía no tenía usuario.
        $pideClave = $usuario === null || blank($usuario->email);

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'mamore_id' => [Rule::requiredIf($ibare), 'nullable', 'string', 'max:50', Rule::unique('users', 'mamore_id')->ignore($usuario)],
            'rol' => ['required', 'string', Rule::exists('roles', 'name')],
            'email' => [Rule::excludeIf($sinClave), Rule::requiredIf(! $ibare), 'required_with:password', 'nullable', 'string', 'max:255', 'regex:/^\S+$/',
                Rule::unique('users', 'email')->ignore($usuario)],
            // Van juntos: un usuario sin clave no abre nada.
            'password' => [Rule::excludeIf($sinClave), Rule::requiredIf(fn (): bool => $pideClave && (! $ibare || filled($this->input('email')))),
                'nullable', 'confirmed', Password::min(8)],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Indique el nombre del funcionario.',
            'mamore_id.required' => 'Indique el id de funcionario de Ibare.',
            'mamore_id.unique' => 'Ese id de Ibare ya está vinculado a otro usuario.',
            'rol.required' => 'Elija un rol.',
            'rol.exists' => 'Ese rol no existe.',
            'email.required' => 'Indique el correo o usuario: sin Ibare se entra con él y la clave.',
            'email.required_with' => 'Con clave hace falta el correo o usuario: es con lo que se entra.',
            'email.regex' => 'El correo o usuario no puede llevar espacios.',
            'email.unique' => 'Ya hay un usuario con ese correo o usuario.',
            'password.required' => 'Indique una clave.',
            'password.confirmed' => 'Las dos claves no coinciden.',
            'password.min' => 'La clave tiene que tener al menos 8 caracteres.',
        ];
    }
}

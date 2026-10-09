<?php

namespace App\Http\Requests\Auth;

use App\Models\Acceso;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Sin regla `email`: el acceso de emergencia admite un usuario sin @ (sedag.encargado).
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Ingrese su correo o usuario.',
            'password.required' => 'Ingrese su contraseña.',
        ];
    }

    /**
     * Autentica y deja registro del intento en la bitácora de accesos.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credenciales = [
            ...$this->only('email', 'password'),
            // Un usuario dado de baja no puede iniciar sesión aunque su
            // contraseña siga siendo válida.
            'activo' => true,
        ];

        if (! Auth::attempt($credenciales, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            $this->registrarAcceso('fallido');

            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no son correctos. Revíselos y vuelva a intentar.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        if ($this->user()->esBeneficiario()) {
            $userId = $this->user()->id;
            Auth::guard('web')->logout();

            $this->registrarAcceso('fallido', $userId);

            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no son correctos. Revíselos y vuelva a intentar.',
            ]);
        }

        // Con Ibare encendido, el correo es solo el acceso de emergencia del administrador.
        if (config('jichi.ibare.activo') && ! $this->user()->esAdministrador()) {
            $userId = $this->user()->id;
            Auth::guard('web')->logout();

            $this->registrarAcceso('fallido', $userId);

            throw ValidationException::withMessages([
                'email' => 'Esta entrada es solo para el administrador. Use el botón «Ingresar con mi cuenta de la Gobernación».',
            ]);
        }

        // Sin rol, o con un rol sin pantallas: entraría a un 403 sin explicación.
        if ($this->user()->rutaInicio() === null) {
            $userId = $this->user()->id;
            Auth::guard('web')->logout();

            $this->registrarAcceso('fallido', $userId);

            throw ValidationException::withMessages([
                'email' => 'Ya tiene una cuenta, pero todavía no le asignaron qué tareas puede hacer. Pida al encargado de Jichi que le asigne un rol.',
            ]);
        }

        $this->user()->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();

        $this->registrarAcceso('login', $this->user()->id);
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $this->registrarAcceso('bloqueado');

        $segundos = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Hubo demasiados intentos seguidos. Por seguridad, espere {$segundos} segundos y vuelva a intentar.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    private function registrarAcceso(string $evento, ?int $userId = null): void
    {
        Acceso::create([
            'user_id' => $userId,
            'email' => $this->string('email'),
            'evento' => $evento,
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'session_id' => $this->hasSession() ? $this->session()->getId() : null,
        ]);
    }
}

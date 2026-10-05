<?php

namespace App\Http\Requests\Portal;

use App\Models\Acceso;
use App\Models\Beneficiario;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Ingreso al portal con la C.I. del beneficiario.
 *
 * La C.I. es fácil de adivinar, así que el mensaje de error es el MISMO exista o
 * no la cuenta, y hay tope de 5 intentos por C.I. e IP (más el throttle de la ruta).
 */
class IngresarRequest extends FormRequest
{
    private const ERROR = 'La cédula o la contraseña no son correctas, o su acceso no está habilitado.';

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
            'ci' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ci.required' => 'Ingrese su número de cédula de identidad.',
            'password.required' => 'Ingrese su contraseña.',
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $cuenta = $this->cuenta();

        if ($cuenta === null || ! Hash::check((string) $this->input('password'), $cuenta->password)) {
            RateLimiter::hit($this->throttleKey());
            $this->registrarAcceso('fallido', $cuenta?->id);

            throw ValidationException::withMessages(['ci' => self::ERROR]);
        }

        RateLimiter::clear($this->throttleKey());

        Auth::guard('web')->login($cuenta);
        $cuenta->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();
        $this->registrarAcceso('login', $cuenta->id);
    }

    /** La cuenta activa del beneficiario con esa C.I., o null. */
    private function cuenta(): ?User
    {
        // Primero lo tipeado tal cual (la C.I. se guarda así); si no, el número sin complemento ni expedido.
        $candidatas = array_values(array_unique([$this->tipeado(), $this->ci()]));
        $beneficiario = Beneficiario::query()->whereIn('ci', $candidatas)->get()
            ->sortBy(fn (Beneficiario $b) => array_search($b->ci, $candidatas, true))
            ->first();

        return $beneficiario?->cuenta()->where('activo', true)->first();
    }

    /** Lo tipeado, sin puntos de miles: «1.234.567» es 1234567. */
    private function tipeado(): string
    {
        return strtoupper(str_replace('.', '', trim((string) $this->input('ci'))));
    }

    /** El número solo: «1234567-1A BN» o «1234567 BN» dan 1234567. */
    private function ci(): string
    {
        return preg_split('/[\s\-]+/', $this->tipeado())[0];
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));
        $this->registrarAcceso('bloqueado');

        $segundos = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'ci' => "Demasiados intentos. Vuelva a intentar en {$segundos} segundos.",
        ]);
    }

    private function throttleKey(): string
    {
        return 'portal|'.$this->ci().'|'.$this->ip();
    }

    // `accesos.email` guarda lo que se tipeó para entrar: acá, la C.I.
    private function registrarAcceso(string $evento, ?int $userId = null): void
    {
        Acceso::create([
            'user_id' => $userId,
            'email' => 'CI '.$this->ci(),
            'evento' => $evento,
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'session_id' => $this->hasSession() ? $this->session()->getId() : null,
        ]);
    }
}

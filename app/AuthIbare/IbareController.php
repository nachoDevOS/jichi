<?php

namespace App\AuthIbare;

use App\Http\Controllers\Controller;
use App\Models\Acceso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class IbareController extends Controller
{
    public function __construct(private IbareService $ibare) {}

    /** GET /auth/ibare — manda al navegador al login de Ibare. */
    public function redirigir(Request $request): Response
    {
        abort_unless($this->ibare->activo(), 404);

        // Inertia::location y no redirect(): si llega por una visita de Inertia, un 302 a otro dominio no se sigue.
        return Inertia::location($this->ibare->urlAutorizacion($request));
    }

    /** GET /auth/ibare/callback — Ibare vuelve acá con el código. */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->ibare->activo(), 404);

        try {
            $usuario = $this->ibare->usuarioDesdeCallback($request);
        } catch (IbareException $e) {
            $this->registrarAcceso($request, 'fallido', null, 'ibare');

            return redirect()->route('login')->withErrors(['ibare' => $e->getMessage()]);
        }

        Auth::login($usuario);
        $request->session()->regenerate();

        $usuario->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();
        $this->registrarAcceso($request, 'login', $usuario->id, $usuario->email);

        return redirect()->intended(route('dashboard'))
            ->with('exito', 'Bienvenido a Jichi, '.$usuario->name.'.');
    }

    private function registrarAcceso(Request $request, string $evento, ?int $userId, ?string $email): void
    {
        Acceso::create([
            'user_id' => $userId,
            'email' => $email,
            'evento' => $evento,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => $request->session()->getId(),
        ]);
    }
}

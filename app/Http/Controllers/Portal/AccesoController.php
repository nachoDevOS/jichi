<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\IngresarRequest;
use App\Models\Acceso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entrar y salir del portal. Mismo guard `web` que el panel: lo que separa a
 * uno del otro es `beneficiario_id` y los middleware `funcionario` / `beneficiario`.
 */
class AccesoController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('portal/ingresar');
    }

    public function store(IngresarRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->route('portal.inicio');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Acceso::create([
            'user_id' => $request->user()?->id,
            'email' => 'CI '.$request->user()?->ci,
            'evento' => 'logout',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => $request->session()->getId(),
        ]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.ingresar')->with('info', 'Salió de su cuenta.');
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * El portal /mi-cuenta es solo de las cuentas con beneficiario. Además corta la
 * sesión de una cuenta desactivada y obliga a cambiar la clave temporal.
 */
class SoloBeneficiario
{
    /** Las únicas rutas abiertas mientras la clave siga siendo la temporal. */
    private const CON_CLAVE_TEMPORAL = ['portal.clave.edit', 'portal.clave.update', 'portal.salir'];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario?->esBeneficiario()) {
            return redirect()->route($usuario?->rutaInicio() ?? 'dashboard');
        }

        // Desactivada en ventanilla, o la persona dada de baja del padrón.
        if (! $usuario->activo || $usuario->beneficiario === null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('portal.ingresar')
                ->with('error', 'Su acceso al portal no está habilitado. Consulte en ventanilla.');
        }

        if ($usuario->debe_cambiar_password && ! $request->routeIs(...self::CON_CLAVE_TEMPORAL)) {
            return redirect()->route('portal.clave.edit');
        }

        return $next($request);
    }
}

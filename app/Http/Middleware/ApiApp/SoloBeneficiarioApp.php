<?php

namespace App\Http\Middleware\ApiApp;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * SoloBeneficiario para la app: las mismas tres reglas, pero contesta JSON con
 * un `codigo` que la app entiende en vez de redirigir.
 */
class SoloBeneficiarioApp
{
    /** Las únicas rutas abiertas mientras la clave siga siendo la temporal. */
    private const CON_CLAVE_TEMPORAL = ['api.portal.yo', 'api.portal.clave.update', 'api.portal.sesion.destroy'];

    /** Un token vence tras estos días sin uso (Sanctum controla `expires_at`). */
    public const DIAS_TOKEN = 90;

    /** Se renueva cuando le quedan menos días que esto: una escritura por semana, no por pedido. */
    private const RENOVAR_DEBAJO_DE = 83;

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario?->esBeneficiario()) {
            return response()->json(['message' => 'Solo para cuentas del portal.', 'codigo' => 'no_beneficiario'], 403);
        }

        // Desactivada en ventanilla, o la persona dada de baja: el token muere acá.
        if (! $usuario->activo || $usuario->beneficiario === null) {
            $usuario->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Su acceso al portal no está habilitado. Consulte en ventanilla.',
                'codigo' => 'acceso_deshabilitado',
            ], 401);
        }

        $this->renovar($usuario->currentAccessToken());

        if ($usuario->debe_cambiar_password && ! $request->routeIs(...self::CON_CLAVE_TEMPORAL)) {
            return response()->json([
                'message' => 'Antes de seguir, cambie la contraseña temporal que le dieron en ventanilla.',
                'codigo' => 'clave_temporal',
            ], 403);
        }

        return $next($request);
    }

    /** Vencimiento deslizante. Un token viejo sin `expires_at` (antes del 25/11/2026) también lo recibe. */
    private function renovar(mixed $token): void
    {
        if (! $token instanceof PersonalAccessToken) {
            return;
        }

        if ($token->expires_at === null || $token->expires_at->lt(now()->addDays(self::RENOVAR_DEBAJO_DE))) {
            $token->forceFill(['expires_at' => now()->addDays(self::DIAS_TOKEN)])->save();
        }
    }
}

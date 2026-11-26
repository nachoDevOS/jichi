<?php

namespace App\Http\Controllers\ApiApp;

use App\Http\Controllers\Controller;
use App\Support\ApiApp\PantallaPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * «Mis pagos» en la app (25/11/2026, pedido del responsable): sus recibos de una
 * gestión, lo mismo que «Mis pagos» del portal web (PantallaPortal::pagos). Cada
 * recibo trae su código y la dirección de su QR de verificación, con la que la app
 * dibuja el recibo digital. No lleva `descargar`: esa ruta pide sesión de navegador.
 */
class PagosController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Como en la web: solo una gestión que exista; si no, la actual.
        $gestion = filter_var($request->query('gestion'), FILTER_VALIDATE_INT);
        $pagos = PantallaPortal::pagos($request->user()->beneficiario, $gestion === false ? null : $gestion);

        return response()->json(['pagos' => [
            ...$pagos,
            'recibos' => array_map(
                fn (array $recibo): array => collect($recibo)->except('descargar')->all(),
                $pagos['recibos'],
            ),
        ]]);
    }
}

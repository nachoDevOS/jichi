<?php

namespace App\Http\Controllers\ApiApp;

use App\Http\Controllers\Controller;
use App\Services\ApiApp\AnularPedidoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * «Anular pedido» de la app (27/11/2026). La regla está en AnularPedidoService, la
 * misma que usa el portal web (Portal\AnularPedidoController):
 *
 * - 200 `mensaje`: anulado.
 * - 404: ajeno, inexistente o que no es faena ni guía.
 * - 422 `errors.tramite`: ya aprobado o revocado, o registrado en ventanilla.
 * - 409 `ya_pagado`: con un pago cargado en SIREB (y `aprobado` si ya está validado).
 */
class AnularTramiteController extends Controller
{
    public function __invoke(Request $request, string $codigo, AnularPedidoService $servicio): JsonResponse
    {
        ['resultado' => $resultado, 'mensaje' => $mensaje, 'aprobado' => $aprobado]
            = $servicio->anular($request->user()->beneficiario_id, $codigo);

        return match ($resultado) {
            'anulado' => response()->json(['mensaje' => $mensaje]),
            'no_encontrado' => response()->json(['message' => $mensaje], 404),
            'no_se_puede' => response()->json(['message' => $mensaje, 'errors' => ['tramite' => [$mensaje]]], 422),
            'ya_pagado' => response()->json(['message' => $mensaje, 'codigo' => 'ya_pagado', 'aprobado' => $aprobado], 409),
        };
    }
}

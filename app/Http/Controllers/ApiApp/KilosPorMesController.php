<?php

namespace App\Http\Controllers\ApiApp;

use App\Http\Controllers\Controller;
use App\Support\ApiApp\KilosPorMes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * «Kilos por mes» (27/11/2026): lo vendido (pescador) y lo transportado
 * (comercializador) por mes de una gestión, para el gráfico del inicio. Ver KilosPorMes.
 */
class KilosPorMesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $gestion = $request->validate([
            'gestion' => ['nullable', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
        ])['gestion'] ?? now()->year;

        return response()->json([
            'kilos' => KilosPorMes::de($request->user()->beneficiario, (int) $gestion),
        ]);
    }
}

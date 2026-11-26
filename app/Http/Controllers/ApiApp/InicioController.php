<?php

namespace App\Http\Controllers\ApiApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiApp\DocumentoResource;
use App\Jobs\VerificarPagoJob;
use App\Support\ApiApp\PantallaPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * El inicio de El Pescador en la app (vuelve el 25/11/2026, pedido del responsable):
 * lo mismo que el inicio del portal web (PantallaPortal::inicio): lo vigente hoy, lo
 * abierto («Qué hacer ahora», «Por pagar»), la deuda y qué puede tramitar.
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $beneficiario = $request->user()->beneficiario;
        // Como la web: al entrar se consultan sus pagos en SIREB, en segundo plano.
        VerificarPagoJob::encolarDe($beneficiario);
        $inicio = PantallaPortal::inicio($beneficiario);
        $pagos = PantallaPortal::pagos($beneficiario, null);

        return response()->json(['inicio' => [
            'nombre' => $inicio['nombre'],
            'deuda' => $inicio['deuda'],
            'vigentes' => DocumentoResource::collection($inicio['vigentes']),
            'abiertos' => DocumentoResource::collection($inicio['abiertos']),
            // Ya trae su `canal` (TramitesDisponibles::EN_LINEA): no lleva nada de la web.
            'tramites_disponibles' => $inicio['tramites_disponibles'],
            // La entrada a «Mis pagos» del inicio: lo pagado en la gestión actual.
            'pagado' => [
                'gestion' => $pagos['gestion'],
                'total' => $pagos['total_pagado'],
                'recibos' => count($pagos['recibos']),
            ],
        ]]);
    }
}

<?php

namespace App\Http\Controllers\ApiApp;

use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Services\ConfirmarPagoService;
use App\Services\EmitirFaenaService;
use App\Services\EmitirGuiaService;
use App\Sireb\SirebException;
use App\Support\ApiApp\DocumentoDelPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * «Anular pedido» (27/11/2026, decisión del responsable): el titular anula su faena o
 * guía **pendiente** que pidió **en línea** (app o portal web), para poder pedir otra.
 * Es el mismo «Eliminar» del panel (EmitirFaenaService / EmitirGuiaService::eliminar):
 * primero anula el cobro en SIREB (si ya se pagó, frena) y la guía devuelve sus kilos
 * al pescador. Queda en la auditoría con el motivo fijo.
 *
 * - Ajeno, inexistente o que no es faena ni guía: 404, sin distinguir.
 * - Ya aprobado o revocado, o registrado en ventanilla: 422 con el motivo.
 * - Ya pagado en SIREB: 409 `ya_pagado` (y queda aprobado si el pago está validado).
 */
class AnularTramiteController extends Controller
{
    public const MOTIVO = 'Anulado por el titular desde la app';

    public function __invoke(
        Request $request,
        string $codigo,
        EmitirFaenaService $faenas,
        EmitirGuiaService $guias,
        ConfirmarPagoService $pagos,
    ): JsonResponse {
        $tramite = DocumentoDelPortal::buscar($codigo, $request->user()->beneficiario_id);

        abort_unless(
            $tramite instanceof PermisoFaena || $tramite instanceof GuiaMovimiento,
            404,
            'No encontramos ese pedido entre los suyos.',
        );

        $cual = $tramite instanceof PermisoFaena
            ? "el permiso de faena N° {$tramite->numero_legible}"
            : "la guía de transporte N° {$tramite->numero_legible}";

        if (! $tramite->estado->permiteEliminacion()) {
            return $this->noSe("Ya no se puede anular {$cual}: está ".mb_strtolower($tramite->estado->etiqueta()).'.');
        }

        if ($tramite->origen_tramite?->esEnLinea() !== true) {
            return $this->noSe("Anular {$cual} se hace en ventanilla de la Unidad de Pesca: ahí se registró.");
        }

        try {
            $tramite instanceof PermisoFaena
                ? $faenas->eliminar($tramite, self::MOTIVO)
                : $guias->eliminar($tramite, self::MOTIVO);
        } catch (PermisoOperativoException $e) {
            return $this->noSe($e->getMessage());
        } catch (SirebException $e) {
            if (! $e->frenaPorPago()) {
                throw $e;
            }

            // Ya pagó: no se anula. Si el pago está validado, queda aprobado ahora.
            $resultado = $pagos->alNoPoderEliminar($tramite);

            return response()->json([
                'message' => $resultado['aprobado']
                    ? 'Ya pagaste: no se anula. Tu trámite quedó aprobado.'
                    : 'Ya pagaste: no se anula. Cuando Recaudaciones lo confirme, queda aprobado solo.',
                'codigo' => 'ya_pagado',
                'aprobado' => $resultado['aprobado'],
            ], 409);
        }

        return response()->json([
            'mensaje' => 'Anulaste '.$cual.'. No se cobra'
                .($tramite instanceof GuiaMovimiento ? ' y los kilos volvieron al pescador' : '')
                .'. Ya podés pedir otro.',
        ]);
    }

    private function noSe(string $motivo): JsonResponse
    {
        return response()->json(['message' => $motivo, 'errors' => ['tramite' => [$motivo]]], 422);
    }
}

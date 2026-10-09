<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\ConfirmarPagoService;
use App\Support\DocumentoDelPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Antes de mostrar el QR, pregunta a SIREB si el cobro sigue pendiente y sin pago
 * cargado. El QR es de muestra: el pago real sigue en Recaudaciones.
 */
class PagoQrController extends Controller
{
    public function __invoke(Request $request, string $codigo, ConfirmarPagoService $pagos): JsonResponse
    {
        $documento = DocumentoDelPortal::buscar($codigo, $request->user()->beneficiario_id);

        abort_if($documento === null, 404);

        $respuesta = $pagos->puedePagarPorQr($documento);

        if (! $respuesta['puede']) {
            return response()->json($respuesta);
        }

        // El trámite ya se comprobó suyo: el titular es la persona con sesión.
        $titular = $request->user()->beneficiario;

        return response()->json([
            ...$respuesta,
            'monto' => $documento->porPagar(),
            'codigo_pago' => $documento->sireb_codigo_publico,
            'titular' => $titular->nombreCompleto,
            'documento' => $titular->documento_identidad,
        ]);
    }
}

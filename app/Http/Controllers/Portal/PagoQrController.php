<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\CobroQrService;
use App\Support\ApiApp\DocumentoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El QR de pago del titular: lo genera SIREB (real o de prueba) y se muestra tal cual.
 * Sin pasarela en ese entorno, se paga por depósito. Ver docs/modulos/PAGO-QR.md.
 */
class PagoQrController extends Controller
{
    public function __invoke(Request $request, string $codigo, CobroQrService $cobro): JsonResponse
    {
        $documento = $this->documento($request, $codigo);
        $respuesta = $cobro->qr($documento);

        // El trámite ya se comprobó suyo: el titular es la persona con sesión.
        $titular = $request->user()->beneficiario;

        return response()->json([
            ...$respuesta,
            'monto' => $respuesta['qr']['monto'] ?? $documento->porPagar(),
            'codigo_pago' => $documento->sireb_codigo_publico,
            'titular' => $titular->nombreCompleto,
            'documento' => $titular->documento_identidad,
        ]);
    }

    /** Con el QR abierto, la ventana pregunta si ya se pagó; si sí, queda aprobado. */
    public function estado(Request $request, string $codigo, CobroQrService $cobro): JsonResponse
    {
        return response()->json($cobro->estado($this->documento($request, $codigo)));
    }

    /** Paga el QR de PRUEBA: solo si SIREB lo dio por `simulado` (lo controla el servicio). */
    public function simular(Request $request, string $codigo, CobroQrService $cobro): JsonResponse
    {
        return response()->json($cobro->simularPago($this->documento($request, $codigo)));
    }

    private function documento(Request $request, string $codigo): Model
    {
        $documento = DocumentoDelPortal::buscar($codigo, $request->user()->beneficiario_id);

        abort_if($documento === null, 404);

        return $documento;
    }
}

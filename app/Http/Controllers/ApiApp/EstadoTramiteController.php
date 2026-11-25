<?php

namespace App\Http\Controllers\ApiApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiApp\DocumentoResource;
use App\Services\CobroQrService;
use App\Support\ApiApp\DocumentoDelPortal;
use App\Support\ApiApp\ResumenPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * La app paga el QR directo en SIREB (API pública) y después pregunta acá: se consulta
 * SIREB en el momento y, si está pagado, el trámite se aprueba. Nunca aprueba con lo que
 * diga el celular. Contrato en docs/api/portal.md.
 */
class EstadoTramiteController extends Controller
{
    public function __invoke(Request $request, string $codigo, CobroQrService $cobro): JsonResponse
    {
        // Lo ajeno y lo inexistente, el mismo 404: no confirma que el código exista.
        $documento = DocumentoDelPortal::buscar($codigo, $request->user()->beneficiario_id);

        if ($documento === null) {
            return response()->json(['message' => 'No encontramos ese trámite entre los suyos.'], 404);
        }

        // Como la ventana del QR en la web: a SIREB, como mucho cada 15 s por trámite.
        $estado = $cobro->estado($documento);

        return response()->json([
            'pagado' => $estado['pagado'],
            'mensaje' => $estado['mensaje'],
            'tramite' => new DocumentoResource(ResumenPortal::de($documento->refresh())),
        ]);
    }
}

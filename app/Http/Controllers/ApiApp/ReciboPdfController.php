<?php

namespace App\Http\Controllers\ApiApp;

use App\Http\Controllers\Controller;
use App\Services\DocumentoEmitidoService;
use App\Support\ApiApp\DocumentoDelPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * «Descargar PDF» del recibo digital (26/11/2026): el PDF oficial guardado al emitirse
 * (en s3), el mismo que imprime ventanilla. No viajan los bytes: devuelve un enlace
 * firmado que vence a los 5 minutos y la app lo abre fuera, sin el token. Solo un
 * recibo suyo; lo ajeno da 404 igual que lo inexistente.
 */
class ReciboPdfController extends Controller
{
    public function __invoke(Request $request, string $codigo, DocumentoEmitidoService $documentos): JsonResponse
    {
        $recibo = DocumentoDelPortal::recibo($codigo, $request->user()->beneficiario_id);

        abort_if($recibo === null, 404, 'No encontramos ese recibo entre los suyos.');

        return response()->json(['pdf' => $documentos->enlaceDescarga($recibo)]);
    }
}

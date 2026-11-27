<?php

namespace App\Http\Controllers\ApiApp;

use App\Http\Controllers\Controller;
use App\Models\Carnet;
use App\Services\DocumentoEmitidoService;
use App\Support\ApiApp\DocumentoDelPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * «Descargar» de «Vigente hoy» (27/11/2026): el PDF oficial guardado al emitirse de
 * su autorización, faena o guía, como el recibo (ReciboPdfController): un enlace
 * firmado que vence a los 5 minutos, que la app abre fuera, sin el token.
 *
 * Las reglas son las de «Descargar» del portal web (Portal\DescargarController):
 * solo lo suyo, solo lo vigente HOY y **nunca el carnet** (perdido, se revoca y se
 * repone en ventanilla). Lo ajeno, vencido o inexistente da 404, sin distinguir.
 */
class PapelPdfController extends Controller
{
    public function __invoke(Request $request, string $codigo, DocumentoEmitidoService $documentos): JsonResponse
    {
        $papel = DocumentoDelPortal::buscar($codigo, $request->user()->beneficiario_id);

        abort_unless(
            $papel !== null && ! $papel instanceof Carnet && $papel->estaVigente(),
            404,
            'No encontramos ese papel vigente entre los suyos.',
        );

        return response()->json(['pdf' => $documentos->enlaceDescarga($papel)]);
    }
}

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
 * Lo que el titular tiene por pagar, para la app: lo mismo que «En curso» del portal
 * web (PantallaPortal). Cada trámite trae su código para pedir el QR de pago.
 */
class EnCursoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $beneficiario = $request->user()->beneficiario;
        // Como la web: al entrar se consultan sus pagos en SIREB, en segundo plano.
        VerificarPagoJob::encolarDe($beneficiario);

        return response()->json(['tramites' => DocumentoResource::collection(PantallaPortal::enCurso($beneficiario))]);
    }
}

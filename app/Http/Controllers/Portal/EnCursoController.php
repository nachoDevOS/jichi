<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Jobs\VerificarPagoJob;
use App\Support\ResumenPortal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los trámites abiertos: pendientes de pago en SIREB. Cada uno dice qué le
 * falta y su código de pago, ya resuelto acá.
 */
class EnCursoController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Mientras mira, sus pagos se consultan en SIREB en segundo plano; el refresco de la página trae el resultado.
        VerificarPagoJob::encolarDe($request->user()->beneficiario);
        $abiertos = ResumenPortal::abiertos($request->user()->beneficiario);

        return Inertia::render('portal/en-curso', [
            'tramites' => $abiertos->all(),
        ]);
    }
}

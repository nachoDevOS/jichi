<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\ApiApp\PantallaPortal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sus recibos por gestión: el informe de aportes que el Art. 13 del reglamento
 * del SEDAG le reconoce. El pago se hace en SIREB; cada recibo copia su boleta.
 */
class PagosController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // La gestión llega por la URL: PantallaPortal acepta solo una que exista.
        $gestion = $request->filled('gestion') ? (int) $request->query('gestion') : null;

        return Inertia::render('portal/pagos', PantallaPortal::pagos($request->user()->beneficiario, $gestion));
    }
}

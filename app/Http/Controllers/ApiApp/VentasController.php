<?php

namespace App\Http\Controllers\ApiApp;

use App\Http\Controllers\Controller;
use App\Models\AprovechamientoPesq;
use App\Support\ApiApp\DocumentoDelPortal;
use App\Support\ApiApp\VentasDelCupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * «Mis ventas» de su Autorización de Pesca (27/11/2026): vendido, reservado y libre,
 * y a quién le vendió, como la ficha del panel (VentasDelCupo). Solo una autorización
 * suya: lo ajeno, otro papel o lo inexistente da 404, sin distinguir.
 */
class VentasController extends Controller
{
    public function __invoke(Request $request, string $codigo): JsonResponse
    {
        $cupo = DocumentoDelPortal::buscar($codigo, $request->user()->beneficiario_id);

        abort_unless($cupo instanceof AprovechamientoPesq, 404, 'No encontramos esa autorización entre las suyas.');

        return response()->json(['ventas' => VentasDelCupo::de($cupo)]);
    }
}

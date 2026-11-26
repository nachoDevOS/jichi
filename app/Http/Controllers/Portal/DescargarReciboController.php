<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\ReciboController;
use App\Support\ApiApp\DocumentoDelPortal;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descargar un recibo propio: el mismo del panel, como archivo. Prueba el pago
 * —el aporte del Art. 13— y no habilita nada, así que se baja siempre. Por el
 * código público, como el resto del portal; lo ajeno da 404.
 */
class DescargarReciboController extends Controller
{
    public function __invoke(Request $request, string $codigo): Response
    {
        // La misma búsqueda que la descarga de la app (DocumentoDelPortal::recibo).
        $recibo = DocumentoDelPortal::recibo($codigo, $request->user()->beneficiario_id);

        abort_if($recibo === null, 404);

        // El panel lo manda `inline` para imprimirlo; acá se baja, con el mismo nombre de archivo.
        return app(ReciboController::class)->imprimir($recibo, descarga: true);
    }
}

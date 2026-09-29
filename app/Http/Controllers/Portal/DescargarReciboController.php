<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\ReciboController;
use App\Models\Carnet;
use App\Models\Codigo;
use App\Models\Recibo;
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
        $recibo = Codigo::query()
            ->where('codigo', Carnet::normalizarCodigo($codigo))
            ->where('codigable_type', (new Recibo)->getMorphClass())
            ->first()
            ?->codigable;

        abort_unless(
            $recibo instanceof Recibo && ! $recibo->trashed() && $recibo->beneficiario_id === $request->user()->beneficiario_id,
            404,
        );

        $pdf = app(ReciboController::class)->imprimir($recibo);

        // El panel lo manda `inline` para imprimirlo; acá se baja, con el mismo nombre de archivo.
        $pdf->headers->set('Content-Disposition', str_replace('inline', 'attachment', (string) $pdf->headers->get('Content-Disposition')));

        return $pdf;
    }
}

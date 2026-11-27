<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\ApiApp\AnularPedidoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «Anular pedido» del portal web (27/11/2026): lo mismo que la app
 * (ApiApp\AnularTramiteController), con la misma regla (AnularPedidoService). El
 * beneficiario anula su faena o guía pendiente pedida en línea, desde la app o desde
 * la web: lo que importa es que la pidió él. Vuelve a «En curso» con el mensaje.
 */
class AnularPedidoController extends Controller
{
    public function __invoke(Request $request, string $codigo, AnularPedidoService $servicio): RedirectResponse
    {
        ['resultado' => $resultado, 'mensaje' => $mensaje] = $servicio->anular($request->user()->beneficiario_id, $codigo, deUsted: true);

        abort_if($resultado === 'no_encontrado', 404, $mensaje);

        return redirect()
            ->route('portal.en-curso')
            ->with($resultado === 'anulado' ? 'exito' : 'aviso', $mensaje);
    }
}

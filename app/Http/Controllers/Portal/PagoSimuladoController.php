<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Support\CodigoQr;
use App\Support\DocumentoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * El QR del «Pagar» del portal: DEMOSTRACIÓN, sin conexión con ningún banco.
 * Solo con `jichi.portal.pago_qr` encendido (por defecto, solo en local).
 *
 * Devuelve la IMAGEN, que el modal global del portal muestra con un `<img>`: el
 * concepto y el monto ya los tiene la tarjeta. No escribe nada, y el QR lleva
 * solo un texto sin valor de pago. Ver docs/modulos/PORTAL.md.
 */
class PagoSimuladoController extends Controller
{
    public function __invoke(Request $request, string $codigo): Response
    {
        abort_unless(config('jichi.portal.pago_qr'), 404);

        $documento = DocumentoDelPortal::buscar($codigo, $request->user()->beneficiario_id);

        // 404 y no 403 para lo ajeno: no confirma que el código exista.
        abort_unless($documento !== null && $documento->admitePagos() && $documento->saldoPendiente() > 0, 404);

        $texto = sprintf(
            'SIMULACION - SIN VALOR DE PAGO | SEDAG-BENI | %s | %s | %s Bs',
            $this->concepto($documento),
            $documento->codigo?->codigo,
            number_format($documento->saldoPendiente(), 2, '.', ''),
        );

        // Sin caché: el saldo cambia al depositar, y la imagen no debe quedar vieja.
        return response(CodigoQr::png($texto), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function concepto(Model $documento): string
    {
        return match (true) {
            $documento instanceof AprovechamientoPesq => 'Autorización de Pesca para Aprovechamiento Pesquero',
            $documento instanceof Carnet => 'Carnet de '.mb_strtolower($documento->tipo_actor->etiqueta()),
            $documento instanceof PermisoFaena => 'Permiso de Faena N° '.$documento->numero_legible,
            $documento instanceof GuiaMovimiento => 'Guía de Transporte N° '.$documento->numero_legible,
        };
    }
}

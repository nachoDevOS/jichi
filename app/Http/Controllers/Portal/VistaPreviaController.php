<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\AutorizacionPescaController;
use App\Http\Controllers\Panel\CarnetImpresionController;
use App\Http\Controllers\Panel\GuiaImpresionController;
use App\Http\Controllers\Panel\PermisoFaenaImpresionController;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Support\DocumentoDelPortal;
use App\Support\MarcaAgua;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Vista previa de un trámite ABIERTO (pendiente de pago): el mismo PDF
 * del panel con «NO VÁLIDO» encima de cada hoja. Lo aprobado se descarga
 * limpio; esto es para que la persona vea cómo va a quedar su papel.
 *
 * El portal lo dibuja con pdf.js, sin botones de descargar ni imprimir. Eso es
 * comodidad: lo que protege de verdad es la marca.
 */
class VistaPreviaController extends Controller
{
    public function __invoke(Request $request, string $codigo): Response
    {
        $documento = DocumentoDelPortal::buscar($codigo, $request->user()->beneficiario_id);

        abort_unless($documento !== null && $documento->estado->estaAbierto(), 404);

        $marca = MarcaAgua::noValido();

        $pdf = match (true) {
            $documento instanceof AprovechamientoPesq => app(AutorizacionPescaController::class)->documento($documento, $marca),
            $documento instanceof Carnet => app(CarnetImpresionController::class)->documento($documento, $marca),
            $documento instanceof PermisoFaena => app(PermisoFaenaImpresionController::class)->documento($documento, $marca),
            $documento instanceof GuiaMovimiento => app(GuiaImpresionController::class)->documento($documento, $marca),
        };

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vista-previa-no-valido.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

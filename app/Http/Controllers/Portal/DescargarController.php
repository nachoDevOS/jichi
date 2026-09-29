<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\AutorizacionPescaController;
use App\Http\Controllers\Panel\GuiaImpresionController;
use App\Http\Controllers\Panel\PermisoFaenaImpresionController;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Support\DocumentoDelPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descargar el PDF desde el portal: el MISMO documento del panel, sin copiar su
 * armado, pero como descarga y no para imprimir en pantalla.
 *
 * Solo lo vigente HOY, y nunca el carnet: perdido, se revoca y se repone en
 * ventanilla; bajarlo de nuevo saltearía eso. Las impresiones del panel no
 * escriben nada, así que el portal sigue siendo de solo lectura.
 */
class DescargarController extends Controller
{
    public function __invoke(Request $request, string $codigo): Response|RedirectResponse
    {
        $documento = DocumentoDelPortal::buscar($codigo, $request->user()->beneficiario_id);

        abort_unless($documento !== null && ! $documento instanceof Carnet && $documento->estaVigente(), 404);

        $pdf = match (true) {
            $documento instanceof AprovechamientoPesq => app(AutorizacionPescaController::class)->imprimir($documento),
            $documento instanceof PermisoFaena => app(PermisoFaenaImpresionController::class)->imprimir($documento),
            $documento instanceof GuiaMovimiento => app(GuiaImpresionController::class)->imprimir($documento),
        };

        // El panel lo manda `inline` para verlo; acá se baja, con el mismo nombre de archivo.
        $pdf->headers->set('Content-Disposition', str_replace('inline', 'attachment', (string) $pdf->headers->get('Content-Disposition')));

        return $pdf;
    }
}

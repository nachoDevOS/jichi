<?php

namespace App\Http\Controllers\Portal;

use App\Enums\OrigenTramite;
use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiApp\PedirGuiaRequest;
use App\Services\ApiApp\PedirGuiaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Pedir guía de transporte» en el portal (19/11/2026). Las reglas son las del panel:
 * ver PedirGuiaService. Ninguna ruta recibe un id: el carnet es el suyo y el
 * pescador se busca por el código de su autorización.
 */
class GuiaController extends Controller
{
    public function __construct(private readonly PedirGuiaService $servicio) {}

    public function create(Request $request): Response
    {
        return Inertia::render('portal/guia', $this->servicio->datos($request->user()->beneficiario));
    }

    /** El pescador que le vendió, por el código de su autorización (la página lo pide al escribirlo). */
    public function autorizacion(string $codigo): JsonResponse
    {
        $autorizacion = $this->servicio->autorizacion($codigo);

        return $autorizacion === null
            ? response()->json(['message' => PedirGuiaService::SIN_AUTORIZACION], 404)
            : response()->json(['autorizacion' => $autorizacion]);
    }

    public function store(PedirGuiaRequest $request): RedirectResponse
    {
        try {
            $guia = $this->servicio->pedir($request->user()->beneficiario, $request->validated(), OrigenTramite::PortalWeb);
        } catch (PermisoOperativoException $e) {
            return back()->withInput()->withErrors(['guia' => $e->getMessage()]);
        }

        return redirect()->route('portal.en-curso')->with('exito', sprintf(
            'Pidió la guía N° %s. Pague %s Bs con su código en Recaudaciones: al validarse el pago queda aprobada.',
            $guia->numero_legible,
            number_format((float) $guia->monto, 2, ',', '.'),
        ));
    }
}

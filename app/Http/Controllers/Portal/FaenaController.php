<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiApp\PedirFaenaRequest;
use App\Services\ApiApp\PedirFaenaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Pedir permiso de faena» en el portal (19/11/2026). Las reglas son las del panel:
 * ver PedirFaenaService. Ninguna ruta recibe un id: el carnet es el suyo.
 */
class FaenaController extends Controller
{
    public function __construct(private readonly PedirFaenaService $servicio) {}

    public function create(Request $request): Response
    {
        return Inertia::render('portal/faena', $this->servicio->datos($request->user()->beneficiario));
    }

    public function store(PedirFaenaRequest $request): RedirectResponse
    {
        try {
            $faena = $this->servicio->pedir($request->user()->beneficiario, $request->validated());
        } catch (PermisoOperativoException $e) {
            return back()->withInput()->withErrors(['faena' => $e->getMessage()]);
        }

        return redirect()->route('portal.en-curso')->with('exito', sprintf(
            'Pidió el permiso de faena N° %s. Pague %s Bs con su código en Recaudaciones: al validarse el pago queda aprobado.',
            $faena->numero_legible,
            number_format($faena->montoACobrar(), 2, ',', '.'),
        ));
    }
}

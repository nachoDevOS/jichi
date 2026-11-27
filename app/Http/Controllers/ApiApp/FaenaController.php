<?php

namespace App\Http\Controllers\ApiApp;

use App\Enums\OrigenTramite;
use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiApp\PedirFaenaRequest;
use App\Http\Resources\ApiApp\DocumentoResource;
use App\Services\ApiApp\PedirFaenaService;
use App\Support\ApiApp\ResumenPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * El permiso de faena desde la app: lo mismo que «Pedir permiso de faena» del portal
 * web (PedirFaenaService, con las reglas del panel). Contrato en docs/api/portal.md.
 */
class FaenaController extends Controller
{
    public function __construct(private readonly PedirFaenaService $servicio) {}

    /** Antes del formulario: lo que va a pedir, o por qué hoy no puede. */
    public function nueva(Request $request): JsonResponse
    {
        return response()->json(['faena' => $this->servicio->datos($request->user()->beneficiario)]);
    }

    public function store(PedirFaenaRequest $request): JsonResponse
    {
        try {
            $faena = $this->servicio->pedir($request->user()->beneficiario, $request->validated(), OrigenTramite::App);
        } catch (PermisoOperativoException $e) {
            // Como un error del formulario: la app lo muestra arriba del botón.
            return response()->json(['message' => $e->getMessage(), 'errors' => ['faena' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'faena' => new DocumentoResource(ResumenPortal::faena($faena->load('codigo'))),
        ], 201);
    }
}

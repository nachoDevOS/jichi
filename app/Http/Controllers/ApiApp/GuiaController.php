<?php

namespace App\Http\Controllers\ApiApp;

use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiApp\PedirGuiaRequest;
use App\Http\Resources\ApiApp\DocumentoResource;
use App\Services\ApiApp\PedirGuiaService;
use App\Support\ApiApp\ResumenPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * La guía de transporte desde la app: lo mismo que «Pedir guía de transporte» del
 * portal web (PedirGuiaService, con las reglas del panel). Contrato en docs/api/portal.md.
 */
class GuiaController extends Controller
{
    public function __construct(private readonly PedirGuiaService $servicio) {}

    /** Antes del formulario: carnet, productos y opciones, o por qué hoy no puede. */
    public function nueva(Request $request): JsonResponse
    {
        return response()->json(['guia' => $this->servicio->datos($request->user()->beneficiario)]);
    }

    /** El pescador que le vendió, por el código de su autorización. */
    public function autorizacion(string $codigo): JsonResponse
    {
        $autorizacion = $this->servicio->autorizacion($codigo);

        return $autorizacion === null
            ? response()->json(['message' => PedirGuiaService::SIN_AUTORIZACION], 404)
            : response()->json(['autorizacion' => $autorizacion]);
    }

    public function store(PedirGuiaRequest $request): JsonResponse
    {
        try {
            $guia = $this->servicio->pedir($request->user()->beneficiario, $request->validated());
        } catch (PermisoOperativoException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['guia' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'guia' => new DocumentoResource(ResumenPortal::guia($guia->load(['codigo', 'aprovechamiento.beneficiario']))),
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\ApiApp;

use App\Enums\OrigenTramite;
use App\Enums\TipoActor;
use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiApp\PedirGuiaRequest;
use App\Http\Resources\ApiApp\DocumentoResource;
use App\Services\ApiApp\PedirGuiaService;
use App\Support\ApiApp\ResumenPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * La foto del pescador de esa autorización (27/11/2026), para reconocerlo al
     * comprarle. Solo para quien tiene carnet de comercializador vigente; nunca por
     * una URL pública. 404 si el código no existe o no tiene foto.
     */
    public function foto(Request $request, string $codigo): Response
    {
        if ($request->user()->beneficiario?->carnetVigenteDe(TipoActor::Comercializador) === null) {
            return response()->json(['message' => PedirGuiaService::SIN_CARNET], 403);
        }

        $bytes = $this->servicio->fotoDelPescador($codigo);

        abort_if($bytes === null, 404);

        return response($bytes, 200, [
            'Content-Type' => (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream',
            // Privada: ni proxies ni la caché compartida la guardan.
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function store(PedirGuiaRequest $request): JsonResponse
    {
        try {
            $guia = $this->servicio->pedir($request->user()->beneficiario, $request->validated(), OrigenTramite::App);
        } catch (PermisoOperativoException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['guia' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'guia' => new DocumentoResource(ResumenPortal::guia($guia->load(['codigo', 'aprovechamiento.beneficiario']))),
        ], 201);
    }
}

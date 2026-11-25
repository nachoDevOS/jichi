<?php

namespace App\Http\Controllers\ApiApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiApp\CambiarClaveAppRequest;
use App\Http\Resources\ApiApp\CuentaResource;
use App\Services\CuentaPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * La cuenta con la que entró la app: quién es y si todavía tiene la clave
 * temporal. Lo único que cambia él mismo es su contraseña, como en la web.
 */
class CuentaController extends Controller
{
    public function __construct(private readonly CuentaPortalService $servicio) {}

    /** Al abrir la app: si el token guardado sigue valiendo, quién es. */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['cuenta' => new CuentaResource($request->user())]);
    }

    public function actualizarClave(CambiarClaveAppRequest $request): JsonResponse
    {
        $cuenta = $request->user();
        $this->servicio->cambiarClave($cuenta, $request->validated('password'), $cuenta->currentAccessToken()->id);

        return response()->json(['cuenta' => new CuentaResource($cuenta->refresh())]);
    }
}

<?php

namespace App\Http\Controllers\ApiApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiApp\IngresarAppRequest;
use App\Http\Resources\ApiApp\CuentaResource;
use App\Models\Acceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * Entrar y salir desde la app BeniDigital con la cuenta del portal /mi-cuenta.
 * Un token por celular; salir borra solo el de ese celular.
 */
class SesionController extends Controller
{
    public function store(IngresarAppRequest $request): JsonResponse
    {
        [$cuenta, $token] = $request->emitirToken();

        return response()->json([
            'token' => $token->plainTextToken,
            'cuenta' => new CuentaResource($cuenta),
        ], 201);
    }

    public function destroy(Request $request): Response
    {
        $cuenta = $request->user();

        Acceso::create([
            'user_id' => $cuenta->id,
            'email' => 'CI '.$cuenta->ci,
            'evento' => 'logout',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $cuenta->currentAccessToken()->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\ApiApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiApp\PerfilResource;
use App\Support\ApiApp\PerfilPortal;
use App\Support\Archivos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * «Mi perfil» de la app (vuelve el 25/11/2026, a pedido del responsable): los
 * mismos datos que «Mis datos» del portal web. Solo lectura: se corrigen en ventanilla.
 */
class PerfilController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['perfil' => new PerfilResource(PerfilPortal::de($request->user()))]);
    }

    /** La foto del carnet, solo para su dueño: no hay id en la URL. */
    public function foto(Request $request): Response
    {
        $bytes = Archivos::contenido($request->user()->beneficiario->foto);

        abort_if($bytes === null, 404);

        return response($bytes, 200, [
            'Content-Type' => (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream',
            // Privada: ni proxies ni la caché compartida la guardan.
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Support\ManualUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 *  Manual de usuario — videos tutoriales que abre el botón de la barra superior
 *  en una ventana. El video se sirve con sesión y no desde public/: muestra el panel por dentro.
 */
class ManualController extends Controller
{
    /**
     * Los tutoriales de este usuario, en JSON — GET /panel/manual
     */
    public function index(Request $request): JsonResponse
    {
        $tutoriales = collect(ManualUsuario::TUTORIALES)
            ->filter(fn (array $t): bool => $request->user()->can($t['permiso']))
            ->map(fn (array $t, string $clave): array => [
                'clave' => $clave,
                'titulo' => $t['titulo'],
                'descripcion' => $t['descripcion'],
                'modulo' => $t['modulo'],
                'capitulos' => $t['capitulos'],
                // `v` cambia al regrabar: el navegador guarda el video un día y mostraría el viejo.
                'video' => route('manual.archivo', [$clave, 'mp4', 'v' => filemtime(ManualUsuario::archivo($clave, 'mp4'))]),
                'portada' => route('manual.archivo', [$clave, 'jpg', 'v' => filemtime(ManualUsuario::archivo($clave, 'jpg'))]),
            ])
            ->values();

        return response()->json($tutoriales);
    }

    /**
     * Video o portada — GET /panel/manual/{tutorial}.{extension}
     */
    public function archivo(Request $request, string $tutorial, string $extension): BinaryFileResponse
    {
        $datos = ManualUsuario::TUTORIALES[$tutorial] ?? abort(404);
        abort_unless($request->user()->can($datos['permiso']), 403);

        // BinaryFileResponse responde los pedidos por rango: el reproductor puede saltar a un capítulo.
        return response()->file(ManualUsuario::archivo($tutorial, $extension), [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}

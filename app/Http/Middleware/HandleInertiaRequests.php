<?php

namespace App\Http\Middleware;

use App\Models\Configuracion;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $usuario = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $usuario ? [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'cargo' => $usuario->cargo,
                    'roles' => $usuario->getRoleNames(),
                    'permisos' => $usuario->getAllPermissions()->pluck('name'),
                ] : null,
            ],

            'institucion' => fn (): array => [
                'sistema' => Configuracion::obtener('sistema.nombre', 'Jichi'),
                'municipio' => Configuracion::obtener('municipio.nombre'),
                'sigla' => Configuracion::obtener('municipio.sigla'),
                'moneda' => Configuracion::obtener('general.simbolo_moneda', 'Bs'),
            ],

            /*
             * El límite de peso y los formatos de archivo, para que el
             * navegador avise ANTES de subir en vez de esperar el rechazo del
             * servidor. Sale de config/jichi.php: es el mismo número que usan
             * las reglas de validación, no una copia escrita en React.
             */
            'archivos' => fn (): array => [
                'max_kb' => config('jichi.archivos.max_kb'),
                'mimes' => config('jichi.archivos.mimes'),
                'mimes_imagen' => config('jichi.archivos.mimes_imagen'),
            ],

            // Toast notifications: se leen una sola vez y se descartan.
            'flash' => [
                'exito' => fn () => $request->session()->get('exito'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],

            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],

            'apariencia' => fn (): string => $request->cookie('apariencia') ?? 'system',
        ];
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CambiarClaveRequest;
use App\Services\CuentaPortalService;
use App\Support\ApiApp\PerfilPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sus datos, para leer: se corrigen en ventanilla con la cédula en la mano.
 * Lo único que cambia él mismo es su contraseña.
 */
class PerfilController extends Controller
{
    public function __construct(private readonly CuentaPortalService $servicio) {}

    public function show(Request $request): Response
    {
        return Inertia::render('portal/perfil', PerfilPortal::de($request->user()));
    }

    public function editarClave(Request $request): Response
    {
        return Inertia::render('portal/clave', [
            'obligatorio' => $request->user()->debe_cambiar_password,
        ]);
    }

    public function actualizarClave(CambiarClaveRequest $request): RedirectResponse
    {
        $this->servicio->cambiarClave($request->user(), $request->validated('password'));

        return redirect()->route('portal.inicio')->with('exito', 'Contraseña actualizada.');
    }
}

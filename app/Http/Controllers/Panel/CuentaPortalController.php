<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\CuentaPortalException;
use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Services\CuentaPortalService;
use Illuminate\Http\RedirectResponse;

/**
 * La cuenta del portal /mi-cuenta, desde la ficha del beneficiario.
 *
 * La clave temporal vuelve por flash (`cuenta_portal`) y se muestra una sola vez:
 * no queda legible en ningún lado, así que perderla es resetearla.
 */
class CuentaPortalController extends Controller
{
    public function __construct(private readonly CuentaPortalService $servicio) {}

    /** POST /panel/beneficiarios/{beneficiario}/portal */
    public function store(Beneficiario $beneficiario): RedirectResponse
    {
        try {
            $clave = $this->servicio->darAcceso($beneficiario);
        } catch (CuentaPortalException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->conClave($beneficiario, $clave, 'Acceso al portal creado.');
    }

    /** PATCH /panel/beneficiarios/{beneficiario}/portal/resetear */
    public function resetear(Beneficiario $beneficiario): RedirectResponse
    {
        try {
            $clave = $this->servicio->resetear($beneficiario);
        } catch (CuentaPortalException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->conClave($beneficiario, $clave, 'Contraseña reseteada.');
    }

    /** PATCH /panel/beneficiarios/{beneficiario}/portal/desactivar */
    public function desactivar(Beneficiario $beneficiario): RedirectResponse
    {
        try {
            $this->servicio->desactivar($beneficiario);
        } catch (CuentaPortalException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', 'Acceso al portal desactivado. Resetear la contraseña lo vuelve a habilitar.');
    }

    private function conClave(Beneficiario $beneficiario, string $clave, string $mensaje): RedirectResponse
    {
        return back()
            ->with('exito', $mensaje)
            ->with('cuenta_portal', [
                'nombre' => $beneficiario->nombreCompleto,
                'usuario' => $beneficiario->ci,
                'clave' => $clave,
                'url' => rtrim(config('app.url'), '/').route('portal.ingresar', [], false),
            ]);
    }
}

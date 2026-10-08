<?php

namespace App\Services;

use App\Enums\RolSistema;
use App\Exceptions\RolInvalidoException;
use App\Models\Rol;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edición y baja de los roles que arma la unidad. Los permisos son el
 * catálogo fijo de RolSistema: un permiso sin ruta no haría nada.
 */
class GestionarRolService
{
    /**
     * @param  list<string>  $permisos
     */
    public function crear(string $nombre, array $permisos): Rol
    {
        return DB::transaction(function () use ($nombre, $permisos): Rol {
            $rol = Rol::create(['name' => $nombre, 'guard_name' => 'web']);
            $this->sincronizar($rol, $permisos);

            return $rol;
        });
    }

    /**
     * @param  list<string>  $permisos
     */
    public function editar(Rol $rol, string $nombre, array $permisos): Rol
    {
        if ($rol->esDelSistema()) {
            throw RolInvalidoException::delSistema($rol->etiqueta());
        }

        DB::transaction(function () use ($rol, $nombre, $permisos): void {
            $rol->update(['name' => $nombre]);
            $this->sincronizar($rol, $permisos);
        });

        return $rol;
    }

    public function eliminar(Rol $rol): void
    {
        if ($rol->esDelSistema()) {
            throw RolInvalidoException::delSistema($rol->etiqueta());
        }

        $usuarios = $rol->users()->count();
        if ($usuarios > 0) {
            throw RolInvalidoException::conUsuarios($rol->name, $usuarios);
        }

        // Tabla de Spatie, sin SoftDeletes: lo que tenía queda en el motivo de la auditoría.
        $rol->motivoAuditoria = 'Tenía: '.$rol->permissions->pluck('name')->implode(', ');
        $rol->delete();
    }

    /**
     * `syncPermissions` va por attach/detach, que no dispara eventos: el
     * cambio se anota a mano en la auditoría.
     *
     * @param  list<string>  $permisos
     */
    private function sincronizar(Rol $rol, array $permisos): void
    {
        $antes = $rol->permissions->pluck('name')->all();
        // «Editar» sin «Ver» deja un botón que termina en 403: se completa acá.
        $permisos = RolSistema::conDependencias($permisos);
        $rol->syncPermissions($permisos);

        $agregados = array_values(array_diff($permisos, $antes));
        $quitados = array_values(array_diff($antes, $permisos));

        if ($agregados !== [] || $quitados !== []) {
            $rol->registrarAuditoria('actualizado', ['permisos' => $permisos], collect([
                $agregados ? 'Agregó: '.implode(', ', $agregados) : null,
                $quitados ? 'Quitó: '.implode(', ', $quitados) : null,
            ])->filter()->implode(' · '));
        }
    }
}

<?php

namespace App\Models;

use App\Enums\RolSistema;
use App\Traits\Auditable;
use Spatie\Permission\Models\Role;

/**
 * Un rol del personal. Los de `RolSistema` los siembra el seeder y no se tocan
 * desde el panel; el resto los arma la unidad en Seguridad › Roles.
 */
class Rol extends Role
{
    use Auditable;

    /** Sembrado por RolPermisoSeeder: el panel no lo edita ni lo borra. */
    public function esDelSistema(): bool
    {
        return RolSistema::tryFrom($this->name) !== null;
    }

    /** «Administrador» para los del sistema; el nombre tal cual para el resto. */
    public function etiqueta(): string
    {
        return RolSistema::tryFrom($this->name)?->etiqueta() ?? $this->name;
    }

    /** Pide `users_count` cargado (withCount) para no consultar por fila. */
    public function puedeEliminarse(): bool
    {
        return ! $this->esDelSistema() && ($this->users_count ?? $this->users()->count()) === 0;
    }
}

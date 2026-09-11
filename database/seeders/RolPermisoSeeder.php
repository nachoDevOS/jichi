<?php

namespace Database\Seeders;

use App\Enums\RolSistema;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolPermisoSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RolSistema::todosLosPermisos() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        // syncPermissions resuelve los nombres contra el cache del registrar,
        // que quedó armado antes de insertar los permisos de arriba.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RolSistema::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web')->syncPermissions($rol->permisos());
        }
    }
}

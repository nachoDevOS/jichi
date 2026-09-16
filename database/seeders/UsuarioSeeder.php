<?php

namespace Database\Seeders;

use App\Enums\RolSistema;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea la cuenta institucional del sistema.
 *
 * POR AHORA ES UNA SOLA, con el rol `administrador`, que tiene todos los
 * permisos. Cuando la unidad defina quién firma qué se agregan los demás roles
 * al enum RolSistema y acá sus cuentas.
 *
 * La contraseña sale de config/jichi.php, NO de env() directamente. Motivo: en
 * producción se corre `php artisan config:cache`, y desde ese momento env()
 * devuelve null fuera de los archivos de config/. Si acá se usara env(), el
 * usuario quedaría con la contraseña por defecto 'password' sin que nadie se
 * entere.
 */
class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $usuario = User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrador Jichi',
                'cargo' => 'Administrador del Sistema',
                'password' => Hash::make(config('jichi.password_semilla')),
                'activo' => true,
                'email_verified_at' => now(),
            ],
        );

        // syncRoles y no assignRole: volver a correr el seeder sobre una base ya
        // sembrada no debe duplicar la asignación.
        $usuario->syncRoles([RolSistema::Administrador->value]);
    }
}

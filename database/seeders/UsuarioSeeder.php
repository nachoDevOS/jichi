<?php

namespace Database\Seeders;

use App\Enums\RolSistema;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea la cuenta institucional del sistema.
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

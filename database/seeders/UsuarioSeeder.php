<?php

namespace Database\Seeders;

use App\Enums\RolSistema;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    /**
     * Crea un usuario institucional por cada rol del sistema.
     *
     * La contraseña sale de config/jichi.php, NO de env() directamente.
     * Motivo: en producción se corre `php artisan config:cache`, y desde ese
     * momento env() devuelve null fuera de los archivos de config/. Si acá se
     * usara env(), los cuatro usuarios quedarían con la contraseña por
     * defecto 'password' sin que nadie se entere.
     */
    public function run(): void
    {
        $password = Hash::make(config('jichi.password_semilla'));

        $usuarios = [
            [RolSistema::Administrador, 'Administrador Jichi', 'admin@admin.com', 'Administrador del Sistema'],
            [RolSistema::Supervisor, 'Supervisor de Recaudaciones', 'supervisor@beni.gob.bo', 'Jefe de Unidad de Recaudaciones'],
            [RolSistema::Operador, 'Operador de Ventanilla 1', 'ventanilla1@beni.gob.bo', 'Operador de Ventanilla'],
            [RolSistema::SoloLectura, 'Consulta Institucional', 'consulta@beni.gob.bo', 'Analista'],
        ];

        foreach ($usuarios as [$rol, $nombre, $email, $cargo]) {
            $usuario = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $nombre,
                    'cargo' => $cargo,
                    'password' => $password,
                    'activo' => true,
                    'email_verified_at' => now(),
                ],
            );

            $usuario->syncRoles([$rol->value]);
        }
    }
}

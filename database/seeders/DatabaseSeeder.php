<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * El orden importa: RolPermisoSeeder tiene que correr antes que UsuarioSeeder
 * —no se le puede asignar a nadie un rol que todavía no existe—.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolPermisoSeeder::class,
            ConfiguracionSeeder::class,
            UsuarioSeeder::class,
        ]);

        // Solo fuera de producción: el padrón de prueba, y los catálogos
        // mientras sigan teniendo los valores de plantilla.
        if (! app()->isProduction()) {
            $this->call([
                CatalogoSeeder::class,
                BeneficiarioSeeder::class,
            ]);
        }
    }
}

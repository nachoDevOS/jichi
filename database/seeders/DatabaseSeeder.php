<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * El orden importa: RolPermisoSeeder tiene que correr antes que UsuarioSeeder
 * —no se le puede asignar a nadie un rol que todavía no existe— y RubroSeeder
 * antes que DemoSeeder, que necesita rubros para armar los trámites.
 *
 * WithoutModelEvents apaga los eventos de Eloquent durante el sembrado. Sin eso,
 * el trait Auditable escribiría una fila en `auditorias` por cada uno de los
 * cientos de registros de prueba, todas con usuario NULL porque no hay sesión:
 * ruido que después hay que aprender a ignorar al mirar esa tabla.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolPermisoSeeder::class,
            ConfiguracionSeeder::class,
            RubroSeeder::class,
            UsuarioSeeder::class,
        ]);

        // Datos de prueba: solo fuera de producción.
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}

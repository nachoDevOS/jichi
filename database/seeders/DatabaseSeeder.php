<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * El orden importa: RolPermisoSeeder tiene que correr antes que UsuarioSeeder
 * —no se le puede asignar a nadie un rol que todavía no existe—.
 *
 * WithoutModelEvents apaga los eventos de Eloquent durante el sembrado. Sin eso,
 * el trait Auditable escribiría una fila en `auditorias` por cada uno de los
 * registros de prueba, todas con usuario NULL porque no hay sesión: ruido que
 * después hay que aprender a ignorar al mirar esa tabla.
 *
 * ============================================================================
 *  LOS ÚNICOS DATOS DE PRUEBA SON LOS DEL PADRÓN
 * ============================================================================
 *
 * Los otros tres seeders no siembran datos de prueba sino lo que el sistema
 * necesita para arrancar: los permisos, la configuración institucional y la
 * cuenta con la que se entra.
 *
 * Los CATÁLOGOS —asociaciones, escala de aprovechamiento y tipos de carnet— van
 * en `CatalogoSeeder`, y corren SOLO fuera de producción **mientras sus valores
 * sean la plantilla**. No son datos de prueba —salen de una resolución— pero sin
 * ellos no se puede emitir ni un carnet, así que en desarrollo hacen falta para
 * recorrer el circuito.
 *
 * EN CUANTO ESOS NÚMEROS SEAN LOS DE LA RESOLUCIÓN, `CatalogoSeeder` SUBE al
 * bloque de arriba, junto a los permisos y la configuración. Sembrado con
 * valores inventados en la base real, el primer carnet emitido saldría cobrando
 * una tarifa que nadie aprobó.
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

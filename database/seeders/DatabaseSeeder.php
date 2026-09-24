<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El orden importa: RolPermisoSeeder tiene que correr antes que UsuarioSeeder
 * —no se le puede asignar a nadie un rol que todavía no existe—.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Datos del dominio que se vacían en cada siembra; usuarios, roles y catálogos quedan. */
    private const TABLAS_DEL_DOMINIO = [
        'pagos',
        'codigos',
        'recibos',
        'guia_detalles',
        'guias_movimiento',
        'permisos_faena',
        'carnets',
        'aprovechamientos_pesq',
        'beneficiarios',
        'auditorias',
        'correlativos',
    ];

    public function run(): void
    {
        $this->call([
            RolPermisoSeeder::class,
            ConfiguracionSeeder::class,
            UsuarioSeeder::class,
        ]);

        // Solo fuera de producción: se vacía el dominio y queda únicamente el padrón de prueba.
        if (! app()->isProduction()) {
            $this->call(CatalogoSeeder::class);
            $this->vaciarDominio();
            $this->call(BeneficiarioSeeder::class);
        }
    }

    // truncate() reinicia además los ids; en PostgreSQL va con RESTART IDENTITY CASCADE.
    private function vaciarDominio(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            foreach (self::TABLAS_DEL_DOMINIO as $tabla) {
                DB::table($tabla)->truncate();
            }
        });
    }
}

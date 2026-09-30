<?php

namespace Database\Seeders;

use App\Enums\ModalidadAprovechamiento;
use App\Models\Asociacion;
use App\Models\CategoriaAprovechamiento;
use App\Models\ProductoHidrobiologico;
use App\Models\TipoCarnet;
use Illuminate\Database\Seeder;

/**
 * Los tres CATÁLOGOS sin los que no se puede emitir nada.
 */
class CatalogoSeeder extends Seeder
{
    /**
     * Los gremios. REVISAR: nombres y siglas reales del registro del SEDAG.
     *
     * @var list<array{nombre: string, sigla: string}>
     */
    private const ASOCIACIONES = [
        ['nombre' => 'Asociación de Pescadores de Trinidad', 'sigla' => 'ASOPESTRI'],
        ['nombre' => 'Asociación de Pescadores del Río Mamoré', 'sigla' => 'APREMA'],
        ['nombre' => 'Asociación de Comercializadores de Pescado del Beni', 'sigla' => 'ACOPEBENI'],
        ['nombre' => 'Asociación de Pescadores de Puerto Almacén', 'sigla' => 'APPA'],
    ];

    /**
     * La escala de aprovechamiento: la del Art. 20 del Reglamento de Pesca del
     * SEDAG-BENI (25/11/2016), tramo por tramo. Los tramos quedan contiguos.
     * El precio lo pone SIREB; los CÓDIGOS son plantilla hasta que Recaudaciones
     * cargue un servicio por tramo. REVISAR.
     *
     * @var list<array{nro_escala: int, descripcion_kg: string, kilos_min: float, kilos_max: float, servicio_sireb: string, modalidad?: string}>
     */
    private const ESCALA = [
        ['nro_escala' => 1, 'descripcion_kg' => '1 Kg Hasta 100 Kg', 'kilos_min' => 1, 'kilos_max' => 100, 'servicio_sireb' => 'SEDAG-001'],
        ['nro_escala' => 2, 'descripcion_kg' => '101 Kg Hasta 200 Kg', 'kilos_min' => 101, 'kilos_max' => 200, 'servicio_sireb' => 'SEDAG-002'],
        ['nro_escala' => 3, 'descripcion_kg' => '201 Kg Hasta 400 Kg', 'kilos_min' => 201, 'kilos_max' => 400, 'servicio_sireb' => 'SEDAG-003'],
        ['nro_escala' => 4, 'descripcion_kg' => '401 Kg Hasta 600 Kg', 'kilos_min' => 401, 'kilos_max' => 600, 'servicio_sireb' => 'SEDAG-004'],
        ['nro_escala' => 5, 'descripcion_kg' => '601 Kg Hasta 800 Kg', 'kilos_min' => 601, 'kilos_max' => 800, 'servicio_sireb' => 'SEDAG-005'],
        ['nro_escala' => 6, 'descripcion_kg' => '801 Kg Hasta 1000 Kg', 'kilos_min' => 801, 'kilos_max' => 1000, 'servicio_sireb' => 'SEDAG-006'],
        // El único con `modalidad` declarada: cuota de la especie que no se amplía.
        // Ver App\Enums\ModalidadAprovechamiento.
        ['nro_escala' => 7, 'descripcion_kg' => '1001 kg Hasta 2000 Kg PAICHE', 'kilos_min' => 1001, 'kilos_max' => 2000, 'servicio_sireb' => 'SEDAG-007', 'modalidad' => ModalidadAprovechamiento::EspecieEspecial->value],
    ];

    /**
     * Las credenciales y su arancel. Los NOMBRES son los oficiales; los
     * PRECIOS son plantilla. REVISAR.
     *
     * @var list<array{nombre: string, precio_bs: float}>
     */
    private const TIPOS_CARNET = [
        ['nombre' => 'Carnet de Pescador', 'tipo_actor' => 'pescador', 'precio_bs' => 80.00],
        ['nombre' => 'Carnet Comercializador', 'tipo_actor' => 'comercializador', 'precio_bs' => 100.00],
    ];

    /**
     * Los productos del cuadro D de la guía y su tasa por kilo. Los NOMBRES son
     * los de la tabla de tamaños mínimos del talonario de la autorización; los
     * PRECIOS son PLANTILLA, desde 0,20 Bs/kg. REVISAR contra la resolución.
     *
     * @var array<string, float>
     */
    private const PRODUCTOS = [
        'Surubí' => 0.50, 'Pacú' => 0.45, 'Tambaqui' => 0.45, 'Chuncuina' => 0.50,
        'Tucunaré' => 0.30, 'General' => 0.40, 'Corvina' => 0.30, 'Dorado (escama)' => 0.35,
        'Sábalo' => 0.20, 'Yatorana' => 0.25, 'Blanquillo' => 0.30, 'Giro' => 0.25,
        'Muturo' => 0.40,
    ];

    public function run(): void
    {
        foreach (self::ASOCIACIONES as $fila) {
            // La clave de búsqueda es el nombre porque es lo que tiene índice
            // único: dos asociaciones no pueden llamarse igual.
            Asociacion::firstOrCreate(['nombre' => $fila['nombre']], ['sigla' => $fila['sigla']]);
        }

        foreach (self::ESCALA as $fila) {
            CategoriaAprovechamiento::firstOrCreate(
                ['nro_escala' => $fila['nro_escala']],
                collect($fila)->except('nro_escala')->all(),
            );
        }

        foreach (self::TIPOS_CARNET as $fila) {
            TipoCarnet::firstOrCreate(
                ['nombre' => $fila['nombre']],
                collect($fila)->except('nombre')->all(),
            );
        }

        foreach (self::PRODUCTOS as $nombre => $precio) {
            ProductoHidrobiologico::firstOrCreate(['nombre' => $nombre], ['precio_kg' => $precio]);
        }

        $this->command?->warn(
            '  Catálogos sembrados con valores de PLANTILLA. Reemplazar por los de la resolución '
            .'antes de emitir carnets: database/seeders/CatalogoSeeder.php',
        );
    }
}

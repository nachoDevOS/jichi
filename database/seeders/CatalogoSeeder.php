<?php

namespace Database\Seeders;

use App\Enums\ConceptoArancel;
use App\Enums\ModalidadAprovechamiento;
use App\Models\ArancelSireb;
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

    /** Servicio «Autorización de Pesca…» de SIREB (código `p`, tarifa por categoría). */
    private const SERVICIO_ESCALA = '01a0f088-34b2-71a5-9d9f-89e2ec9b9096';

    /**
     * La escala de aprovechamiento: la del Art. 20 del Reglamento de Pesca del
     * SEDAG-BENI (25/11/2016), tramo por tramo. Los tramos quedan contiguos.
     * El precio lo pone SIREB: cada tramo apunta a su tarifa dentro del servicio
     * de la autorización. Ids copiados de SIREB el 30/09/2026.
     *
     * @var list<array{nro_escala: int, descripcion_kg: string, kilos_min: float, kilos_max: float, servicio_sireb: string, tarifa_sireb: string, modalidad?: string}>
     */
    private const ESCALA = [
        ['nro_escala' => 1, 'descripcion_kg' => '1 Kg Hasta 100 Kg', 'kilos_min' => 1, 'kilos_max' => 100, 'servicio_sireb' => self::SERVICIO_ESCALA, 'tarifa_sireb' => '01a0f096-3f3a-722a-ac3d-7272101c7c80'],
        ['nro_escala' => 2, 'descripcion_kg' => '101 Kg Hasta 200 Kg', 'kilos_min' => 101, 'kilos_max' => 200, 'servicio_sireb' => self::SERVICIO_ESCALA, 'tarifa_sireb' => '01a0f098-349c-721f-a577-e8a2aceea10b'],
        ['nro_escala' => 3, 'descripcion_kg' => '201 Kg Hasta 400 Kg', 'kilos_min' => 201, 'kilos_max' => 400, 'servicio_sireb' => self::SERVICIO_ESCALA, 'tarifa_sireb' => '01a0f098-dece-7062-95f1-bf478f2a87cc'],
        ['nro_escala' => 4, 'descripcion_kg' => '401 Kg Hasta 600 Kg', 'kilos_min' => 401, 'kilos_max' => 600, 'servicio_sireb' => self::SERVICIO_ESCALA, 'tarifa_sireb' => '01a0f09a-94dc-71c1-afa8-8ee0e06fd703'],
        ['nro_escala' => 5, 'descripcion_kg' => '601 Kg Hasta 800 Kg', 'kilos_min' => 601, 'kilos_max' => 800, 'servicio_sireb' => self::SERVICIO_ESCALA, 'tarifa_sireb' => '01a0f09b-44a5-70cf-8a10-4810562cb314'],
        ['nro_escala' => 6, 'descripcion_kg' => '801 Kg Hasta 1000 Kg', 'kilos_min' => 801, 'kilos_max' => 1000, 'servicio_sireb' => self::SERVICIO_ESCALA, 'tarifa_sireb' => '01a0f09b-f512-7049-aa6d-8af898a34673'],
        // El único con `modalidad` declarada: cuota de la especie que no se amplía.
        // Ver App\Enums\ModalidadAprovechamiento.
        ['nro_escala' => 7, 'descripcion_kg' => '1001 kg Hasta 2000 Kg PAICHE', 'kilos_min' => 1001, 'kilos_max' => 2000, 'servicio_sireb' => self::SERVICIO_ESCALA, 'tarifa_sireb' => '01a0f09c-728d-7030-b714-9f50e830fc5c', 'modalidad' => ModalidadAprovechamiento::EspecieEspecial->value],
    ];

    /**
     * Las credenciales, con los nombres oficiales. Sin tarifa de SIREB: se elige
     * desde la pantalla, y hasta entonces ese tipo no puede emitir carnets.
     *
     * @var list<array{nombre: string, tipo_actor: string}>
     */
    private const TIPOS_CARNET = [
        ['nombre' => 'Carnet de Pescador', 'tipo_actor' => 'pescador'],
        ['nombre' => 'Carnet Comercializador', 'tipo_actor' => 'comercializador'],
    ];

    /**
     * Los productos del cuadro D de la guía, con los nombres de la tabla de tamaños
     * mínimos del talonario. Sin tarifa de SIREB: se elige desde la pantalla, y
     * hasta entonces ese producto no puede ir en una guía.
     *
     * @var list<string>
     */
    private const PRODUCTOS = [
        'Surubí', 'Pacú', 'Tambaqui', 'Chuncuina', 'Tucunaré', 'General', 'Corvina',
        'Dorado (escama)', 'Sábalo', 'Yatorana', 'Blanquillo', 'Giro', 'Muturo',
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

        foreach (self::PRODUCTOS as $nombre) {
            ProductoHidrobiologico::firstOrCreate(['nombre' => $nombre]);
        }

        // Una fila por concepto, sin tarifa: se elige en Catálogos › Aranceles.
        foreach (ConceptoArancel::cases() as $concepto) {
            ArancelSireb::firstOrCreate(['concepto' => $concepto->value]);
        }

        $this->command?->warn(
            '  Catálogos sembrados con valores de PLANTILLA. Reemplazar por los de la resolución '
            .'antes de emitir carnets: database/seeders/CatalogoSeeder.php',
        );
    }
}

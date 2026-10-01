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

    /** Servicio «Carnet» de SIREB: una tarifa por tipo. */
    private const SERVICIO_CARNET = '01a0f39c-d6b4-731b-a76d-c4ef5695edbf';

    /**
     * Las credenciales, con los nombres oficiales y su tarifa de SIREB. Ids
     * copiados de SIREB el 30/09/2026.
     *
     * @var list<array{nombre: string, tipo_actor: string, servicio_sireb: string, tarifa_sireb: string}>
     */
    private const TIPOS_CARNET = [
        ['nombre' => 'Carnet de Pescador', 'tipo_actor' => 'pescador', 'servicio_sireb' => self::SERVICIO_CARNET, 'tarifa_sireb' => '01a0f3a1-b0a0-7234-a607-a05ba41d9caa'],
        ['nombre' => 'Carnet Comercializador', 'tipo_actor' => 'comercializador', 'servicio_sireb' => self::SERVICIO_CARNET, 'tarifa_sireb' => '01a0f3a3-7544-71d7-951f-68ea41a80dd5'],
    ];

    /** Servicio de SIREB del precio por kilo de los productos de la guía. */
    private const SERVICIO_PRODUCTOS = '01a0f4e4-120d-7095-bea1-17b44b338d62';

    /**
     * Los productos del cuadro D de la guía que ya tienen tarifa por kilo en
     * SIREB. Uno nuevo se agrega desde Catálogos › Productos.
     *
     * @var array<string, string>
     */
    private const PRODUCTOS = [
        'Surubí' => '01a0f4e6-5f24-7229-9779-d931143a0aea',
        'Pacú' => '01a0f4e5-fd14-7201-b586-856f65877beb',
        'Chuncuina' => '01a0f4e5-128c-73da-95b9-335181f9d809',
        'General' => '01a0f4e5-6c7f-72a7-8fb3-cfcad044ed1c',
        'Sábalo' => '01a0f4e6-a015-7075-9ef1-810353da1a2c',
        'Blanquillo' => '01a0f4e4-ac90-736c-a99f-dbee3bb22776',
        'Giro' => '01a0f4eb-9715-7321-84c6-6ea754d8acbe',
        'Muturo' => '01a0f4e5-b083-70f8-bcc9-d8fa75ab066c',
    ];

    /**
     * La tarifa de cada arancel, por concepto. Un concepto que no esté acá nace
     * sin tarifa y se elige en Catálogos › Aranceles.
     *
     * @var array<string, array{servicio_sireb: string, tarifa_sireb: string}>
     */
    private const ARANCELES = [
        'faena' => ['servicio_sireb' => '01a0f40b-baf6-7191-adaa-d72a61f9caf6', 'tarifa_sireb' => '01a0f4b2-28a9-70c8-9912-a1fc6109d441'],
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

        foreach (self::PRODUCTOS as $nombre => $tarifa) {
            ProductoHidrobiologico::firstOrCreate(['nombre' => $nombre], [
                'servicio_sireb' => self::SERVICIO_PRODUCTOS,
                'tarifa_sireb' => $tarifa,
            ]);
        }

        // Una fila por concepto: es lo que la pantalla corrige, nunca agrega.
        foreach (ConceptoArancel::cases() as $concepto) {
            ArancelSireb::firstOrCreate(['concepto' => $concepto->value], self::ARANCELES[$concepto->value] ?? []);
        }

        $this->command?->warn(
            '  Catálogos sembrados con valores de PLANTILLA. Reemplazar por los de la resolución '
            .'antes de emitir carnets: database/seeders/CatalogoSeeder.php',
        );
    }
}

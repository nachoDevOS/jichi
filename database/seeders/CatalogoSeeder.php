<?php

namespace Database\Seeders;

use App\Enums\ConceptoArancel;
use App\Models\ArancelSireb;
use App\Models\ArancelTarifa;
use App\Models\Asociacion;
use Illuminate\Database\Seeder;

/**
 * Los CATÁLOGOS sin los que no se puede emitir nada.
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
     * Servicio «Autorización de Pesca…» de SIREB (código `p`): sus tarifas son
     * las escalas del Art. 20 del Reglamento de Pesca. Id de test.sireb al 13/10/2026
     * (el del 30/09 ya no existe: la escala se volvió a cargar allá).
     */
    private const SERVICIO_ESCALA = '01a13592-0a9f-7122-980d-4baffb3f462e';

    /** Servicio «Carnet» de SIREB: una tarifa por actividad. */
    private const SERVICIO_CARNET = '01a0f39c-d6b4-731b-a76d-c4ef5695edbf';

    /** Servicio de SIREB de los productos de la guía: cada tarifa es una especie, por kilo. */
    private const SERVICIO_PRODUCTOS = '01a0f4e4-120d-7095-bea1-17b44b338d62';

    /** Servicio «Aranceles» de SIREB: entre sus tarifas está la del permiso de faena. */
    private const SERVICIO_ARANCELES = '01a0f40b-baf6-7191-adaa-d72a61f9caf6';

    /**
     * El servicio de SIREB de cada trámite, por concepto. Solo el servicio: la
     * tarifa la elige ventanilla al emitir. Ids copiados de SIREB el 30/09/2026.
     *
     * @var array<string, string>
     */
    /** Las tarifas del servicio «Carnet» de test.sireb, por actividad (13/10/2026). */
    private const TARIFAS_CARNET = [
        'pescador' => ['01a0f3a1-b0a0-7234-a607-a05ba41d9caa', 'Carnet de pescador'],
        'comercializador' => ['01a0f3a3-7544-71d7-951f-68ea41a80dd5', 'Carnet de comercializador'],
    ];

    private const ARANCELES = [
        'autorizacion' => self::SERVICIO_ESCALA,
        'carnet' => self::SERVICIO_CARNET,
        'faena' => self::SERVICIO_ARANCELES,
        'guia' => self::SERVICIO_PRODUCTOS,
    ];

    public function run(): void
    {
        foreach (self::ASOCIACIONES as $fila) {
            // La clave de búsqueda es el nombre porque es lo que tiene índice
            // único: dos asociaciones no pueden llamarse igual.
            Asociacion::firstOrCreate(['nombre' => $fila['nombre']], ['sigla' => $fila['sigla']]);
        }

        // Una fila por trámite; las demás las registra la unidad desde el panel.
        foreach (ConceptoArancel::cases() as $concepto) {
            ArancelSireb::firstOrCreate(['concepto' => $concepto->value], [
                'nombre' => $concepto->etiqueta(),
                'servicio_sireb' => self::ARANCELES[$concepto->value] ?? null,
            ]);
        }

        $carnet = ArancelSireb::de(ConceptoArancel::Carnet);

        foreach (self::TARIFAS_CARNET as $clave => [$id, $etiqueta]) {
            ArancelTarifa::firstOrCreate(['arancel_id' => $carnet->id, 'clave' => $clave], ['sireb_tarifa_id' => $id, 'etiqueta' => $etiqueta]);
        }

        $this->command?->warn(
            '  Catálogos sembrados con valores de PLANTILLA. Reemplazar por los de la resolución '
            .'antes de emitir carnets: database/seeders/CatalogoSeeder.php',
        );
    }
}

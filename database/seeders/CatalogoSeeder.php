<?php

namespace Database\Seeders;

use App\Enums\ModalidadAprovechamiento;
use App\Models\Asociacion;
use App\Models\CategoriaAprovechamiento;
use App\Models\TipoCarnet;
use Illuminate\Database\Seeder;

/**
 * Los tres CATÁLOGOS sin los que no se puede emitir nada.
 *
 * ============================================================================
 *  ⚠️  LOS NÚMEROS DE ACÁ SON UNA PLANTILLA, NO LA RESOLUCIÓN
 * ============================================================================
 *
 * Los tramos de la escala, los precios y los nombres de las asociaciones salen
 * de una resolución administrativa que no está cargada en el sistema. Lo que
 * hay abajo se armó para que el circuito se pueda recorrer de punta a punta en
 * desarrollo, y está marcado con `REVISAR` renglón por renglón.
 *
 * **Antes de usar esto en la base real hay que reemplazar los valores.** Un
 * carnet emitido con una tarifa inventada es plata mal cobrada, y el error no
 * aparece hasta el arqueo.
 *
 * De dónde salen los números de la plantilla, para que se sepa qué se supuso:
 *
 *   - Los DOS EXTREMOS de la escala son los únicos textos oficiales que había:
 *     «1 Kg Hasta 100 Kg» y «1001 kg Hasta 2000 Kg PAICHE».
 *   - Los cinco tramos del medio se repartieron a ojo entre esos dos extremos.
 *   - Los precios siguen una regla lineal de 55 Bs por cada 100 kg, que hace
 *     cerrar los dos valores conocidos de los tramos bajos (55 y 110). OJO: el
 *     tercer valor conocido —500 Bs— NO cae en esa recta, así que la escala
 *     real casi seguro NO es lineal. Es la señal más clara de que esto hay que
 *     confirmarlo.
 *
 * ============================================================================
 *  POR QUÉ firstOrCreate Y NO updateOrCreate
 * ============================================================================
 *
 * Un catálogo lo edita la unidad desde el panel. Con `updateOrCreate`, volver a
 * correr el seeder —cosa que pasa sola al rearmar un entorno— PISARÍA la
 * tarifa que alguien ajustó, y nadie se enteraría hasta que un carnet saliera
 * cobrando el número viejo.
 *
 * Con `firstOrCreate`, el seeder solo llena lo que falta. Para forzar la
 * recarga de un valor hay que decidirlo a mano, que es lo correcto para un dato
 * que sale de una resolución.
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
     * La escala de aprovechamiento. REVISAR TODO: kilos, textos y precios.
     *
     * Los tramos tienen que ser CONTIGUOS y SIN HUECOS: el `kilos_min` de cada
     * uno es el `kilos_max` del anterior más 1. Si queda un hueco,
     * `CategoriaAprovechamiento::paraVolumen()` devuelve null para los
     * volúmenes que caen adentro y el formulario no ofrece ninguna escala, sin
     * ningún error que lo explique.
     *
     * @var list<array{nro_escala: int, descripcion_kg: string, kilos_min: float, kilos_max: float, valor_bs: float, modalidad?: string}>
     */
    private const ESCALA = [
        // Texto oficial conocido, y el valor de 55 Bs también.
        ['nro_escala' => 1, 'descripcion_kg' => '1 Kg Hasta 100 Kg', 'kilos_min' => 1, 'kilos_max' => 100, 'valor_bs' => 55.00],
        // Valor de 110 Bs conocido; el texto y el corte en 200, supuestos.
        ['nro_escala' => 2, 'descripcion_kg' => '101 Kg Hasta 200 Kg', 'kilos_min' => 101, 'kilos_max' => 200, 'valor_bs' => 110.00],
        ['nro_escala' => 3, 'descripcion_kg' => '201 Kg Hasta 300 Kg', 'kilos_min' => 201, 'kilos_max' => 300, 'valor_bs' => 165.00],
        ['nro_escala' => 4, 'descripcion_kg' => '301 Kg Hasta 500 Kg', 'kilos_min' => 301, 'kilos_max' => 500, 'valor_bs' => 275.00],
        ['nro_escala' => 5, 'descripcion_kg' => '501 Kg Hasta 750 Kg', 'kilos_min' => 501, 'kilos_max' => 750, 'valor_bs' => 412.50],
        ['nro_escala' => 6, 'descripcion_kg' => '751 Kg Hasta 1000 Kg', 'kilos_min' => 751, 'kilos_max' => 1000, 'valor_bs' => 550.00],
        // Texto oficial conocido. El «PAICHE» va en el texto y no en ningún
        // número: es lo que obliga a guardar `descripcion_kg` además del rango.
        //
        // Y ES EL ÚNICO CON `modalidad` DECLARADA: los otros seis se quedan con
        // el default de la tabla, `escala_general`. El paiche es una CUOTA DE LA
        // ESPECIE con tasación fija que NO se amplía —agotada, hay que tramitar
        // una nueva—, y eso lo fija la resolución al definir el tramo, no el
        // operador al otorgar. Ver App\Enums\ModalidadAprovechamiento.
        ['nro_escala' => 7, 'descripcion_kg' => '1001 kg Hasta 2000 Kg PAICHE', 'kilos_min' => 1001, 'kilos_max' => 2000, 'valor_bs' => 1100.00, 'modalidad' => ModalidadAprovechamiento::EspecieEspecial->value],
    ];

    /**
     * Las credenciales y su arancel. Los NOMBRES son los oficiales; los
     * PRECIOS son plantilla. REVISAR.
     *
     * @var list<array{nombre: string, precio_bs: float}>
     */
    private const TIPOS_CARNET = [
        ['nombre' => 'Carnet de Pescador', 'precio_bs' => 80.00],
        ['nombre' => 'Carnet Comercializador', 'precio_bs' => 100.00],
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
            TipoCarnet::firstOrCreate(['nombre' => $fila['nombre']], ['precio_bs' => $fila['precio_bs']]);
        }

        $this->command?->warn(
            '  Catálogos sembrados con valores de PLANTILLA. Reemplazar por los de la resolución '
            .'antes de emitir carnets: database/seeders/CatalogoSeeder.php',
        );
    }
}

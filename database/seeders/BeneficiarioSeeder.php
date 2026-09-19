<?php

namespace Database\Seeders;

use App\Models\Beneficiario;
use Illuminate\Database\Seeder;

/**
 * DATOS DE PRUEBA DEL PADRÓN — y son los ÚNICOS del sistema.
 *
 * ============================================================================
 *  POR QUÉ NO SE SIEMBRA NADA MÁS
 * ============================================================================
 *
 * Los carnets, los cupos, las faenas y las guías se cargan a mano desde el
 * panel, que es justamente lo que hay que probar. Sembrados, las pantallas se
 * ven llenas sin que nadie haya recorrido el circuito, y el primer error real
 * aparece en ventanilla.
 *
 * Lo que sí hace falta para que ese circuito arranque son los CATÁLOGOS
 * —asociaciones, escala de aprovechamiento y tipos de carnet—, y no se siembran
 * acá a propósito: no son datos de prueba sino datos oficiales, salen de una
 * resolución y los carga la unidad desde el panel.
 *
 * ============================================================================
 *  LAS TRES PRIMERAS FICHAS SON FIJAS, NO ALEATORIAS
 * ============================================================================
 *
 * Con todo al azar no se puede escribir un paso a paso —«abrí la ficha de
 * 1234567»— ni volver a la misma pantalla después de recargar la base. Estas
 * tres tienen cédula conocida y cubren los tres casos que cambian cómo se arma
 * y cómo se imprime el nombre.
 */
class BeneficiarioSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * updateOrCreate y no create: volver a correr el seeder sobre una base
         * ya sembrada no debe reventar contra el índice único de `ci`.
         */
        Beneficiario::updateOrCreate(['ci' => '1234567'], [
            'complemento' => null,
            'expedido' => 'BN',
            'primerNombre' => 'Juan',
            'segundoNombre' => 'Carlos',
            'apellidoPaterno' => 'Antezana',
            'apellidoMaterno' => 'Chávez',
            'apellidoCasado' => null,
            'fechaNacimiento' => '1985-04-12',
            'genero' => 'masculino',
            'nacionalidad' => 'Boliviana',
            'direccion' => 'Barrio Pompeya, calle Muiba s/n',
            'ciudad' => 'Trinidad',
            'provincia' => 'Cercado',
            'telefono' => '71234567',
            'email' => 'juan.antezana@example.test',
        ]);

        // El nombre más largo posible: cinco partes, con el «de» del apellido de
        // casada. Es el que hace trabajar al encogido de texto del carnet.
        Beneficiario::updateOrCreate(['ci' => '2345678'], [
            'complemento' => '1A',
            'expedido' => 'BN',
            'primerNombre' => 'María',
            'segundoNombre' => 'Esther',
            'apellidoPaterno' => 'Justiniano',
            'apellidoMaterno' => 'Melgar',
            'apellidoCasado' => 'Áñez',
            'fechaNacimiento' => '1990-11-03',
            'genero' => 'femenino',
            'nacionalidad' => 'Boliviana',
            'direccion' => 'Av. Cipriano Barace #430',
            'ciudad' => 'Trinidad',
            'provincia' => 'Cercado',
            'telefono' => '69871234',
            'email' => 'maria.justiniano@example.test',
        ]);

        // El caso mínimo: sin segundo nombre ni apellido materno.
        Beneficiario::updateOrCreate(['ci' => '3456789'], [
            'complemento' => null,
            'expedido' => 'LP',
            'primerNombre' => 'Pedro',
            'segundoNombre' => null,
            'apellidoPaterno' => 'Noza',
            'apellidoMaterno' => null,
            'apellidoCasado' => null,
            'fechaNacimiento' => '1978-01-27',
            'genero' => 'masculino',
            'nacionalidad' => 'Boliviana',
            'direccion' => 'Puerto Almacén',
            'ciudad' => 'Trinidad',
            'provincia' => 'Cercado',
            'telefono' => '70011223',
            'email' => null,
        ]);

        /*
         * ====================================================================
         *  EL RESTO SE COMPLETA HASTA EL OBJETIVO, NO SE CREA DE NUEVO
         * ====================================================================
         *
         * Las tres de arriba son idempotentes por el `updateOrCreate`, pero la
         * factory NO: cada `create()` inserta filas nuevas. Escrito como
         * `factory()->count(37)->create()` a secas, volver a correr el seeder
         * —cosa que pasa sola al rearmar un entorno o al agregar un seeder
         * nuevo— sumaba otras treinta y siete, y el padrón crecía de 40 a 77 a
         * 114 sin que nadie lo pidiera ni lo notara.
         *
         * Por eso se mira cuántas hay y se completa la diferencia. Corrido dos
         * veces seguidas, la segunda no inserta nada.
         */
        $objetivo = 40;
        $faltan = max(0, $objetivo - Beneficiario::count());

        if ($faltan > 0) {
            /*
             * Una de cada ocho, casada. No se libra al azar porque el apellido
             * de casada cambia cómo se arma `nombreCompleto` —le agrega el
             * «de»— y esa variante tiene que poder verse en las pantallas sin
             * depender de la suerte. El max(1) garantiza que haya al menos una
             * aunque falte una sola ficha.
             */
            $casadas = min($faltan, max(1, (int) round($faltan / 8)));

            Beneficiario::factory()->count($faltan - $casadas)->create();
            Beneficiario::factory()->count($casadas)->casada()->create();
        }
    }
}

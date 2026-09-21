<?php

namespace Database\Seeders;

use App\Models\Beneficiario;
use App\Models\Departamento;
use Illuminate\Database\Seeder;

/**
 * DATOS DE PRUEBA DEL PADRÓN — y son los ÚNICOS del sistema.
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
            'departamento_id' => Departamento::where('codigo', 'BN')->value('id'),
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
            'departamento_id' => Departamento::where('codigo', 'BN')->value('id'),
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
            'departamento_id' => Departamento::where('codigo', 'LP')->value('id'),
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
         *  EL RESTO SE COMPLETA HASTA EL OBJETIVO, NO SE CREA DE NUEVO
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

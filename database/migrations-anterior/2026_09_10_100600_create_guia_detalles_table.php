<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Detalle de la guía — qué se traslada, especie por especie
|--------------------------------------------------------------------------
|
|     guia ──< «Surubí, fresco, 120 kg»
|          ──< «Pacú, congelado, 80 kg»
|
| Va en tabla aparte y no en un `jsonb` ni en columnas numeradas porque con
| esas dos se pierde lo mismo: poder preguntarle a la base cuántos kilos de
| surubí salieron del Beni este año, que es el reporte que la unidad necesita.
|
| ES LA ÚNICA CASCADA DEL MÓDULO. El detalle no vale sin su guía y el
| formulario reescribe la grilla entera, así que borrar es parte del uso
| normal. Faenas, guías y pagos van al revés —restrictOnDelete—, porque cada
| uno respalda un papel que salió a la calle.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guia_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('guia_id')->constrained('guias')->cascadeOnDelete();

            /*
             * LA ESPECIE VA COMO TEXTO, y cuesta: «surubí», «Surubi» y «SURUBÍ»
             * van a convivir en esta columna.
             *
             * Gana igual, porque el padrón de especies del Beni no existe
             * escrito y una lista cerrada incompleta es peor: la especie que
             * falta no se puede cargar y la guía no sale. Cuando la unidad tenga
             * la lista oficial, esto pasa a `especie_id`.
             */
            $table->string('especie', 100);

            // Ver App\Enums\CondicionProducto. Obligatorio: cambia el control
            // sanitario y el valor. Va por FILA porque un mismo viaje lleva
            // pescado fresco en hielo y charque en bolsas.
            $table->string('condicion', 50)->comment('fresco | congelado | seco | salado');

            $table->decimal('cantidad_kg', 10, 2);

            /*
             * `imponible` parece redundante —cantidad por precio— y no lo es: es
             * la base de cálculo que la unidad ESCRIBIÓ EN EL PAPEL, y con una
             * rebaja o un redondeo no coincide con la multiplicación.
             * Recalcularlo haría que el sistema contradijera la guía firmada.
             *
             * Los dos nullable: hay guías que solo declaran volumen.
             */
            $table->decimal('precio_unitario', 10, 2)->nullable();
            $table->decimal('imponible', 10, 2)->nullable()
                ->comment('Base de calculo tal como figura en el papel. NO se recalcula');

            $table->timestamps();

            // «El detalle de esta guía», la única consulta fuera de reportes.
            $table->index('guia_id');

            // Para el reporte por especie, que recorre todas las guías.
            $table->index('especie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guia_detalles');
    }
};

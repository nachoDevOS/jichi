<?php

use App\Enums\EstadoFaena;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Permisos de faena — la autorización de UNA salida de pesca.
|
| El carnet es la llave anual; con él solo no se sale a trabajar. Cada salida se
| autoriza con una faena: cuántos kilos y hasta cuándo. Vigencia máxima, un mes.
|
| Apunta a DOS cosas a la vez y hacen falta las dos: `aprovechamiento_id` es de
| dónde salen los kilos, `carnet_id` es quién los extrae. Son la misma persona
| por caminos distintos, y se renuevan en fechas distintas.
|
| NO se edita ni se borra: se vence o se completa. El número sale de un talonario
| de papel que el pescador se llevó.
|
| Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos_faena', function (Blueprint $table) {
            $table->id();

            $table->foreignId('aprovechamiento_id')->constrained('aprovechamientos_pesq')->restrictOnDelete();
            $table->foreignId('carnet_id')->constrained('carnets')->restrictOnDelete();

            $table->unsignedInteger('numero_faena')->comment('Hoja del talonario, correlativa dentro del cupo');

            // Decimal: con enteros, treinta faenas redondeando medio kilo cada
            // una desajustan el cupo en quince.
            $table->decimal('kilos_extraidos', 12, 2)->default(0);

            $table->date('fecha_salida');

            // Se guarda calculada en vez de derivarla al leer: si la resolución
            // cambia el plazo, los permisos ya emitidos tienen que seguir
            // venciendo cuando dice el papel que el pescador tiene en la mano.
            $table->date('fecha_limite')->comment('Máximo 1 mes desde fecha_salida');

            $table->string('estado', 20)->default(EstadoFaena::Activo->value);

            /*
             * Dos hojas del talonario no pueden tener el mismo número DENTRO del
             * mismo cupo. No es único global: cada bolsa arranca su numeración en
             * 1, que es como se llena el papel.
             *
             * Y NO es parcial: dar de baja una faena no libera su número. La hoja
             * se gastó y el pescador se la llevó; reusar el número dejaría dos
             * salidas distintas diciendo ser el mismo papel.
             */
            $table->unique(['aprovechamiento_id', 'numero_faena']);

            // La consulta caliente del módulo: la suma de kilos consumidos.
            // Ver AprovechamientoPesq::saldoKg().
            $table->index(['aprovechamiento_id', 'estado']);

            $table->index(['carnet_id', 'estado']);

            // Para el comando diario que marca las vencidas.
            $table->index('fecha_limite');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos_faena');
    }
};

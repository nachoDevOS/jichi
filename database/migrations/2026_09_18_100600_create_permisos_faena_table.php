<?php

use App\Enums\EstadoFaena;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Permisos de faena — la autorización de UNA salida de pesca.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos_faena', function (Blueprint $table) {
            $table->id();

            /*
             * LA FAENA CUELGA DEL CARNET Y DE NADA MÁS. El cupo se alcanza a
             * través de él —`carnets.aprovechamiento_id`— y por eso acá NO hay
             * una segunda FK: con las dos, un permiso podía quedar apuntando a
             * un cupo distinto del que respalda su carnet, y nada lo impedía.
             */
            $table->foreignId('carnet_id')->constrained('carnets')->restrictOnDelete();

            $table->unsignedInteger('numero_faena')->comment('Hoja del talonario, correlativa dentro del carnet');

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
             * mismo carnet. No es único global: cada talonario arranca su
             * numeración en 1, que es como se llena el papel.
             */
            $table->unique(['carnet_id', 'numero_faena']);

            /*
             * La consulta caliente del módulo: la suma de kilos consumidos del
             * cupo, que hoy llega por `carnets`. Ver AprovechamientoPesq::faenas().
             */
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

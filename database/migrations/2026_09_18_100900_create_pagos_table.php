<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Pagos — el DETALLE de lo que se cobró, abono por abono.
|
| Es POLIMÓRFICA porque se cobran tres cosas —carnet, cupo y guía— y las tres se
| pagan igual. Partida en tres tablas, `numero_recibo` dejaría de ser único
| global.
|
| EL COSTO: se pierde la clave foránea. El motor no puede exigir que
| `pagable_id` exista, porque no sabe en qué tabla buscarlo. La integridad la
| sostienen los RESTRICT de las otras tablas y la aplicación.
|
| Y no se precarga con `with('pagable.beneficiario')`: eso se IGNORA en silencio
| y el N+1 sigue ahí. Va con `morphWith`.
|
| Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();

            // CASCADE y no RESTRICT, al revés que en el resto del sistema: un
            // pago sin recibo no se puede imprimir ni entra en ningún arqueo.
            $table->foreignId('recibo_id')->constrained('recibos')->cascadeOnDelete();

            // `morphs` crea las dos columnas MÁS el índice (pagable_type,
            // pagable_id), que resuelve la consulta caliente: «cuánto se pagó de
            // ESTE carnet».
            $table->morphs('pagable');

            // Se llama `parcial` porque el nombre dice la regla: un trámite se
            // paga en cuotas. Lo que se debe NO se guarda: se calcula al leer
            // con el trait Pagable, o quedaría desfasado al corregir un abono.
            $table->decimal('monto_parcial', 12, 2)->comment('Este abono, no el total del trámite');

            $table->string('metodo_pago', 20)->comment('efectivo | transferencia | qr');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};

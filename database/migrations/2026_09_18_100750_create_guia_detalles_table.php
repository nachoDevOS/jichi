<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Detalle de una guía — el cuadro D del papel, una fila por especie.
|
| El talonario trae cinco renglones; acá no hay tope: el que necesite seis
| emite una sola guía en vez de dos. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guia_detalles', function (Blueprint $table) {
            $table->id();

            /*
             * CASCADE Y NO RESTRICT, al revés que el resto del dominio: el
             * detalle no tiene vida propia —es el cuerpo de la guía, no un
             * documento aparte— así que borrarla se lo tiene que llevar.
             *
             * ⚠️ Con SoftDeletes esto NO se dispara: `$guia->delete()` es un
             * UPDATE. La baja del detalle la hace el servicio a mano. Ver la
             * trampa de `pagos.recibo_id` en CLAUDE.md.
             */
            $table->foreignId('guia_movimiento_id')->constrained('guias_movimiento')->cascadeOnDelete();

            // Texto libre: no hay padrón de especies y uno cerrado dejaría al
            // comerciante esperando a que alguien dé de alta «blanquillo».
            $table->string('especie', 120);

            // Las DIEZ columnas de tilde del cuadro, en una sola. Ver el enum.
            $table->string('condicion', 30)->comment('CondicionProducto');

            $table->decimal('cantidad_kg', 12, 2)->default(0)
                ->comment('CANT. ADQUIRIDA en kg, descarga o trasbordo');

            /*
             * LOS DOS SON DECLARATIVOS: lo que el comerciante pagó por el
             * pescado en origen, no el arancel del SEDAG. El arancel sale de
             * `guias_movimiento.monto`.
             */
            $table->decimal('precio_kg', 12, 2)->default(0)->comment('Precio pagado por kg en origen');
            $table->decimal('importe_total', 12, 2)->default(0);

            $table->index('guia_movimiento_id');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guia_detalles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Códigos — la llave pública de CUALQUIER documento que se entrega.
| Una sola tabla y no una columna por tabla: ver MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigos', function (Blueprint $table) {
            $table->id();

            /*
             * 16 caracteres, sin prefijo, del alfabeto sin confundibles. Es la
             * llave de /verificar, así que va al azar y no correlativa: un
             * número consecutivo deja ver el documento del de al lado.
             */
            $table->string('codigo', 16);

            /*
             * A QUÉ DOCUMENTO PERTENECE. Polimórfico y sin clave foránea, con
             * el mismo costo que `pagos`: la integridad la sostiene la
             * aplicación.
             */
            $table->string('codigable_type');
            $table->unsignedBigInteger('codigable_id');

            /*
             * ÚNICO COMPLETO, no parcial: un código que salió impreso queda
             * QUEMADO para siempre, aunque su documento se dé de baja. Ver la
             * tabla de índices únicos de MER.md.
             */
            $table->unique('codigo');

            // Un documento, un código. Sin esto, dos emisiones seguidas le
            // colgarían dos códigos al mismo papel.
            $table->unique(['codigable_type', 'codigable_id']);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos');
    }
};

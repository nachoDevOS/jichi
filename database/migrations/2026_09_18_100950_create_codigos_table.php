<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Códigos — la llave pública de cualquier documento que se entrega.
| Una sola tabla y no una columna por tabla. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigos', function (Blueprint $table) {
            $table->id();

            /*
             * 16 caracteres al azar, del alfabeto sin confundibles. Único
             * COMPLETO y no parcial: un código impreso queda quemado para
             * siempre, aunque su documento se dé de baja.
             */
            $table->string('codigo', 16)->unique();

            // Polimórfico y sin clave foránea, igual que `pagos`: la integridad
            // la sostiene la aplicación.
            $table->string('codigable_type');
            $table->unsignedBigInteger('codigable_id');

            // Un documento, un código: sin esto dos emisiones seguidas le
            // colgarían dos al mismo papel.
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

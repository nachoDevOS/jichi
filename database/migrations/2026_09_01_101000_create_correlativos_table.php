<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contador correlativo por serie y gestion. Se bloquea con SELECT ... FOR UPDATE
     * al reservar un numero, de modo que dos ventanillas concurrentes nunca emitan
     * el mismo DOC-PESCA-2026-0001.
     */
    public function up(): void
    {
        Schema::create('correlativos', function (Blueprint $table) {
            $table->id();
            $table->string('serie', 40)->comment('PAG, TRA-PESCA, DOC-PESCA-PPA');
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();

            $table->unique(['serie', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correlativos');
    }
};

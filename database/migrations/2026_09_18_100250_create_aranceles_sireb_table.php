<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Aranceles de SIREB — qué servicio cobra cada trámite: una fila por concepto,
| SOLO con el servicio. La tarifa se elige al emitir. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aranceles_sireb', function (Blueprint $table) {
            $table->id();

            // Único a secas: las filas las pone el seeder y no se dan de baja.
            $table->string('concepto', 30)->unique()->comment('ConceptoArancel');

            // Nullable: nace sin servicio hasta que alguien lo elige.
            $table->uuid('servicio_sireb')->nullable()->comment('Id del servicio en SIREB');
            $table->json('sireb_historial')->nullable()->comment('Servicios que tuvo antes, con desde/hasta');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aranceles_sireb');
    }
};

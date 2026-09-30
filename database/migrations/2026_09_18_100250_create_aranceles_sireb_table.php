<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Aranceles de SIREB — los cobros que no cuelgan de un catálogo (hoy, la faena):
| una fila por concepto con su servicio y tarifa en Recaudaciones. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aranceles_sireb', function (Blueprint $table) {
            $table->id();

            // Único a secas: las filas las pone el seeder y no se dan de baja.
            $table->string('concepto', 30)->unique()->comment('ConceptoArancel');

            // Nullable: nacen sin tarifa hasta que alguien la elige.
            $table->uuid('servicio_sireb')->nullable()->comment('Id del servicio en SIREB');
            $table->uuid('tarifa_sireb')->nullable()->comment('Id de la tarifa dentro del servicio');
            $table->json('sireb_historial')->nullable()->comment('Servicio y tarifa que tuvo antes, con desde/hasta');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aranceles_sireb');
    }
};

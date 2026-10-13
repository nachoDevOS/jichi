<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Aranceles de SIREB — un nombre y el servicio de SIREB que lo cobra. Los de los
| trámites llevan `concepto`; los registrados en el panel, no. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aranceles_sireb', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 120)->comment('Como se ve en Catálogos › Aranceles');
            // Solo en las filas que usa un trámite. Único a secas: NULL no choca con NULL.
            $table->string('concepto', 30)->nullable()->unique()->comment('ConceptoArancel; null si lo registró la unidad');

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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Tarifas definidas de un arancel: un nombre (etiqueta) y la tarifa de SIREB que
| lo cobra, por su id. La `clave` la usa solo el carnet para hallar la suya.
| Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arancel_tarifas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('arancel_id')->constrained('aranceles_sireb')->restrictOnDelete();

            $table->string('etiqueta', 120)->comment('Como se ve y se edita: «Carnet de pescador», «Alevines»…');
            // Fija y oculta: el carnet busca por ella, no por la etiqueta. Null en los de la unidad.
            $table->string('clave', 30)->nullable()->comment('pescador | comercializador; null si no la usa el código');
            $table->uuid('sireb_tarifa_id')->comment('Id de la tarifa en SIREB');

            $table->timestamps();
            $table->softDeletes();
        });

        // Una clave por arancel. Parcial: quitar el vínculo la libera; NULL no choca con NULL.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX arancel_tarifas_clave_unica
                ON arancel_tarifas (arancel_id, clave)
                WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('arancel_tarifas');
    }
};

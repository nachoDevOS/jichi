<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tipos de carnet — el catálogo de credenciales y su arancel.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_carnet', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 120);
            $table->decimal('precio_bs', 10, 2)->default(0);
            $table->boolean('estado')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        // SIN INDICE UNICO EN LA BASE, a propósito: era un índice PARCIAL
        // —`WHERE deleted_at IS NULL`— que solo existe en PostgreSQL. La
        // unicidad la exige el Request del catálogo, que alcanza: esto se edita
        // desde el panel una vez cada tanto, sin dos ventanillas a la vez.
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_carnet');
    }
};

<?php

use App\Enums\ModalidadAprovechamiento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Categorías de aprovechamiento — la ESCALA OFICIAL del SEDAG.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_aprovechamiento', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('nro_escala')->comment('1 a 7, el orden oficial de la escala');

            // El RÉGIMEN del tramo, y lo fija la resolución al definirlo: de él
            // depende si el cupo se va a poder ampliar. Ver ModalidadAprovechamiento.
            $table->string('modalidad', 30)
                ->default(ModalidadAprovechamiento::EscalaGeneral->value)
                ->comment('escala_general | especie_especial');

            // El texto oficial no siempre es la lectura del rango: el tramo más
            // alto dice «1001 kg Hasta 2000 Kg PAICHE», y eso no está en ningún número.
            $table->string('descripcion_kg', 160)->comment('Texto literal de la resolución');

            // Decimal y no entero: la balanza pesa con decimales, y con enteros
            // un cupo de 100,5 kg caería fuera del primer tramo por redondeo.
            $table->decimal('kilos_min', 12, 2);
            $table->decimal('kilos_max', 12, 2);

            $table->decimal('valor_bs', 10, 2)->comment('Lo que se cobra por ese cupo');

            // Booleano y no enum: o la escala está vigente o quedó derogada.
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
        Schema::dropIfExists('categorias_aprovechamiento');
    }
};

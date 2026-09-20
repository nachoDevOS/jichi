<?php

use App\Enums\ModalidadAprovechamiento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // PARCIAL, no `unique()` a secas: en SQL NULL != NULL, así que un unique
        // con `deleted_at` adentro no bloquearía nada. Y tiene que ser parcial y
        // no global porque esto es un catálogo: una escala dada de baja libera su
        // número para que se pueda volver a cargar.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX categorias_aprovechamiento_nro_escala_unico
                ON categorias_aprovechamiento (nro_escala)
                WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_aprovechamiento');
    }
};

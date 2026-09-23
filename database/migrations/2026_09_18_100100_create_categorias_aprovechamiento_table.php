<?php

use App\Enums\ModalidadAprovechamiento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Categorías de aprovechamiento — la escala oficial del SEDAG. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_aprovechamiento', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('nro_escala')->comment('1 a 7, el orden oficial');

            // De la modalidad depende si el cupo se puede ampliar.
            $table->string('modalidad', 30)
                ->default(ModalidadAprovechamiento::EscalaGeneral->value)
                ->comment('escala_general | especie_especial');

            // El texto oficial no siempre es la lectura del rango: el tramo más
            // alto dice «1001 kg Hasta 2000 Kg PAICHE».
            $table->string('descripcion_kg', 160)->comment('Texto literal de la resolución');

            // Decimal: con enteros un cupo de 100,5 kg cae fuera de su tramo.
            $table->decimal('kilos_min', 12, 2);
            $table->decimal('kilos_max', 12, 2);
            $table->decimal('valor_bs', 10, 2)->comment('Lo que se cobra por ese cupo');

            $table->boolean('estado')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        // La escala única la exige el Request, no la base. Ver docs/MER.md.
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_aprovechamiento');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Productos hidrobiológicos — el catálogo del cuadro D de la guía: qué especie
| viaja y su tarifa por kilo en SIREB. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_hidrobiologicos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 120);

            // El precio por kilo NO vive acá: lo pone SIREB y la guía lo congela en su cuadro D.
            // Nullable: los productos sembrados no traen tarifa hasta que alguien la elige.
            $table->uuid('servicio_sireb')->nullable()->comment('Id del servicio en SIREB');
            $table->uuid('tarifa_sireb')->nullable()->comment('Id de la tarifa por kilo del producto');
            $table->json('sireb_historial')->nullable()->comment('Servicio y tarifa que tuvo antes, con desde/hasta');

            $table->boolean('estado')->default(true)->comment('Inactivo: no se elige en una guía nueva');

            $table->timestamps();
            $table->softDeletes();
        });

        // El nombre único lo exige el Request, no la base. Ver docs/MER.md.
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_hidrobiologicos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Productos hidrobiológicos — el catálogo del cuadro D de la guía: qué especie
| viaja y la tasa por kilo que se cobra. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_hidrobiologicos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 120);

            // La tasa por kilo: la guía cobra la suma de kilos × precio de su
            // cuadro D (guias_movimiento.monto). Mínimo 0,20, lo exige el Request.
            $table->decimal('precio_kg', 10, 2)->default(0);

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

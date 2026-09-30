<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Detalle de una guía — el cuadro D del papel, una fila por especie.
| Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guia_detalles', function (Blueprint $table) {
            $table->id();

            /*
             * CASCADE y no RESTRICT, al revés que el resto del dominio: el
             * detalle no tiene vida propia, es el cuerpo de la guía.
             *
             * ⚠️ Con SoftDeletes esto NO se dispara —`delete()` es un UPDATE—:
             * la baja del detalle la hace EmitirGuiaService a mano.
             */
            $table->foreignId('guia_movimiento_id')->index()->constrained('guias_movimiento')->cascadeOnDelete();

            // Del catálogo, y además COPIADOS: el papel entregado no cambia si
            // mañana se corrige el nombre o el precio del producto.
            $table->foreignId('producto_id')->index()->constrained('productos_hidrobiologicos')->restrictOnDelete();
            $table->string('especie', 120);

            // Las DIEZ columnas de tilde del cuadro, en una sola. Ver el enum.
            $table->string('condicion', 30)->comment('CondicionProducto');

            $table->decimal('cantidad_kg', 12, 2)->default(0)->comment('CANT. ADQUIRIDA');

            // El precio de SIREB al emitir, congelado; la suma de importes es `guias_movimiento.monto`.
            $table->decimal('precio_kg', 12, 2)->default(0)->comment('Copia del precio de SIREB');
            $table->uuid('sireb_tarifa_id')->nullable()->comment('Tarifa de SIREB de ese precio');
            $table->decimal('importe_total', 12, 2)->default(0)->comment('cantidad_kg × precio_kg');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guia_detalles');
    }
};

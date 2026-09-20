<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Recibos — la CABECERA del comprobante oficial de caja.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();

            /*
             * String y no entero: la serie lleva prefijo y año —REC-2026-0016— y
             * el año que viene el contador vuelve a 1. Se reserva con
             * CorrelativoService, que bloquea la fila del contador.
             */
            $table->string('numero_recibo', 40)->unique();

            $table->decimal('monto_total', 12, 2)->default(0)->comment('Suma congelada de los pagos que ampara');

            $table->text('concepto')->comment('Descripción unificada del cobro, tal como se imprime');

            // Se copian: el comprobante puede ir a nombre de un tercero, y tiene
            // que seguir diciendo lo mismo aunque la ficha se corrija después.
            $table->string('nit_ci_factura', 30);
            $table->string('nombre_factura', 160);

            /*
             * ÍNDICE, no columna: `created_at` ya la creó timestamps().
             */
            $table->index('created_at');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos');
    }
};

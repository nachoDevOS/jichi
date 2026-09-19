<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Recibos — la CABECERA del comprobante oficial de caja.
|
| El papel numerado que la persona se lleva. Agrupa uno o varios `pagos`, que
| pueden ser de trámites distintos:
|
|     recibo 0016 (180 Bs)  ──< pago  80 Bs → carnet
|                           ──< pago 100 Bs → aprovechamiento
|
| Es una tabla y no se arma al vuelo porque `numero_recibo` es un CORRELATIVO DE
| CAJA —el dato que no se puede derivar de otras tablas— y porque el comprobante
| tiene que ser INMUTABLE: por eso el nombre, el NIT y el total se COPIAN acá al
| emitir.
|
| Ver docs/MER.md.
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
             *
             * Único GLOBAL y NO parcial: es un correlativo que Contabilidad
             * audita. Un recibo dado de baja deja su número QUEMADO —el papel
             * salió— y la serie conserva el hueco, que es justamente lo que la
             * hace auditable.
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
             *
             * Lo gana el ORDEN del listado de recibos, que hace
             * `latest('created_at')` + paginado en cada carga: sin índice, cada
             * página ordena la tabla entera para devolver quince filas. Ver
             * ReciboController::index().
             *
             * Ese listado además filtra por rango con `whereDate()`, y eso NO
             * usa este índice —envuelve la columna en una función—. Se arregla
             * comparando contra instantes en vez de días; queda anotado.
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

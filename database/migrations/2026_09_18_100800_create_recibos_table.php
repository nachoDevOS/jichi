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
             * A NOMBRE DE QUIÉN SALE, por id y no copiando el nombre y la
             * cédula: un solo lugar donde corregir un apellido mal tipeado.
             * RESTRICT porque el comprobante respalda plata cobrada.
             */
            $table->foreignId('beneficiario_id')->constrained('beneficiarios')->restrictOnDelete();

            /*
             * CORRELATIVO CONTINUO: «000001», sin prefijo ni gestión, y no
             * reinicia en enero. String y no entero porque los ceros son parte
             * del número impreso. Lo reserva CorrelativoService, que bloquea la
             * fila del contador.
             */
            $table->string('numero_recibo', 40)->unique();

            $table->decimal('monto_total', 12, 2)->default(0)->comment('Suma congelada de los pagos que ampara');

            $table->text('concepto')->comment('Descripción unificada del cobro, tal como se imprime');

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

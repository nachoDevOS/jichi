<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Recibos — el comprobante oficial, uno por documento, emitido cuando SIREB
| confirma el pago. Copia la boleta de SIREB. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos', function (Blueprint $table) {
            // Por id y no copiando el nombre: un solo lugar donde corregir un
            // apellido. RESTRICT porque el comprobante respalda plata cobrada.
            $table->id();
            $table->foreignId('beneficiario_id')->constrained('beneficiarios')->restrictOnDelete();

            // El documento pagado. Único: un documento, un recibo.
            $table->string('recibible_type');
            $table->unsignedBigInteger('recibible_id');

            // Correlativo CONTINUO: «000001», sin prefijo ni gestión, no
            // reinicia en enero. String porque los ceros son parte del número.
            $table->string('numero_recibo', 40)->unique();

            $table->decimal('monto_total', 12, 2)->default(0)->comment('Lo pagado en SIREB, congelado');
            $table->text('concepto')->comment('Tal como se imprime');

            // El pago tal como lo validó SIREB, copiado: es lo que el papel imprime.
            $table->string('numero_boleta', 60)->nullable();
            $table->string('entidad_bancaria', 120)->nullable();
            $table->date('fecha_pago')->nullable()->comment('El día de la boleta');

            $table->unique(['recibible_type', 'recibible_id']);
            // El arqueo del día. `created_at` la crea timestamps().
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

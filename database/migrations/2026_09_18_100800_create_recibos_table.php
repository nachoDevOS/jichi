<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Recibos — la cabecera del comprobante oficial de caja. Ver docs/MER.md.
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

            // Correlativo CONTINUO: «000001», sin prefijo ni gestión, no
            // reinicia en enero. String porque los ceros son parte del número.
            $table->string('numero_recibo', 40)->unique();

            $table->decimal('monto_total', 12, 2)->default(0)->comment('Suma congelada de sus pagos');
            $table->text('concepto')->comment('Tal como se imprime');

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

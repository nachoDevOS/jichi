<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->string('nro_comprobante', 40)->unique()->comment('PAG-2026-0001');
            $table->foreignId('tramite_id')->constrained('tramites')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()
                ->comment('Cajero que registro el pago');
            $table->decimal('monto_bruto', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('monto', 12, 2)->comment('Monto efectivamente cobrado');
            $table->string('forma_pago', 20)->default('efectivo')->comment('efectivo | qr | transferencia');
            $table->string('referencia')->nullable()->comment('Nro de transaccion QR o transferencia bancaria');
            $table->string('banco')->nullable();
            $table->timestamp('fecha_pago');
            $table->string('estado', 20)->default('pagado')->comment('pagado | anulado');
            $table->string('pdf_path')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fecha_pago', 'estado']);
            $table->index(['user_id', 'fecha_pago']);
            $table->index(['tramite_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};

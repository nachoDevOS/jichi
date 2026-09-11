<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramites', function (Blueprint $table) {
            /*
             * El trámite se identifica por su id y nada más.
             *
             * Antes había además una columna `codigo` con un correlativo
             * legible —TRA-PESCA-2026-0001—. Se retiró: eran dos números para
             * la misma fila, y el que se imprime en la credencial es otro, el
             * de `datos_adicionales.registro`, que lo asigna
             * RegistroPescadorService.
             *
             * La migración se editó en su lugar en vez de agregar una que
             * borre la columna, por lo mismo que se hizo al retirar Caja: el
             * sistema todavía no está en producción y la base se vuelve a
             * sembrar entera.
             */
            $table->id();
            $table->foreignId('solicitante_id')->constrained('solicitantes')->restrictOnDelete();
            $table->foreignId('tipo_tramite_id')->constrained('tipos_tramite')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()
                ->comment('Operador que recepciono el tramite');
            /*
             * Los cuatro estados del circuito. Ver App\Enums\EstadoTramite,
             * que es donde viven las reglas de qué salto vale.
             *
             * Antes eran seis: estaban también «recibido» y «emitido». Se
             * retiraron —y la migración se editó en su lugar, como cuando se
             * quitó Caja, porque el sistema todavía no está en producción y la
             * base se vuelve a sembrar entera—. Que el documento esté impreso
             * no es un estado del trámite: se mira si existe la fila en
             * `documentos`.
             */
            $table->string('estado', 30)->default('en_revision')
                ->comment('en_revision | aprobado | entregado | rechazado');
            $table->decimal('monto_total', 12, 2)->default(0);
            $table->decimal('monto_pagado', 12, 2)->default(0);
            $table->foreignId('exencion_id')->nullable()->constrained('exenciones')->nullOnDelete();
            $table->jsonb('datos_adicionales')->nullable()
                ->comment('Campos propios del tipo de tramite: embarcacion, arte de pesca, etc.');
            $table->jsonb('requisitos_validados')->nullable();
            $table->text('observaciones')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->timestamp('fecha_recepcion')->nullable();
            $table->timestamp('fecha_revision')->nullable();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamp('fecha_emision')->nullable();
            $table->timestamp('fecha_entrega')->nullable();
            $table->string('modo_entrega', 20)->nullable()->comment('fisica | digital');
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('entregado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'created_at']);
            $table->index(['solicitante_id', 'estado']);
            $table->index(['tipo_tramite_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramites');
    }
};

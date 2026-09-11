<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            /*
             * NO HAY NUMERO DE DOCUMENTO.
             *
             * Habia una columna `nro_documento` con un correlativo legible
             * —DOC-COMER-GUT-2026-0013—. Se retiro por lo mismo que se retiro
             * el codigo del tramite: eran dos identificadores para la misma
             * fila, y el que de verdad usa el ciudadano es el codigo de
             * verificacion, el que viaja en el QR y el que se tipea en la
             * pantalla publica.
             *
             * La migracion se edito en su lugar en vez de agregar una que
             * borre la columna, porque el sistema todavia no esta en
             * produccion y la base se vuelve a sembrar entera.
             */
            /*
             * El codigo opaco que viaja dentro del QR publico.
             *
             * Hoy son 16 caracteres (ver EmisionDocumentoService), pero la
             * columna se deja en 32: es texto corto, no cuesta nada, y dejar
             * margen evita tener que migrar la tabla el dia que el codigo se
             * alargue de nuevo.
             */
            $table->string('codigo_verificacion', 32)->unique()
                ->comment('Codigo opaco embebido en el QR publico: 16 caracteres');
            $table->foreignId('tramite_id')->constrained('tramites')->restrictOnDelete();
            $table->string('tipo', 30)->comment('certificacion | credencial | permiso | licencia');
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable()->comment('NULL = sin vencimiento');
            $table->string('estado', 20)->default('vigente')->comment('vigente | vencido | anulado');
            $table->string('pdf_path')->nullable();
            $table->string('hash_pdf', 64)->nullable()->comment('SHA-256 del PDF emitido');
            $table->jsonb('datos_snapshot')->nullable()
                ->comment('Copia inmutable de los datos impresos en el documento');
            $table->unsignedSmallInteger('veces_verificado')->default(0);
            $table->timestamp('ultima_verificacion_at')->nullable();
            $table->foreignId('emitido_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'fecha_vencimiento']);
            $table->index(['tipo', 'fecha_emision']);
            $table->index('tramite_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};

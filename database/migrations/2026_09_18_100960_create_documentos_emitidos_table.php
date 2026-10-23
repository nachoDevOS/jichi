<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Documentos emitidos — el PDF de cada papel entregado, guardado tal como salió.
| Imprimir entrega este archivo en vez de volver a armarlo. Polimórfico como
| `codigos`: autorización, carnet, faena, guía y recibo. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_emitidos', function (Blueprint $table) {
            $table->id();

            // Polimórfico y sin clave foránea, igual que `codigos` y `recibos`.
            $table->string('emitible_type');
            $table->unsignedBigInteger('emitible_id');

            $table->string('disco', 20)->comment('s3 en producción; local en desarrollo sin s3');
            $table->string('ruta')->comment('Dentro del disco, privado: se sirve a través de Jichi');
            $table->char('sha256', 64)->comment('Huella del archivo: prueba que nadie lo tocó');
            $table->unsignedInteger('bytes');
            $table->string('motivo', 500)->nullable()->comment('Por qué se regeneró; null en la primera emisión');

            $table->timestamp('generado_en');

            $table->timestamps();
            $table->softDeletes();
        });

        // Un PDF vigente por papel. Parcial: regenerar da de baja el anterior y lo deja de historial.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX documentos_emitidos_uno_por_papel
                ON documentos_emitidos (emitible_type, emitible_id)
                WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_emitidos');
    }
};

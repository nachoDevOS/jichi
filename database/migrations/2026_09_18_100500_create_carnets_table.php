<?php

use App\Enums\EstadoCarnet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Carnets — la credencial física que se entrega en ventanilla.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carnets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('beneficiario_id')->constrained('beneficiarios')->restrictOnDelete();
            $table->foreignId('asociacion_id')->constrained('asociaciones')->restrictOnDelete();
            $table->foreignId('tipo_carnet_id')->constrained('tipos_carnet')->restrictOnDelete();

            /*
             * NULLABLE PORQUE SOLO EL PESCADOR LLEVA CUPO, y eso lo dice
             * `TipoActor::requiereAprovechamiento()`, NUNCA un match sobre el
             * nombre del tipo de carnet —que es un catálogo que edita la unidad—.
             */
            $table->foreignId('aprovechamiento_id')->nullable()
                ->constrained('aprovechamientos_pesq')->nullOnDelete()
                ->comment('Solo si tipo_actor = pescador');

            $table->string('tipo_actor', 20)->comment('pescador | comercializador');

            /*
             * Único GLOBAL, y en los dos sentidos:
             */
            $table->string('codigo_carnet', 40)->unique();

            $table->string('estado', 20)->default(EstadoCarnet::Activo->value);

            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');

            // «Los carnets de esta persona», que es como entra siempre la ficha.
            $table->index(['beneficiario_id', 'estado']);

            // Los listados filtran por tipo de actor y por gremio.
            $table->index(['tipo_actor', 'estado']);
            $table->index('asociacion_id');

            // Para el comando diario que marca los vencidos.
            $table->index('fecha_vencimiento');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carnets');
    }
};

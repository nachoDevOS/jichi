<?php

use App\Enums\EstadoAsociacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Asociaciones — el gremio al que pertenece la persona.
|
| Catálogo: lo edita la unidad desde el panel y NUNCA se borra una fila. Para
| sacar una de circulación se pone `estado = inactivo`.
|
| Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asociaciones', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 160);
            // La sigla es lo que entra en el renglón angosto del carnet.
            $table->string('sigla', 20)->nullable()->comment('ASOPESCA, APESCAT...');

            // String y no ENUM nativo: regla 7 del proyecto.
            $table->string('estado', 20)->default(EstadoAsociacion::Activo->value);

            $table->index(['estado', 'nombre']);

            $table->timestamps();
            $table->softDeletes();
        });

        /*
         * Dos asociaciones no pueden llamarse igual.
         *
         * Índice PARCIAL y no `unique()` con `deleted_at` adentro: en SQL
         * NULL != NULL, así que ese unique no bloquearía nada. Con el WHERE, el
         * nombre queda libre recién al dar la fila de baja.
         */
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX asociaciones_nombre_unico
                ON asociaciones (nombre)
                WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('asociaciones');
    }
};

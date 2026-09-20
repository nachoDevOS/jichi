<?php

use App\Enums\EstadoAsociacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Asociaciones — el gremio al que pertenece la persona.
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

<?php

use App\Enums\EstadoAsociacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Asociaciones — el gremio al que pertenece la persona. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asociaciones', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 160);
            $table->string('sigla', 20)->nullable()->comment('Lo que entra en el renglón del carnet: ASOPESCA');

            // Contacto y respaldo: nada del sistema DECIDE con estos datos. Las
            // claves las fija Asociacion::CAMPOS.
            $table->json('datos')->nullable()->comment('Personería, representante, contacto…');

            $table->string('estado', 20)->default(EstadoAsociacion::Activo->value);

            // Compuesto y no inline: ordena por nombre DENTRO de un estado.
            $table->index(['estado', 'nombre']);

            $table->timestamps();
            $table->softDeletes();
        });

        // El nombre único lo exige el Request, no la base: sería un índice
        // parcial, que solo existe en PostgreSQL. Ver docs/MER.md.
    }

    public function down(): void
    {
        Schema::dropIfExists('asociaciones');
    }
};

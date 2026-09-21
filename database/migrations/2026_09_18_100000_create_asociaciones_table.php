<?php

use App\Enums\EstadoAsociacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

            /*
             * EL RESTO DE LA FICHA DEL GREMIO, EN JSON.
             *
             * Va en una sola columna y no en ocho porque nada del sistema
             * DECIDE con estos datos: son de contacto y de respaldo, se
             * muestran y se imprimen. Una columna por dato obligaría a una
             * migración cada vez que la unidad pide guardar uno más.
             *
             * Las claves las fija `Asociacion::CAMPOS`, que es lo que el
             * formulario del catálogo dibuja: sin esa lista, un JSON abierto
             * termina con «telefono», «teléfono» y «tel» en la misma tabla.
             */
            $table->json('datos')->nullable()->comment('Personería, representante, contacto…');

            // String y no ENUM nativo: regla 7 del proyecto.
            $table->string('estado', 20)->default(EstadoAsociacion::Activo->value);

            $table->index(['estado', 'nombre']);

            $table->timestamps();
            $table->softDeletes();
        });

        // SIN INDICE UNICO EN LA BASE, a propósito: era un índice PARCIAL
        // —`WHERE deleted_at IS NULL`— que solo existe en PostgreSQL. La
        // unicidad la exige el Request del catálogo, que alcanza: esto se edita
        // desde el panel una vez cada tanto, sin dos ventanillas a la vez.
    }

    public function down(): void
    {
        Schema::dropIfExists('asociaciones');
    }
};

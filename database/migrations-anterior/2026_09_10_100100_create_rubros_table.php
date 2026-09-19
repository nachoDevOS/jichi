<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Rubros — el catálogo de actividades
|--------------------------------------------------------------------------
|
| Pescador, Comercializador. Cada actividad emite su propio carnet anual.
|
| LAS TRES BANDERAS DE ABAJO MANDAN COMPORTAMIENTO, Y SE PREGUNTAN POR COLUMNA
| Y NUNCA POR EL NOMBRE DEL RUBRO: el catálogo lo edita la unidad desde el
| panel —el mismo rubro figura como «Pescador» o como «Faena» según quién lo
| cargó— y los rubros nuevos entran por ordenanza, sin pasar por código.
|
| Tabla chica, de lectura constante y escritura rarísima.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubros', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50);
            $table->text('descripcion')->nullable();

            // Se COPIA a `tramites.monto_requerido` al registrar la solicitud.
            // Así, subir la tarifa no deja impagos los expedientes ya abiertos.
            $table->decimal('costo', 10, 2)->default(0)
                ->comment('Tarifa vigente en Bs. Se copia al tramite al registrarlo');

            /*
             * ¿SE AUTORIZA POR VOLUMEN? La pesca sí —tantos kilos, contrastables
             * contra las guías—; la comercialización no.
             *
             * De acá cuelgan cuatro cosas: el formulario muestra u oculta el
             * campo del cupo, la validación lo exige o lo PROHÍBE, la ficha lo
             * muestra o no, y el plástico imprime el renglón CUPO.
             */
            $table->boolean('requiere_capacidad')->default(false)
                ->comment('Si la actividad se autoriza por volumen (kilos)');

            /*
             * QUÉ PERMISO OPERATIVO CUELGA DE SUS CARNETS.
             *
             *     Pescador        ──▶ FAENAS   (una por salida)
             *     Comercializador ──▶ GUÍAS    (una por traslado)
             *
             * Son DOS banderas y no un solo `tipo_permiso` porque no son
             * excluyentes: una actividad piscícola necesitaría faena para la
             * cosecha y guía para trasladarla.
             *
             * DEFAULT FALSE en las tres: un rubro nuevo no habilita nada hasta
             * que alguien lo tilde.
             */
            $table->boolean('emite_faenas')->default(false)
                ->comment('Si de sus carnets cuelgan permisos por faena de pesca');
            $table->boolean('emite_guias')->default(false)
                ->comment('Si de sus carnets cuelgan guias unicas de transporte');

            // 'activo' | 'inactivo'. Ver App\Enums\EstadoRubro. No se borra
            // nunca: los carnets y trámites históricos apuntan acá.
            $table->string('estado', 30)->default('activo')->comment('activo | inactivo');

            $table->timestamps();

            // Dos rubros con el mismo nombre serían indistinguibles en el
            // formulario y el operador elegiría a ciegas.
            $table->unique('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubros');
    }
};

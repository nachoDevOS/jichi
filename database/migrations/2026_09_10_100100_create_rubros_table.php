<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Rubros — el catálogo de actividades que habilita un carnet
|--------------------------------------------------------------------------
|
| Un rubro es una actividad autorizada: pesca artesanal, transporte de
| producto, venta en mercado. El carnet es uno solo por persona y gestión, y
| sobre él se van habilitando rubros (ver `carnet_rubro`).
|
| Es una tabla chica y de lectura constante: la carga el seeder y la
| administra un usuario con permiso `rubros.gestionar`.
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

            /*
             * EL COSTO NO ESTABA EN EL SCRIPT SQL Y HACE FALTA.
             *
             * La Regla C pide validar «si la sumatoria de los pagos cubre el
             * costo total requerido» del trámite. Ese costo tiene que salir de
             * algún lado, y el lugar natural es el rubro: cada actividad tiene
             * su tarifa según ordenanza.
             *
             * El trámite NO lee esta columna al momento de aprobar: se la copia
             * a `tramites.monto_requerido` cuando se registra la solicitud.
             * Así, si mañana sube la tarifa, los expedientes ya abiertos siguen
             * debiendo lo que decía el papel el día que se presentaron.
             */
            $table->decimal('costo', 10, 2)->default(0)
                ->comment('Tarifa vigente en Bs. Se copia al tramite al registrarlo');

            // 'activo' | 'inactivo'. Ver App\Enums\EstadoRubro.
            //
            // Se da de baja cambiando el estado y no borrando la fila: los
            // carnets y trámites históricos apuntan acá y no pueden quedar
            // huérfanos. Un rubro inactivo no aparece en el formulario de
            // solicitud, pero los carnets que ya lo tienen lo siguen mostrando.
            $table->string('estado', 30)->default('activo')->comment('activo | inactivo');

            $table->timestamps();

            // Dos rubros con el mismo nombre serían indistinguibles en el
            // formulario de solicitud, y el operador elegiría a ciegas.
            $table->unique('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubros');
    }
};

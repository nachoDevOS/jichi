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

            /*
             * ¿ESTA ACTIVIDAD LLEVA CUPO EN KILOS?
             *
             * No todas. La pesca —«Pescador», «Faena»— se autoriza por volumen:
             * tantos kilos por temporada, y ese número se contrasta contra las
             * guías de transporte. La comercialización no: habilita a trasladar
             * y vender, sin tope propio.
             *
             * ------------------------------------------------------------------
             *  ES UNA COLUMNA Y NO UN `match` SOBRE EL NOMBRE
             * ------------------------------------------------------------------
             *
             * La tentación es preguntar `$rubro->nombre === 'Pescador'`. No
             * sirve, por dos motivos concretos:
             *
             *   - el catálogo lo edita la unidad desde el panel, y el mismo
             *     rubro figura como «Pescador» o como «Faena» según quién lo
             *     cargó. Un `match` por nombre deja de funcionar el día que
             *     alguien corrige una tilde;
             *   - los rubros se agregan por ordenanza. El que venga mañana
             *     —«Acuicultor»— tendría que pasar por código para decir si
             *     lleva cupo, cuando es un dato del catálogo.
             *
             * De qué depende: el formulario de trámite muestra u oculta el campo
             * del cupo, la validación lo exige o lo prohíbe, y el plástico
             * imprime el renglón CUPO o le da la tira entera al rubro.
             *
             * DEFAULT FALSE a propósito: un rubro nuevo no pide cupo hasta que
             * alguien diga que sí. Al revés, el que se olvide de destildarlo
             * obliga a ventanilla a inventar un número para poder guardar.
             */
            $table->boolean('requiere_capacidad')->default(false)
                ->comment('Si la actividad se autoriza por volumen (kilos)');

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

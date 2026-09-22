<?php

use App\Enums\EstadoGuia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Guías de movimiento — el amparo de UN traslado de producto.
|
| Calca el talonario «Guía Única de Transporte de Productos Ictícolas» del
| SEDAG, bloque por bloque. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guias_movimiento', function (Blueprint $table) {
            $table->id();

            /*
             * LA GUÍA CUELGA DEL CARNET DE COMERCIALIZADOR. La persona se
             * alcanza por él —`carnets.beneficiario_id`— y por eso acá NO hay
             * un beneficiario suelto: con las dos claves, una guía podía quedar
             * a nombre de alguien distinto del titular de su propio carnet.
             * Mismo criterio que `permisos_faena`.
             */
            $table->foreignId('carnet_id')->constrained('carnets')->restrictOnDelete();

            // La asociación SÍ se copia: es el aval que va impreso en el papel,
            // y un cambio de gremio posterior no puede reescribir lo entregado.
            $table->foreignId('asociacion_id')->constrained('asociaciones')->restrictOnDelete();

            // CORRELATIVO GLOBAL Y CONTINUO, lo genera el sistema: el talonario
            // de papel es uno solo para toda la unidad y no reinicia por año.
            $table->unsignedInteger('numero_guia')->comment('Correlativo global del talonario: 000308');

            // Copia congelada del arancel: una suba por resolución no puede
            // mover lo que dice un papel ya entregado. Ver `permisos_faena`.
            $table->decimal('monto', 10, 2)->default(0);

            //  BLOQUE B DEL PAPEL — la ubicación, ida y vuelta
            $table->string('origen', 160);
            $table->string('origen_departamento', 100)->nullable();
            $table->string('origen_provincia', 100)->nullable();
            $table->string('origen_distrito', 100)->nullable()->comment('Distrito o cuenca');

            $table->string('destino', 160);
            $table->string('destino_departamento', 100)->nullable();
            $table->string('destino_provincia', 100)->nullable();
            $table->string('destino_distrito', 100)->nullable();

            /*
             * BLOQUE C — el medio y el vehículo. Texto libre y nullable por lo
             * mismo que los renglones de la faena: el formulario se llena a
             * mano y llega incompleto, y un catálogo cerrado obligaría a dar de
             * alta una embarcación con el comerciante esperando.
             */
            $table->string('medio_transporte', 20)->nullable()->comment('MedioTransporte: casillero 10');
            $table->string('tipo_transporte', 25)->nullable()->comment('TipoTransporte: renglones a/b/c del bloque C');
            $table->string('transporte_nombre', 150)->nullable()->comment('Nombre o tipo del vehículo');
            $table->string('transporte_placa', 50)->nullable();
            $table->decimal('transporte_capacidad_kg', 12, 2)->nullable()->comment('Cap. máxima');

            /*
             * LA SUMA DEL CUADRO D, guardada. Se recalcula al guardar el
             * detalle en vez de sumarse al leer: el listado la muestra en cada
             * fila y derivarla sería un agregado por fila.
             */
            $table->decimal('peso_total_kg', 12, 2)->default(0);

            $table->boolean('es_piscicultura')->default(false)
                ->comment('true = producto de criadero: el arancel se cobra al 50%');

            $table->text('observaciones')->nullable();

            // NACE PENDIENTE: la guía se cobra y se firma como el carnet y la
            // faena, así que no ampara nada hasta que la aprueban.
            $table->string('estado', 20)->default(EstadoGuia::Pendiente->value);

            // El día que el comerciante la pidió en ventanilla. Puede ser
            // pasada: sirve para poner al día lo que se tramitó en papel.
            $table->date('fecha_solicitud');

            /*
             * LAS DOS LAS ESCRIBE LA APROBACIÓN, y por eso son nullable: hasta
             * la firma esto es una solicitud y no hay nada que venza.
             *
             * `dateTime` y no `date`: los cinco días se cuentan desde la HORA de
             * emisión. Una guía de las 18:00 del lunes vence a las 18:00 del
             * sábado, no a la medianoche del viernes. Por eso a React van con
             * toIso8601String() —son MOMENTOS— y no con toDateString().
             */
            $table->dateTime('fecha_emision')->nullable();
            $table->dateTime('fecha_vencimiento')->nullable()->comment('Máximo 5 días desde fecha_emision');

            /*
             * ÚNICO GLOBAL y no parcial: la hoja del talonario se gastó. Dar de
             * baja la fila no devuelve el número, que está impreso en un papel
             * que el comerciante se llevó.
             */
            $table->unique('numero_guia');

            $table->index(['carnet_id', 'estado']);
            $table->index('asociacion_id');

            // Para el comando diario que cierra las que se pasaron de fecha.
            $table->index(['estado', 'fecha_vencimiento']);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guias_movimiento');
    }
};

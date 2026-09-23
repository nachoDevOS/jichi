<?php

use App\Enums\EstadoGuia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Guías de movimiento — el amparo de UN traslado de producto.
| Calca la «Guía Única de Transporte» del SEDAG. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guias_movimiento', function (Blueprint $table) {
            $table->id();

            // SU ÚNICA CLAVE HACIA LA PERSONA. Con un beneficiario suelto
            // además, la guía podía quedar a nombre de otro. Igual que faenas.
            $table->foreignId('carnet_id')->constrained('carnets')->restrictOnDelete();

            // La asociación SÍ se copia: es el aval impreso, y un cambio de
            // gremio no puede reescribir lo entregado.
            $table->foreignId('asociacion_id')->index()->constrained('asociaciones')->restrictOnDelete();

            // Correlativo global y continuo, lo genera el sistema. Único y no
            // parcial: la hoja del talonario se gastó.
            $table->unsignedInteger('numero_guia')->unique()->comment('Del talonario: 000308');

            $table->decimal('monto', 10, 2)->default(0)->comment('Copia congelada del arancel');

            // BLOQUE B — la ubicación, ida y vuelta.
            $table->string('origen', 160);
            $table->string('origen_departamento', 100)->nullable();
            $table->string('origen_provincia', 100)->nullable();
            $table->string('origen_distrito', 100)->nullable()->comment('Distrito o cuenca');
            $table->string('destino', 160);
            $table->string('destino_departamento', 100)->nullable();
            $table->string('destino_provincia', 100)->nullable();
            $table->string('destino_distrito', 100)->nullable();

            // BLOQUE C — el medio y el vehículo. Nullable y texto libre: el
            // papel llega incompleto y no hay padrón de embarcaciones.
            $table->string('medio_transporte', 20)->nullable()->comment('MedioTransporte: casillero 10');
            $table->string('tipo_transporte', 25)->nullable()->comment('TipoTransporte: renglones a/b/c');
            $table->string('transporte_nombre', 150)->nullable();
            $table->string('transporte_placa', 50)->nullable();
            $table->decimal('transporte_capacidad_kg', 12, 2)->nullable()->comment('Cap. máxima');

            // La suma del cuadro D, guardada: el listado la muestra por fila y
            // derivarla sería un agregado por fila.
            $table->decimal('peso_total_kg', 12, 2)->default(0);

            $table->boolean('es_piscicultura')->default(false)->comment('true = criadero: arancel al 50%');
            $table->text('observaciones')->nullable();

            $table->string('estado', 20)->default(EstadoGuia::Pendiente->value);

            // Puede ser pasada: sirve para poner al día lo tramitado en papel.
            $table->date('fecha_solicitud');

            /*
             * LAS DOS LAS ESCRIBE LA APROBACIÓN, y por eso son nullable: hasta
             * la firma no hay nada que venza.
             *
             * `dateTime` y no `date`: los cinco días corren desde la HORA de
             * emisión, así que a React van con toIso8601String().
             */
            $table->dateTime('fecha_emision')->nullable();
            $table->dateTime('fecha_vencimiento')->nullable()->comment('Máximo 5 días desde la emisión');

            $table->index(['carnet_id', 'estado']);

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

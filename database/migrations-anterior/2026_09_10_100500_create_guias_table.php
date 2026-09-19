<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Guías — la Guía Única de Transporte de Productos Ictícolas
|--------------------------------------------------------------------------
|
|     carnet (Comercializador, 2026) ──< guia ──< guia
|
| Es el papel que acompaña a la carga. Acá va SOLO LA CABECERA —quién, desde
| dónde, hasta dónde y en qué—; la carga, especie por especie, está en
| `guia_detalles`.
|
| Misma forma que las faenas: cuelga del carnet, se emiten muchas por gestión,
| y que el rubro emita guías lo comprueba el servicio.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guias', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete: la guía acompañó carga real y queda consultable.
            $table->foreignId('carnet_id')->constrained('carnets')->restrictOnDelete();

            // Número del talonario de papel, lo tipea el operador. Único, por lo
            // mismo que en las faenas: en un control de carretera no habría cómo
            // saber cuál de dos guías iguales vale.
            $table->string('nro_guia', 50)->unique()
                ->comment('Numero preimpreso del talonario fisico. Lo tipea el operador');

            $table->string('nro_recibo', 50)->nullable()
                ->comment('Numero del recibo de caja, tal como figura en el papel');

            /*
             * ORIGEN Y DESTINO, cuatro campos cada uno como el formulario.
             *
             * Planos y no contra un catálogo de localidades, por lo mismo que
             * `beneficiarios.ciudad`: ese padrón no existe en la unidad y
             * exigirlo frenaría la ventanilla. Nullable porque el papel llega
             * incompleto seguido.
             */
            $table->string('origen_lugar', 150)->nullable();
            $table->string('origen_depto', 100)->nullable();
            $table->string('origen_provincia', 100)->nullable();
            $table->string('origen_distrito', 100)->nullable();

            $table->string('destino_lugar', 150)->nullable();
            $table->string('destino_depto', 100)->nullable();
            $table->string('destino_provincia', 100)->nullable();
            $table->string('destino_distrito', 100)->nullable();

            /*
             * Ver App\Enums\TipoTransporte. OBLIGATORIO porque dice quién
             * controla y dónde: la naval en el río, un retén en la carretera.
             * Sin el dato, la guía no le dice a nadie dónde mirar.
             */
            $table->string('tipo_transporte', 30)->comment('fluvial | aerea | terrestre');

            // Texto libre: no hay padrón de transportistas.
            $table->string('transporte_nombre', 150)->nullable();
            $table->string('transporte_placa', 50)->nullable();

            // Dato del VEHÍCULO, no de la autorización: sirve para notar que la
            // carga declarada no entra. Nullable: de una canoa nadie la sabe.
            $table->decimal('capacidad_maxima', 10, 2)->nullable()
                ->comment('Capacidad del vehiculo en kilos. Dato del transporte, no del permiso');

            $table->text('observaciones')->nullable();

            // Ver App\Enums\EstadoPermiso. Sin borrado, como las faenas.
            $table->string('estado', 30)->default('emitido')->comment('emitido | anulado');

            $table->timestamps();

            $table->index(['carnet_id', 'estado']);

            // Para los reportes por período y el listado general.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guias');
    }
};

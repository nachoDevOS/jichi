<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Carnets — un documento por persona, POR RUBRO y por gestión
|--------------------------------------------------------------------------
|
|     UNA PERSONA TIENE COMO MÁXIMO UN CARNET POR RUBRO Y POR GESTIÓN.
|
| Es la regla que ordena el módulo, y la garantiza el índice único de abajo.
| Quien pesca y comercializa tiene DOS carnets en 2026, cada uno con su
| plástico, su firma y su cupo. El carnet ES la habilitación: no hay tabla
| intermedia.
|
| De ahí salen los dos tipos de trámite, que decide SolicitudCarnetService:
| sin carnet de ese rubro, EMISIÓN INICIAL; con carnet, ACTUALIZACIÓN.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carnets', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete: la baja de una ficha es lógica y el historial se
            // conserva. Ver Carnet::beneficiario(), que va con withTrashed().
            $table->foreignId('beneficiario_id')->constrained('beneficiarios')->restrictOnDelete();

            // La actividad que habilita. Es parte de la llave, y se imprime.
            // restrictOnDelete: el catálogo se inactiva, no se borra.
            $table->foreignId('rubro_id')->constrained('rubros')->restrictOnDelete();

            /*
             * LO ÚNICO QUE IDENTIFICA AL CARNET. No hay columna `codigo`.
             *
             * 16 caracteres al azar. Es identificador y llave de la verificación
             * pública a la vez, y puede serlo porque es IMPREDECIBLE —no es un
             * hash de los datos, que se podría recalcular—.
             *
             * Se genera al crear y NO se vuelve a tocar: si cambiara, el QR ya
             * impreso dejaría de funcionar.
             */
            $table->string('firma_validacion', 16)->unique();

            // Columna propia y no deducida de `fecha_emision`: un carnet emitido
            // el 2 de enero para la gestión anterior existe.
            $table->unsignedSmallInteger('gestion');

            // Copia congelada del trámite AL APROBAR. Va impresa en el plástico,
            // y una actualización posterior no puede cambiarla sola.
            $table->string('asociacion', 150)->nullable()
                ->comment('Copia de tramites.asociacion al aprobar');

            /*
             * CUPO AUTORIZADO. NO es redundante con `tramites.capacidad_kg`:
             *
             *     tramites.capacidad_kg  ──▶  lo PEDIDO en ese expediente
             *     carnets.capacidad_kg   ──▶  lo AUTORIZADO, lo que rige HOY
             *
             * Quien tiene 600 kg y pide 850 sigue rigiendo por 600 hasta que se
             * cobre y se firme. Con una sola columna el pedido pisaría al
             * vigente, y un rechazo no tendría a dónde volver.
             *
             * La escribe SOLO la aprobación —consolidarCarnet()—, así que un
             * carnet recién creado la tiene en NULL. decimal y no entero: nada
             * asegura que un cupo no se exprese con decimales.
             */
            $table->decimal('capacidad_kg', 10, 2)->nullable()
                ->comment('Cupo AUTORIZADO en kilos. Lo escribe solo la aprobacion');

            $table->date('fecha_emision');

            // Vence al CERRAR LA GESTIÓN, no a los N días: emitido en enero dura
            // casi doce meses, en diciembre unas semanas, los dos hasta el 31/12.
            $table->date('fecha_vencimiento');

            /*
             * Ver App\Enums\EstadoCarnet. Suspender el carnet ES suspender esa
             * actividad; los demás carnets de la persona siguen vigentes.
             *
             * OJO: puede mentir. `vencido` lo escribe un proceso por lote, así
             * que para saber si vale HOY se mira además `fecha_vencimiento`.
             */
            $table->string('estado', 30)->default('vigente')
                ->comment('vigente | suspendido | vencido | anulado');

            $table->timestamps();

            /*
             * LA RESTRICCIÓN QUE SOSTIENE TODO EL MÓDULO. Sin ella, dos
             * ventanillas atendiendo al mismo pescador en el mismo segundo
             * crearían dos carnets del mismo rubro.
             *
             * Unique normal y no parcial: acá no hay borrado lógico. Un carnet
             * mal emitido se anula y sigue ocupando su lugar en la gestión.
             *
             * El orden importa: sirve además para «los carnets de esta persona»
             * y «los de esta persona en este rubro».
             */
            $table->unique(
                ['beneficiario_id', 'rubro_id', 'gestion'],
                'carnets_beneficiario_rubro_gestion_unique',
            );

            // «Carnets vigentes de esta gestión» — listados y tablero.
            $table->index(['gestion', 'estado']);

            // «Cuántos carnets habilitan esta actividad» — el gráfico del tablero.
            $table->index(['rubro_id', 'gestion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carnets');
    }
};

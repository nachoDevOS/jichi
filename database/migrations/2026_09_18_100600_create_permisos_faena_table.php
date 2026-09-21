<?php

use App\Enums\EstadoFaena;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Permisos de faena — la autorización de UNA salida de pesca.
|
| Calca el talonario «PERMISO POR FAENA» del SEDAG. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos_faena', function (Blueprint $table) {
            $table->id();

            /*
             * LA FAENA CUELGA DEL CARNET Y DE NADA MÁS. El cupo se alcanza a
             * través de él —`carnets.aprovechamiento_id`— y por eso acá NO hay
             * una segunda FK: con las dos, un permiso podía quedar apuntando a
             * un cupo distinto del que respalda su carnet, y nada lo impedía.
             */
            $table->foreignId('carnet_id')->constrained('carnets')->restrictOnDelete();

            // CORRELATIVO GLOBAL Y CONTINUO, lo genera el sistema: el talonario
            // de papel es uno solo para toda la unidad y no reinicia por año.
            $table->unsignedInteger('numero_faena')->comment('Correlativo global del talonario: 000001');

            // Copia congelada del arancel. Ver `pagos`: una suba por resolución
            // no puede mover lo que dice un papel ya entregado.
            $table->decimal('monto', 10, 2)->default(15.00);

            // Decimal: con enteros, treinta faenas redondeando medio kilo cada
            // una desajustan el cupo en quince.
            $table->decimal('kilos_extraidos', 12, 2)->default(0);

            /*
             * LOS RENGLONES DEL PAPEL. Nullable no es descuido: el formulario
             * se llena a mano y llega incompleto. Texto libre porque no hay
             * padrón de embarcaciones ni de comandantes, y un catálogo cerrado
             * obligaría a dar de alta uno con el pescador esperando.
             */
            $table->string('embarcacion', 150)->nullable();
            $table->string('propietario', 150)->nullable();
            $table->string('comandante_barco', 150)->nullable();
            $table->string('matricula_naval', 50)->nullable();
            $table->string('nro_kardex', 50)->nullable();
            $table->string('region_desde', 150)->nullable();
            $table->string('region_hasta', 150)->nullable();

            // NACE PENDIENTE: la faena se cobra y se firma como el carnet y el
            // aprovechamiento, así que no autoriza nada hasta que la aprueban.
            $table->string('estado', 20)->default(EstadoFaena::Pendiente->value);

            // El día que el pescador la pidió en ventanilla. Puede ser pasada:
            // sirve para poner al día lo que se tramitó en papel.
            $table->date('fecha_solicitud');

            $table->date('fecha_salida');

            // El renglón «Fecha de desembarque» del papel: la ventana real de
            // ESTA salida, que el operador escribe y un control en el río mira.
            $table->date('fecha_desembarque');

            // Se guarda calculada en vez de derivarla al leer: si la resolución
            // cambia el plazo, los permisos ya emitidos tienen que seguir
            // venciendo cuando dice el papel que el pescador tiene en la mano.
            $table->date('fecha_limite')->comment('Techo: 1 mes desde fecha_salida');

            // La escribe la APROBACIÓN. En NULL mientras es una solicitud.
            $table->date('fecha_emision')->nullable();

            /*
             * ÚNICO GLOBAL y no parcial: la hoja del talonario se gastó. Dar de
             * baja la fila no devuelve el número, que está impreso en un papel
             * que el pescador se llevó.
             */
            $table->unique('numero_faena');

            /*
             * La consulta caliente del módulo: la suma de kilos consumidos del
             * cupo, que hoy llega por `carnets`. Ver AprovechamientoPesq::faenas().
             */
            $table->index(['carnet_id', 'estado']);

            // Para el comando diario que marca las vencidas.
            $table->index('fecha_limite');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos_faena');
    }
};

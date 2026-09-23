<?php

use App\Enums\EstadoFaena;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Permisos de faena — la autorización de UNA salida de pesca.
| Calca el talonario «PERMISO POR FAENA» del SEDAG. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos_faena', function (Blueprint $table) {
            $table->id();

            // SU ÚNICA CLAVE. El cupo se alcanza por `carnets.aprovechamiento_id`:
            // con dos claves, un permiso podía apuntar a un cupo que no es el suyo.
            $table->foreignId('carnet_id')->constrained('carnets')->restrictOnDelete();

            // Correlativo global y continuo, lo genera el sistema. Único y no
            // parcial: la hoja del talonario se gastó.
            $table->unsignedInteger('numero_faena')->unique()->comment('Del talonario: 000001');

            $table->decimal('monto', 10, 2)->default(15.00)->comment('Copia congelada del arancel');

            // Decimal: treinta faenas redondeando medio kilo desajustan el cupo
            // en quince.
            $table->decimal('kilos_extraidos', 12, 2)->default(0);

            // LOS RENGLONES DEL PAPEL. Nullable y texto libre: el formulario se
            // llena a mano y llega incompleto, y no hay padrón de embarcaciones.
            $table->string('embarcacion', 150)->nullable();
            $table->string('propietario', 150)->nullable();
            $table->string('comandante_barco', 150)->nullable();
            $table->string('matricula_naval', 50)->nullable();
            $table->string('nro_kardex', 50)->nullable();
            $table->string('region_desde', 150)->nullable();
            $table->string('region_hasta', 150)->nullable();

            $table->string('estado', 20)->default(EstadoFaena::Pendiente->value);

            // Puede ser pasada: sirve para poner al día lo tramitado en papel.
            $table->date('fecha_solicitud');
            $table->date('fecha_salida');
            $table->date('fecha_desembarque')->comment('La ventana real de ESTA salida');

            // Guardada y no derivada: si la resolución cambia el plazo, lo ya
            // emitido tiene que seguir venciendo cuando dice el papel.
            $table->date('fecha_limite')->index()->comment('Techo: 1 mes desde la salida');

            // La escribe la APROBACIÓN. En NULL mientras es una solicitud.
            $table->date('fecha_emision')->nullable();

            // La consulta caliente: los kilos consumidos del cupo.
            $table->index(['carnet_id', 'estado']);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos_faena');
    }
};

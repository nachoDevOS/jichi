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

            // Su única clave. El cupo se alcanza por `carnets.aprovechamiento_id`:
            // con dos claves, un permiso podía apuntar a un cupo que no es el suyo.
            $table->foreignId('carnet_id')->constrained('carnets')->restrictOnDelete();

            // Correlativo global y continuo, lo genera el sistema. Único y no
            // parcial: la hoja del talonario se gastó.
            $table->unsignedInteger('nro')->unique()->comment('Del talonario: 000001');

            $table->decimal('monto', 10, 2)->default(0)->comment('Precio de SIREB congelado al emitir');
            $table->uuid('sireb_tarifa_id')->nullable()->comment('Tarifa de SIREB de ese precio');

            // La liquidación en SIREB (ahí se paga). La clave se guarda ANTES de llamar. Ver MER.md.
            $table->uuid('sireb_idempotency_key')->nullable()->unique()->comment('Header Idempotency-Key');
            $table->uuid('sireb_liquidacion_id')->nullable()->comment('Id de la liquidación en SIREB');
            $table->string('sireb_codigo_publico', 40)->nullable()->comment('Con el que se paga en SIREB');
            $table->string('sireb_estado', 20)->nullable()->comment('EstadoLiquidacionSireb');
            $table->json('sireb_envio')->nullable()->comment('Lo enviado a SIREB y su respuesta o error');

            // Decimal: treinta faenas redondeando medio kilo desajustan el cupo
            // en quince.
            $table->decimal('kilos_extraidos', 12, 2)->default(0);

            // Los renglones del papel. Nullable y texto libre: el formulario se
            // llena a mano y llega incompleto, y no hay padrón de embarcaciones.
            $table->string('embarcacion', 150)->nullable();
            $table->string('propietario', 150)->nullable();
            $table->string('comandante_barco', 150)->nullable();
            $table->string('matricula_naval', 50)->nullable();
            $table->string('nro_kardex', 50)->nullable();
            $table->string('region_desde', 150)->nullable();
            $table->string('region_hasta', 150)->nullable();

            $table->string('estado', 20)->default(EstadoFaena::Pendiente->value);

            $table->date('fecha_solicitud');

            // Las dos las escribe la aprobación: la salida es el día de la firma y
            // el desembarque, salida + 30 días. En NULL mientras es una solicitud.
            $table->date('fecha_salida')->nullable();
            $table->date('fecha_desembarque')->nullable()->index()->comment('Techo: salida + 30 días');

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

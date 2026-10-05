<?php

use App\Enums\EstadoCarnet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Carnets — la credencial física que se entrega en ventanilla.
|
| Sin columna de código: la llave de verificación vive en `codigos`, compartida
| con los otros cuatro documentos. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carnets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('beneficiario_id')->constrained('beneficiarios')->restrictOnDelete();
            // `index()` ANTES de `constrained()`: después se lo queda el
            // ForeignKeyDefinition y no se crea. PostgreSQL no indexa solo las FK.
            $table->foreignId('asociacion_id')->index()->constrained('asociaciones')->restrictOnDelete();
            $table->foreignId('tipo_carnet_id')->constrained('tipos_carnet')->restrictOnDelete();

            // Nullable porque solo el pescador lleva cupo, y eso lo dice
            // TipoActor::requiereAprovechamiento(), nunca el nombre del tipo.
            $table->foreignId('aprovechamiento_id')->nullable()
                ->constrained('aprovechamientos_pesq')->nullOnDelete()
                ->comment('Solo si tipo_actor = pescador');

            $table->string('tipo_actor', 20)->comment('pescador | comercializador');

            // Copia congelada: un cambio de precio en SIREB no toca lo ya emitido.
            $table->decimal('monto', 10, 2)->default(0)->comment('Precio de SIREB al emitir');
            $table->uuid('sireb_tarifa_id')->nullable()->comment('Tarifa de SIREB de ese precio');

            // La liquidación en SIREB (ahí se paga). La clave se guarda ANTES de llamar. Ver MER.md.
            $table->uuid('sireb_idempotency_key')->nullable()->unique()->comment('Header Idempotency-Key');
            $table->uuid('sireb_liquidacion_id')->nullable()->comment('Id de la liquidación en SIREB');
            $table->string('sireb_codigo_publico', 40)->nullable()->comment('Con el que se paga en SIREB');
            $table->string('sireb_estado', 20)->nullable()->comment('EstadoLiquidacionSireb');
            $table->json('sireb_envio')->nullable()->comment('Lo enviado a SIREB y su respuesta o error');

            // Correlativo por gestión, asignado al aprobar. Índice y no único:
            // lo garantiza CorrelativoService. Ver MER.md.
            $table->unsignedInteger('nro')->nullable()->index()->comment('Correlativo anual, al aprobar');

            // Nullable: muchos carnets se cargan para poner al día lo emitido
            // en papel, donde esos escaneos no existen. El formulario sí los exige.
            $table->string('archivo_ci', 255)->nullable()->comment('Escaneo de la cédula');
            $table->string('archivo_asociacion', 255)->nullable()->comment('Carta del gremio');

            $table->string('estado', 20)->default(EstadoCarnet::Pendiente->value);

            $table->date('fecha_solicitud');
            $table->date('fecha_emision')->nullable()->comment('Se llena al aprobar');
            $table->date('fecha_vencimiento')->index()->comment('Índice: lo filtra el scope vigentes()');

            $table->index(['beneficiario_id', 'estado']);
            $table->index(['tipo_actor', 'estado']);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carnets');
    }
};

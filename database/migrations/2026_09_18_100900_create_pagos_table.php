<?php

use App\Enums\EstadoValidacionPago;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Pagos — el detalle de lo que se cobró, depósito por depósito. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();

            // Nullable: el depósito nace antes que el recibo. En el cupo y la
            // guía el papel sale al pasar a EN REVISIÓN; en Caja, en el acto.
            $table->foreignId('recibo_id')->nullable()->constrained('recibos')->cascadeOnDelete();

            // DOS columnas y no una: uno tipeó la boleta, el otro la comparó
            // contra el banco. `nullOnDelete`: borrar un usuario no borra el pago.
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();

            // Crea las dos columnas MÁS su índice, que resuelve «cuánto se pagó
            // de ESTE carnet». Sin clave foránea: la sostiene la aplicación.
            $table->morphs('pagable');

            // `parcial` porque el nombre dice la regla: se paga en cuotas. Lo
            // que se debe NO se guarda, lo calcula el trait Pagable al leer.
            $table->decimal('monto_parcial', 12, 2)->comment('Este abono, no el total');

            $table->string('nro_transaccion', 60)->comment('Número de la boleta del banco');
            $table->date('fecha_deposito')->comment('La que dice la boleta, no cuándo se cargó');
            $table->string('comprobante')->comment('Ruta; la sube StorageController');

            // El estado del CONTROL, no el del pago: dice si alguien miró la
            // boleta contra el extracto. Un observado sigue sumando.
            $table->string('estado_validacion', 20)
                ->default(EstadoValidacionPago::Pendiente->value)
                ->comment('pendiente | validado | observado');

            $table->string('observacion')->nullable()->comment('Por qué se observó');

            // Un MOMENTO, no un día: va con toIso8601String().
            $table->timestamp('validado_en')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // LA MISMA BOLETA NO SE CARGA DOS VECES. Parcial y no inline: con
        // `deleted_at` adentro el índice no bloquea nada (NULL != NULL).
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX pagos_nro_transaccion_unico
                ON pagos (nro_transaccion)
                WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};

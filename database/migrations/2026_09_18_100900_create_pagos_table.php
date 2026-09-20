<?php

use App\Enums\EstadoValidacionPago;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Pagos — el DETALLE de lo que se cobró, depósito por depósito.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();

            /*
             * NULLABLE: el depósito nace antes que el recibo. En el
             * aprovechamiento el papel es UNO por trámite y sale al pasar a EN
             * REVISIÓN; en Caja llega lleno desde el primer momento.
             */
            $table->foreignId('recibo_id')->nullable()->constrained('recibos')->cascadeOnDelete();

            /*
             * QUIÉN CARGÓ Y QUIÉN VALIDÓ, en dos columnas: uno tipeó la boleta,
             * el otro la comparó contra el banco. En una sola, «quién responde
             * por esta plata» deja de tener respuesta.
             *
             * `nullOnDelete`: borrar un usuario no se lleva el pago.
             */
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Quién cargó el depósito');

            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Quién controló la boleta contra el extracto');

            // `morphs` crea las dos columnas MÁS el índice (pagable_type,
            // pagable_id), que resuelve la consulta caliente: «cuánto se pagó de
            // ESTE carnet».
            $table->morphs('pagable');

            // Se llama `parcial` porque el nombre dice la regla: un trámite se
            // paga en cuotas. Lo que se debe NO se guarda: se calcula al leer
            // con el trait Pagable, o quedaría desfasado al corregir un abono.
            $table->decimal('monto_parcial', 12, 2)->comment('Este abono, no el total del trámite');

            /*
             *  NO HAY COLUMNA `metodo_pago`, Y NO ES UN OLVIDO
             */

            /*
             * EL NÚMERO DEL DEPÓSITO, ÚNICO GLOBAL.
             */
            $table->string('nro_transaccion', 60)->comment('Número de la boleta del banco');

            /*
             * LA FECHA QUE DICE LA BOLETA, que NO es cuándo se cargó.
             */
            $table->date('fecha_deposito')->comment('La que figura en la boleta, no cuándo se cargó');

            /*
             * LA FOTO O EL PDF DE LA BOLETA. Es una RUTA, no una dirección
             * completa: la arma App\Support\Archivos según el disco activo.
             */
            $table->string('comprobante')->comment('Ruta de la boleta; la sube StorageController');

            /*
             * EL ESTADO DEL CONTROL, que no es el del pago: dice si alguien miró
             * la boleta contra el extracto. Un OBSERVADO sigue sumando en
             * `montoPagado()`. El default se repite en `Pago::$attributes`.
             */
            $table->string('estado_validacion', 20)
                ->default(EstadoValidacionPago::Pendiente->value)
                ->comment('pendiente | validado | observado');

            // EL MOTIVO, escrito: sin él nadie sabe cómo levantar la
            // observación. Se limpia al corregir; lo que pasó queda en auditorías.
            $table->string('observacion')->nullable()->comment('Por qué se observó la boleta');

            // Un MOMENTO, no un día: va con toIso8601String().
            $table->timestamp('validado_en')->nullable()->comment('Cuándo se validó u observó');

            $table->timestamps();
            $table->softDeletes();
        });

        /*
         * LA MISMA BOLETA NO SE CARGA DOS VECES.
         */
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Pagos — el DETALLE de lo que se cobró, depósito por depósito.
|
| TODO pago es un DEPÓSITO BANCARIO: no hay efectivo ni QR en esta unidad. Por
| eso cada fila lleva sí o sí su número de boleta, su fecha y su archivo.
|
| Es POLIMÓRFICA porque se cobran tres cosas —carnet, cupo y guía— y las tres se
| pagan igual. Partida en tres tablas, `numero_recibo` dejaría de ser único
| global.
|
| EL COSTO: se pierde la clave foránea. El motor no puede exigir que
| `pagable_id` exista, porque no sabe en qué tabla buscarlo. La integridad la
| sostienen los RESTRICT de las otras tablas y la aplicación.
|
| Y no se precarga con `with('pagable.beneficiario')`: eso se IGNORA en silencio
| y el N+1 sigue ahí. Va con `morphWith`.
|
| Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();

            // CASCADE y no RESTRICT, al revés que en el resto del sistema: un
            // pago sin recibo no se puede imprimir ni entra en ningún arqueo.
            $table->foreignId('recibo_id')->constrained('recibos')->cascadeOnDelete();

            // `morphs` crea las dos columnas MÁS el índice (pagable_type,
            // pagable_id), que resuelve la consulta caliente: «cuánto se pagó de
            // ESTE carnet».
            $table->morphs('pagable');

            // Se llama `parcial` porque el nombre dice la regla: un trámite se
            // paga en cuotas. Lo que se debe NO se guarda: se calcula al leer
            // con el trait Pagable, o quedaría desfasado al corregir un abono.
            $table->decimal('monto_parcial', 12, 2)->comment('Este abono, no el total del trámite');

            /*
             * ====================================================================
             *  NO HAY COLUMNA `metodo_pago`, Y NO ES UN OLVIDO
             * ====================================================================
             *
             * En esta unidad NO se cobra en efectivo ni por QR: TODO pago es un
             * depósito bancario. Una columna con un solo valor posible no informa
             * nada —y peor, invita a suponer que algún día hubo otra cosa—.
             *
             * Por eso las tres columnas de abajo son OBLIGATORIAS: todo pago
             * tiene su número de boleta, su fecha y su papel. Cuando existían el
             * efectivo y el QR eran nullable, porque esos dos no traían boleta.
             */

            /*
             * EL NÚMERO DEL DEPÓSITO, ÚNICO GLOBAL.
             *
             * Es lo que impide cargar la misma boleta dos veces —contra el mismo
             * trámite o contra otro—, que es la forma más fácil de que un cupo
             * figure pagado sin que haya entrado la plata. Único global y no por
             * trámite: la boleta es una sola en el banco.
             */
            $table->string('nro_transaccion', 60)->comment('Número de la boleta del banco');

            /*
             * LA FECHA QUE DICE LA BOLETA, que NO es cuándo se cargó.
             *
             * Un depósito hecho el viernes puede registrarse el lunes, y el
             * arqueo tiene que poder mirar las dos cosas: `created_at` para
             * cuadrar el trabajo del día, y esta para cruzar contra el extracto
             * del banco.
             *
             * Es un DÍA y no un instante: va `date`, y a React con toDateString().
             */
            $table->date('fecha_deposito')->comment('La que figura en la boleta, no cuándo se cargó');

            /*
             * LA FOTO O EL PDF DE LA BOLETA. Es una RUTA, no una dirección
             * completa: la arma App\Support\Archivos según el disco activo.
             *
             * UNA POR PAGO y no por recibo: si la persona hizo dos depósitos,
             * son dos boletas distintas y cada una respalda su monto. Guardada
             * en el recibo, la segunda pisaría a la primera.
             *
             * La sube StorageController::file() —regla 11— que es el único que
             * aplica el tope de 3 MB y el nombre al azar.
             */
            $table->string('comprobante')->comment('Ruta de la boleta; la sube StorageController');

            $table->timestamps();
            $table->softDeletes();
        });

        /*
         * LA MISMA BOLETA NO SE CARGA DOS VECES.
         *
         * Es la forma más fácil de que un trámite figure pagado sin que haya
         * entrado la plata: cargar el mismo depósito contra dos cupos, o dos
         * veces contra el mismo. El índice lo impide en la base, que es donde
         * tiene que estar — dos ventanillas simultáneas pasarían cualquier
         * comprobación de la aplicación.
         *
         * Va PARCIAL para dejar fuera las filas dadas de baja: un cobro anulado
         * libera su boleta y se la puede volver a cargar bien. Ya no hace falta
         * excluir los NULL —como antes, por el efectivo— porque la columna es
         * obligatoria.
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

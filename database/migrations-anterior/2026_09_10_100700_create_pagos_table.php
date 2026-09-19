<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Pagos — los depósitos que cubren un trámite, una faena o una guía
|--------------------------------------------------------------------------
|
|     tramite ──┐
|     faena   ──┼──< pago   (pagable_type + pagable_id)
|     guia    ──┘
|
| ES DE 1 A N a propósito: se puede depositar todo junto o en cuotas, y cada
| depósito llega con su boleta. Un solo `monto_pagado` arriba obligaría a sumar
| a mano y perdería la traza de cada comprobante.
|
| POR QUÉ POLIMÓRFICA Y NO TRES TABLAS: el índice único de `nro_transaccion`.
| Partido en tres DEJARÍA de ser único, y la misma boleta podría pagar un
| trámite y una faena — que es justo el fraude que ese índice frena. Además
| habría que triplicar la subida de la boleta y la pantalla de depósitos.
|
| LO QUE CUESTA: se pierde la clave foránea. La base ya no puede garantizar que
| `pagable_id` apunte a algo que existe, ni impedir que se borre lo pagado. Por
| eso las tres tablas de destino van con restrictOnDelete hacia arriba.
|
| NO HAY COLUMNA `saldo`: es una resta y se calcula al leer. Guardada, quedaría
| desfasada el día que alguien corrija un pago sin recalcularla.
|
| --------------------------------------------------------------------------
|  CADA DEPÓSITO SE VALIDA, Y QUEDA ESCRITO QUIÉN Y CUÁNDO
| --------------------------------------------------------------------------
|
|     PENDIENTE ──▶ VALIDADO    (la boleta cuadra con el extracto)
|               └─▶ OBSERVADO   (no cuadra, con el motivo escrito)
|
| Quien carga el depósito NO es quien lo valida: `registrado_por` y
| `validado_por` son dos columnas distintas y el servicio no deja que sean la
| misma persona. Es el mismo criterio de «quien arma no firma» que ya rige para
| aprobar un trámite.
|
| Un trámite con algún depósito sin validar NO SE PUEDE APROBAR. Ver
| `Tramite::puedeAprobarse()`.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();

            // Qué se está pagando. En `pagable_type` va el nombre completo de la
            // clase: sin morphMap, para que un comando que no cargó el mapa no
            // devuelva null en silencio.
            $table->string('pagable_type')->comment('Tramite | Faena | Guia');
            $table->unsignedBigInteger('pagable_id');

            /*
             * ÚNICO EN TODO EL SISTEMA. La misma boleta cargada dos veces —el
             * operador duda si guardó y vuelve a dar clic— haría figurar el
             * trámite como pagado con la mitad del dinero. Es la única defensa
             * real: la comprobación en PHP no resiste dos peticiones simultáneas.
             *
             * Global y no por trámite, porque un mismo depósito tampoco puede
             * pagar dos cosas distintas.
             */
            $table->string('nro_transaccion', 50)->unique();

            $table->decimal('monto', 10, 2);

            // Ruta de la boleta escaneada. Puede ser local o dirección de s3
            // según el disco: se lee siempre con App\Support\Archivos::url().
            $table->string('urlFile')->comment('Comprobante escaneado del deposito');

            // La fecha del depósito en el banco, no la de carga: una boleta del
            // viernes se registra el lunes.
            $table->timestamp('fecha_pago')->useCurrent();

            $table->text('observaciones')->nullable();

            /*
             * ==================================================================
             *  QUIÉN LO CARGÓ Y QUIÉN LO VALIDÓ
             * ==================================================================
             *
             * Son DOS columnas y no una porque tienen que poder ser personas
             * distintas: el servicio se niega a validar un depósito cargado por
             * uno mismo.
             *
             * nullOnDelete en las dos: el usuario se puede dar de baja y el
             * depósito tiene que seguir siendo legible. Es el mismo patrón que
             * `auditorias.user_id` — lo que ya pasó no se borra con quien lo
             * hizo.
             *
             * Nullable también por lo viejo: los depósitos cargados antes de que
             * esto existiera no tienen a quién apuntar, y no se les puede
             * inventar un responsable.
             */
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Quien cargo el deposito en ventanilla');

            // 'pendiente' | 'validado' | 'observado'. Ver App\Enums\EstadoValidacionPago.
            //
            // Nace PENDIENTE: un depósito recién cargado no está revisado, y
            // arrancar en «validado» haría que el control no existiera.
            $table->string('estado_validacion', 30)->default('pendiente')
                ->comment('pendiente | validado | observado');

            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Quien reviso la boleta contra el extracto');

            // El instante de la revisión. Va aparte de `updated_at` porque esa
            // se mueve con cualquier corrección; esta dice exactamente cuándo se
            // controló el dinero, que es el dato que pide una auditoría.
            $table->timestamp('validado_at')->nullable();

            // Por qué no cuadra. Obligatorio al observar: es lo único que le
            // dice a ventanilla qué tiene que ir a corregir.
            $table->text('motivo_observacion')->nullable();

            $table->timestamps();

            /*
             * TRES COLUMNAS Y NO DOS. El par (type, id) es lo que consulta
             * Eloquent, y como es el prefijo izquierdo del índice, este también
             * le sirve. La tercera está porque la consulta real es «los pagos de
             * esto, del más viejo al más nuevo»: con `fecha_pago` adentro, el
             * motor los devuelve ya ordenados.
             */
            $table->index(['pagable_type', 'pagable_id', 'fecha_pago'], 'pagos_pagable_index');

            // «Qué depósitos faltan validar» — la cola de trabajo de quien
            // revisa, y el contador del tablero.
            $table->index('estado_validacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Carnets — un documento por persona y por gestión
|--------------------------------------------------------------------------
|
| El carnet es el documento anual. La regla que lo gobierna, y de la que
| cuelga todo el módulo, es esta:
|
|     UNA PERSONA TIENE COMO MÁXIMO UN CARNET POR GESTIÓN.
|
| De ahí salen los dos tipos de trámite:
|
|     - No tiene carnet de esta gestión  ->  EMISIÓN INICIAL (se crea el carnet)
|     - Ya tiene carnet de esta gestión  ->  ADICIÓN DE RUBRO (se reutiliza)
|
| Esa decisión la toma SolicitudCarnetService, pero quien la GARANTIZA es el
| índice único de abajo: si dos ventanillas atienden al mismo pescador en el
| mismo segundo, el servicio de las dos leería «no tiene carnet» y las dos
| intentarían crearlo. La base rechaza el segundo INSERT y la transacción se
| deshace. Sin ese índice quedarían dos carnets y el sistema perdería sentido.
|
| EL CARNET NO TIENE NÚMERO: se identifica por su `firma_validacion`, que es
| única. Ver el comentario de esa columna.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carnets', function (Blueprint $table) {
            $table->id();

            /*
             * restrictOnDelete: no se puede borrar de verdad a un beneficiario
             * que tiene carnets. La baja de una ficha es lógica (deleted_at) y
             * el historial se conserva; ver Carnet::beneficiario(), que lleva
             * withTrashed() justamente para que la ficha dada de baja siga
             * siendo legible desde el carnet.
             */
            $table->foreignId('beneficiario_id')->constrained('beneficiarios')->restrictOnDelete();

            /*
             * FIRMA DE VALIDACIÓN — lo ÚNICO que identifica al carnet.
             *
             * Dieciséis caracteres alfanuméricos generados al azar. No hay
             * columna `codigo`: se retiró a pedido, y con ella el correlativo
             * `CARNET-2026-0001` que existía antes.
             *
             * ------------------------------------------------------------------
             *  UNA FIRMA POR CARNET, NO POR RUBRO
             * ------------------------------------------------------------------
             *
             * El carnet es uno solo por persona y gestión, y su firma también:
             * agregarle rubros con una adición NO la cambia. Lo contrario haría
             * que el QR impreso dejara de funcionar cada vez que alguien suma una
             * actividad, y habría que reimprimir el plástico.
             *
             * ------------------------------------------------------------------
             *  ES IDENTIFICADOR Y LLAVE A LA VEZ
             * ------------------------------------------------------------------
             *
             * Antes hacían falta dos datos para consultar un carnet —el código
             * público y la firma secreta—. Ahora la firma cumple los dos papeles,
             * y eso está bien porque es IMPREDECIBLE: 16 caracteres alfanuméricos
             * son ~8 · 10^24 combinaciones. Junto con el throttle de la ruta
             * pública, adivinar una no es posible en la práctica.
             *
             * Lo que sí cambia es que la firma ya no es un secreto que se pueda
             * guardar: va impresa en el carnet y se muestra en el panel, porque
             * es el número por el que se pregunta. Quien la tenga puede consultar
             * ese carnet — igual que quien tiene el plástico en la mano.
             *
             * No es un hash de los datos a propósito. Un hash se puede recalcular
             * si alguien deduce la fórmula; un valor aleatorio guardado no.
             *
             * El índice ÚNICO es la garantía de que no se repita. Carnet::nuevaFirma()
             * comprueba antes de devolverla, pero entre ese SELECT y el INSERT
             * otra conexión podría meter la misma; el índice no deja.
             */
            $table->string('firma_validacion', 16)->unique();

            // El año. Es la mitad de la regla «un carnet por persona y gestión»
            // y por eso es una columna propia y no se deduce de fecha_emision:
            // un carnet emitido el 2 de enero para la gestión anterior existe.
            $table->unsignedSmallInteger('gestion');

            /*
             * LA ASOCIACIÓN QUE SE IMPRIME EN LA TARJETA — copia congelada.
             *
             * Se copia del trámite que emitió el carnet, igual que
             * `tramites.monto_requerido` copia la tarifa del rubro. El motivo es
             * el mismo: el carnet dice lo que decía el papel el día que se
             * emitió, y una adición de rubro presentada después con el
             * certificado de otra asociación no puede cambiar lo que ya está
             * impreso en un plástico que la persona tiene en el bolsillo.
             */
            $table->string('asociacion', 150)->nullable()
                ->comment('Copia de tramites.asociacion al emitir');

            $table->date('fecha_emision');

            /*
             * VENCE AL CERRAR LA GESTIÓN, NO A LOS N DÍAS.
             *
             * Sacado en enero dura casi doce meses, sacado en diciembre dura
             * unas semanas, y los dos vencen el 31 de diciembre. Es la forma en
             * que se maneja el talonario en papel y el sistema la copia.
             */
            $table->date('fecha_vencimiento');

            // 'vigente' | 'vencido' | 'anulado'. Ver App\Enums\EstadoCarnet.
            //
            // OJO: el estado guardado puede mentir. Se actualiza por lote, así
            // que entre corrida y corrida un carnet vencido ayer sigue diciendo
            // «vigente». Para decidir si vale HOY se mira fecha_vencimiento, que
            // nunca miente. Ver Carnet::estaVigente().
            $table->string('estado', 30)->default('vigente')->comment('vigente | vencido | anulado');

            $table->timestamps();

            /*
             * LA RESTRICCIÓN QUE SOSTIENE TODO EL MÓDULO.
             *
             * Acá NO hay borrado lógico —la tabla no tiene deleted_at— y por eso
             * un unique normal alcanza: no hay filas «muertas» que el índice
             * tenga que ignorar. Un carnet mal emitido se anula cambiando el
             * estado, no borrándolo, y sigue ocupando su lugar en la gestión.
             * Es lo correcto: el número ya se imprimió y se entregó.
             */
            $table->unique(['beneficiario_id', 'gestion'], 'carnets_beneficiario_gestion_unique');

            // La verificación pública entra por la firma, y su índice único ya
            // la cubre: no hace falta ninguno más.

            // Para los listados y el tablero: «carnets vigentes de esta gestión».
            $table->index(['gestion', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carnets');
    }
};

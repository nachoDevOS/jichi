<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Carnets — un documento por persona, POR RUBRO y por gestión
|--------------------------------------------------------------------------
|
| El carnet es el documento anual de UNA actividad. La regla que lo gobierna,
| y de la que cuelga todo el módulo, es esta:
|
|     UNA PERSONA TIENE COMO MÁXIMO UN CARNET POR RUBRO Y POR GESTIÓN.
|
| Un pescador que además comercializa tiene DOS carnets en 2026: uno de
| Pescador y otro de Comercializador, cada uno con su propio plástico, su
| propia firma de validación y su propio cupo autorizado.
|
| --------------------------------------------------------------------------
|  ESTO REEMPLAZÓ AL MODELO DE «UN CARNET CON VARIOS RUBROS»
| --------------------------------------------------------------------------
|
| Antes existía un carnet general por persona y gestión, y los rubros se le
| iban colgando en una tabla intermedia `carnet_rubro`; agregar una actividad
| era una «adición de rubro» sobre ese carnet. Esa tabla YA NO EXISTE.
|
| Lo que la volvió innecesaria es que el carnet pasó a ser de un solo rubro:
| la fila del pivote decía «este carnet habilita esta actividad, desde esta
| fecha, con este cupo, en este estado», y hoy eso es exactamente lo que dice
| la fila del carnet. Mantener las dos era guardar el mismo dato dos veces con
| la posibilidad de que discreparan.
|
| De ahí salen los dos tipos de trámite:
|
|     - No tiene carnet de ESE rubro en esta gestión -> EMISIÓN INICIAL
|     - Ya lo tiene                                  -> ACTUALIZACIÓN
|
| Esa decisión la toma SolicitudCarnetService, pero quien la GARANTIZA es el
| índice único de abajo: si dos ventanillas atienden al mismo pescador en el
| mismo segundo, el servicio de las dos leería «no tiene carnet de este rubro»
| y las dos intentarían crearlo. La base rechaza el segundo INSERT y la
| transacción se deshace. Sin ese índice quedarían dos carnets del mismo rubro
| y el sistema perdería sentido.
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
             * QUÉ ACTIVIDAD HABILITA ESTE CARNET.
             *
             * Es la columna que define el modelo nuevo. No es un dato más del
             * documento: junto con el beneficiario y la gestión forma la llave
             * que impide duplicados, y es lo que se imprime en el plástico.
             *
             * restrictOnDelete, como en `tramites`: un rubro no se puede borrar
             * mientras haya carnets emitidos que lo nombren. El catálogo se
             * inactiva (`rubros.estado`), no se borra — ver RubroController,
             * que a propósito no tiene `destroy()`.
             */
            $table->foreignId('rubro_id')->constrained('rubros')->restrictOnDelete();

            /*
             * FIRMA DE VALIDACIÓN — lo ÚNICO que identifica al carnet.
             *
             * Dieciséis caracteres alfanuméricos generados al azar. No hay
             * columna `codigo`: se retiró a pedido, y con ella el correlativo
             * `CARNET-2026-0001` que existía antes.
             *
             * ------------------------------------------------------------------
             *  UNA FIRMA POR CARNET, O SEA UNA POR RUBRO
             * ------------------------------------------------------------------
             *
             * Con el modelo anterior había que aclarar que la firma NO cambiaba
             * al agregar un rubro, porque el carnet era uno solo y crecía. Hoy
             * no hace falta la aclaración: cada rubro es un carnet distinto y
             * cada uno nace con la suya. Dos carnets de la misma persona en la
             * misma gestión tienen firmas distintas, y es correcto — son dos
             * documentos, con dos plásticos y dos QR.
             *
             * La firma se genera al crear el carnet y NO se vuelve a tocar: si
             * cambiara, el QR ya impreso dejaría de funcionar y habría que
             * reimprimir el plástico.
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

            // El año. Es un tercio de la regla «un carnet por persona, rubro y
            // gestión» y por eso es una columna propia y no se deduce de
            // fecha_emision: un carnet emitido el 2 de enero para la gestión
            // anterior existe.
            $table->unsignedSmallInteger('gestion');

            /*
             * LA ASOCIACIÓN QUE SE IMPRIME EN LA TARJETA — copia congelada.
             *
             * Se copia del trámite AL APROBARLO, igual que
             * `tramites.monto_requerido` copia la tarifa del rubro al
             * registrar. El motivo es el mismo: el carnet dice lo que la unidad
             * autorizó, y una actualización presentada después con el
             * certificado de otra asociación no puede cambiar sola lo que ya
             * está impreso en un plástico que la persona tiene en el bolsillo.
             *
             * NULL mientras el carnet no tenga ningún trámite aprobado. Ver el
             * comentario de `capacidad_kg`, que vale igual para esta columna.
             */
            $table->string('asociacion', 150)->nullable()
                ->comment('Copia de tramites.asociacion al aprobar');

            /*
             * CUPO AUTORIZADO EN KILOS.
             *
             * Cuánto puede capturar o trasladar la persona EN LA ACTIVIDAD DE
             * ESTE CARNET. Antes vivía en `carnet_rubro` justamente porque era
             * por rubro y no por carnet; hoy el carnet ES de un rubro, así que
             * la columna vuelve acá sin perder esa precisión.
             *
             * ------------------------------------------------------------------
             *  NO ES UNA COPIA REDUNDANTE DE `tramites.capacidad_kg`
             * ------------------------------------------------------------------
             *
             * Es la pregunta que más vuelve al mirar el esquema, porque las dos
             * columnas se llaman igual y guardan kilos. Son dos cosas distintas:
             *
             *     tramites.capacidad_kg  ->  lo PEDIDO en ese expediente
             *     carnets.capacidad_kg   ->  lo AUTORIZADO, lo que rige HOY
             *
             * Entre el registro y la aprobación valen cosas distintas, y ahí
             * está el motivo de fondo. Alguien con 600 kg autorizados presenta
             * un trámite para subir a 850: hasta que se cobre y se firme sigue
             * rigiendo 600. Con una sola columna, ese 850 pisaría al 600 y la
             * persona quedaría autorizada a llevar más sin haber pagado — y si
             * el trámite después se rechaza, el 600 ya no existiría en ningún
             * lado para volver atrás.
             *
             * SE ESCRIBE SOLO AL APROBAR, desde
             * `SolicitudCarnetService::consolidarCarnet()`, que es el único
             * lugar que la toca. Un carnet recién creado la tiene en NULL: el
             * documento existe pero todavía no autoriza ningún cupo, que es
             * exactamente lo que es un expediente sin firmar.
             *
             * Leerla siempre del trámite tampoco alcanzaría: obligaría a
             * remontar cuál de todos los expedientes la autorizó —que puede no
             * ser el último, si hubo correcciones o rechazos—.
             *
             * SÍ SE IMPRIME en el plástico, a diferencia del modelo anterior.
             * Antes no se podía: el carnet agrupaba varios rubros con cupos
             * distintos y no había un número que imprimir. Hoy hay uno solo y
             * no cambia, así que va en la tarjeta.
             *
             * Nullable por dos motivos que se suman: porque arranca vacío hasta
             * la primera aprobación, y porque el formulario exige el cupo pero
             * el almacenamiento no —un expediente cargado por consola desde el
             * padrón en papel puede no traerlo—. Misma decisión que en
             * `tramites`.
             *
             * decimal(10,2) y no entero: la unidad usa kilos, pero nada asegura
             * que un cupo no se exprese con decimales.
             */
            $table->decimal('capacidad_kg', 10, 2)->nullable()
                ->comment('Cupo AUTORIZADO en kilos. Lo escribe solo la aprobacion');

            $table->date('fecha_emision');

            /*
             * VENCE AL CERRAR LA GESTIÓN, NO A LOS N DÍAS.
             *
             * Sacado en enero dura casi doce meses, sacado en diciembre dura
             * unas semanas, y los dos vencen el 31 de diciembre. Es la forma en
             * que se maneja el talonario en papel y el sistema la copia.
             */
            $table->date('fecha_vencimiento');

            /*
             * 'vigente' | 'suspendido' | 'vencido' | 'anulado'.
             * Ver App\Enums\EstadoCarnet.
             *
             * `suspendido` llegó con el modelo nuevo y ocupa el lugar que antes
             * tenía `carnet_rubro.estado`: cortar una actividad sin quitar las
             * otras. Con un carnet por rubro, suspender el carnet ES suspender
             * esa actividad, y los demás carnets de la persona siguen vigentes.
             *
             * OJO: el estado guardado puede mentir. `vencido` se actualiza por
             * lote, así que entre corrida y corrida un carnet vencido ayer sigue
             * diciendo «vigente». Para decidir si vale HOY se mira además
             * fecha_vencimiento, que nunca miente. Ver Carnet::estaVigente().
             */
            $table->string('estado', 30)->default('vigente')
                ->comment('vigente | suspendido | vencido | anulado');

            $table->timestamps();

            /*
             * LA RESTRICCIÓN QUE SOSTIENE TODO EL MÓDULO.
             *
             * Acá NO hay borrado lógico —la tabla no tiene deleted_at— y por eso
             * un unique normal alcanza: no hay filas «muertas» que el índice
             * tenga que ignorar. Un carnet mal emitido se anula cambiando el
             * estado, no borrándolo, y sigue ocupando su lugar en la gestión.
             * Es lo correcto: el número ya se imprimió y se entregó.
             *
             * El orden de las columnas importa para las consultas: con
             * (beneficiario, rubro, gestión) el índice también sirve para
             * «todos los carnets de esta persona» y «los de esta persona en
             * este rubro», que son las dos búsquedas del formulario de trámite.
             */
            $table->unique(
                ['beneficiario_id', 'rubro_id', 'gestion'],
                'carnets_beneficiario_rubro_gestion_unique',
            );

            // La verificación pública entra por la firma, y su índice único ya
            // la cubre: no hace falta ninguno más.

            // Para los listados y el tablero: «carnets vigentes de esta gestión».
            $table->index(['gestion', 'estado']);

            // «Cuántos carnets habilitan esta actividad» — el gráfico del
            // tablero y el listado filtrado por rubro. Antes esta consulta salía
            // de `carnet_rubro`; ahora sale de acá.
            $table->index(['rubro_id', 'gestion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carnets');
    }
};

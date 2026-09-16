<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Trámites — el expediente de una solicitud
|--------------------------------------------------------------------------
|
| Un trámite es «esta persona pide que se le habilite este rubro». Nace
| PENDIENTE con los papeles adjuntos y termina APROBADO o RECHAZADO.
|
|     PENDIENTE ──▶ EN REVISIÓN ──▶ APROBADO   (nace la fila en carnet_rubro)
|         │              │
|         └──────────────┴────────▶ RECHAZADO  (con motivo escrito, obligatorio)
|
| Qué salto vale desde dónde lo decide App\Enums\EstadoTramite, no esta tabla:
| la columna es un `string` justamente para que agregar un estado no exija un
| ALTER TYPE ni bloquear la tabla.
|
| EL TRÁMITE CUELGA DEL CARNET, NO DEL BENEFICIARIO.
|
| Podría apuntar directo a la persona, pero entonces habría que volver a
| deducir a qué gestión pertenece cada expediente. Colgándolo del carnet, la
| gestión viene dada: todo trámite de un carnet de 2026 es de la gestión 2026,
| por construcción. La persona se llega con un salto más (tramite -> carnet ->
| beneficiario) y eso lo resuelve una relación hasOneThrough.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('carnet_id')->constrained('carnets')->restrictOnDelete();
            $table->foreignId('rubro_id')->constrained('rubros')->restrictOnDelete();

            /*
             * 'emision_inicial' | 'adicion_rubro'. Ver App\Enums\TipoTramite.
             *
             * NO LO ELIGE EL OPERADOR. Lo decide el sistema mirando si la
             * persona ya tenía carnet de la gestión en curso. Dejarlo a elección
             * de ventanilla sería pedirle al operador que adivine algo que la
             * base de datos ya sabe, y equivocarse ahí significa cobrar de menos
             * o emitir un carnet duplicado. Ver SolicitudCarnetService::registrar().
             */
            $table->string('tipo_tramite', 50)->comment('emision_inicial | adicion_rubro');

            // Ver App\Enums\EstadoTramite.
            $table->string('estado', 30)->default('pendiente')
                ->comment('pendiente | en_revision | aprobado | rechazado');

            /*
             * LOS DOS RESPALDOS OBLIGATORIOS.
             *
             * Se guarda la RUTA del archivo, no el archivo. Quién decide en qué
             * disco se escribe es StorageController, y según esté configurado
             * devuelve una ruta local o una dirección completa de s3; las dos
             * formas pueden convivir en esta misma columna. Por eso para armar
             * el enlace se pasa siempre por App\Support\Archivos::url().
             *
             * Son nullable en la base aunque el formulario los exija: un
             * expediente cargado por consola o migrado de papel puede no
             * tenerlos, y una NOT NULL acá haría imposible registrarlo. La
             * obligatoriedad vive en RegistrarSolicitudRequest, que es donde
             * corresponde —es una regla del formulario, no del almacenamiento—.
             */
            $table->string('ciFile')->nullable()->comment('Fotocopia de carnet de identidad');
            $table->string('certAsociacionFile')->nullable()->comment('Certificado de la asociacion');

            /*
             * A QUÉ ASOCIACIÓN PERTENECE, declarado en ESTE trámite.
             *
             * Va acá y no en `beneficiarios` porque es lo que el certificado
             * adjunto respalda: el papel dice «fulano es socio de tal
             * asociación», y los dos datos tienen que viajar juntos. Guardado en
             * la ficha de la persona quedaría suelto del documento que lo prueba,
             * y cambiarlo alteraría el respaldo de trámites ya resueltos.
             *
             * Es texto libre y no una tabla de asociaciones: hay decenas, nacen y
             * se disuelven, y ninguna oficina mantiene ese padrón. Una tabla
             * obligaría a dar de alta una asociación antes de poder atender a
             * alguien en ventanilla. El día que la unidad tenga el listado
             * oficial, se normaliza.
             */
            $table->string('asociacion', 150)->nullable()
                ->comment('Asociacion que certifica al beneficiario');

            /*
             * CUPO AUTORIZADO EN KILOS — declarado en ESTE trámite.
             *
             * Es cuánto puede capturar o trasladar la persona en la actividad
             * que está pidiendo. El carnet de papel lo traía impreso —«600 KG»
             * bajo el domicilio—; el nuevo NO lo imprime, a pedido de la
             * unidad. Se guarda igual porque es dato de control: sin él no se
             * puede contrastar una guía de transporte contra lo autorizado, ni
             * sacar el cupo total comprometido en una gestión.
             *
             * Va acá y no en `rubros` porque no es una propiedad de la
             * actividad sino de la autorización concreta: dos pescadores del
             * mismo rubro pueden tener cupos distintos según lo que resuelva la
             * unidad. Y va en el TRÁMITE —no directo en la habilitación— por el
             * mismo motivo que `asociacion`: es lo que se declaró el día que se
             * presentó el papel, y tiene que quedar en el expediente aunque la
             * habilitación se suspenda o el cupo se corrija después.
             *
             * EL FORMULARIO LO EXIGE, PERO LA COLUMNA ES NULLABLE. No es una
             * contradicción: es la misma decisión que con `asociacion` y los
             * dos adjuntos. La obligatoriedad es de la pantalla, no del
             * almacenamiento —un expediente migrado del padrón en papel puede
             * no traer el dato, y una NOT NULL haría imposible cargarlo—.
             * Ver RegistrarSolicitudRequest.
             *
             * decimal(10,2) y no entero: la unidad usa kilos, pero nada asegura
             * que un cupo no se exprese con decimales, y un entero obligaría a
             * migrar la columna el día que aparezca el primero.
             */
            $table->decimal('capacidad_kg', 10, 2)->nullable()
                ->comment('Cupo autorizado en kilos. No se imprime en el carnet');

            /*
             * CUÁNTO HAY QUE PAGAR — copia congelada de `rubros.costo`.
             *
             * Se copia y no se consulta en vivo porque las tarifas cambian por
             * ordenanza. Si el trámite leyera el costo actual del rubro, subir
             * la tarifa en marzo dejaría de golpe «impagos» todos los
             * expedientes de febrero que ya estaban cubiertos. El expediente
             * debe lo que decía el papel el día que se presentó.
             */
            $table->decimal('monto_requerido', 10, 2)->default(0)->comment('Copia de rubros.costo al registrar');

            $table->timestamp('fecha_solicitud')->useCurrent();

            /*
             * CUÁNDO ALGUIEN LO ABRIÓ PARA REVISARLO.
             *
             * Junto con `fecha_solicitud` responde la pregunta que hace la
             * unidad todos los meses: cuánto tiempo pasa un expediente en la
             * pila antes de que alguien lo mire. Sin esta fecha, lo único
             * medible sería «presentado → aprobado», que mezcla el tiempo de
             * espera con el de revisión y no dice dónde está el cuello.
             */
            $table->timestamp('fecha_revision')->nullable()->comment('Cuando se tomo para revisar');

            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamp('fecha_generacion')->nullable()->comment('Cuando se imprimio el carnet');
            $table->timestamp('fecha_entrega')->nullable();

            $table->text('observaciones')->nullable();
            $table->text('motivo_rechazo')->nullable();

            $table->timestamps();

            // No hay `recepcionado_por` ni `aprobado_por`. Quién hizo cada
            // movimiento queda en `auditorias`: el trait Auditable escribe una
            // fila por cada cambio de estado con el usuario, la IP y los valores
            // antes y después. Columnas acá repetirían peor lo que esa tabla ya
            // guarda completo.

            // El listado del panel filtra por estado y ordena por fecha.
            $table->index(['estado', 'fecha_solicitud']);

            // «Qué pidió este carnet» — la consulta de la ficha del carnet.
            $table->index(['carnet_id', 'estado']);

            /*
             * NO HAY UNIQUE (carnet_id, rubro_id) ACÁ, y no es un olvido.
             *
             * Un rubro rechazado se puede volver a pedir con los papeles
             * corregidos, así que la misma combinación aparece más de una vez a
             * lo largo del tiempo. Lo que no puede repetirse es el rubro ya
             * APROBADO, y eso lo impide el unique de `carnet_rubro`.
             *
             * Lo que sí se impide —un segundo trámite PENDIENTE para el mismo
             * rubro— se comprueba en el servicio: expresarlo como índice exigiría
             * uno parcial sobre estado = 'pendiente', y el mensaje de error que
             * necesita ventanilla («ya hay una solicitud en curso para este
             * rubro») no lo puede dar la base.
             */
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramites');
    }
};

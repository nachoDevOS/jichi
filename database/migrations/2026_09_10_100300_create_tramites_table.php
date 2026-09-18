<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Trámites — el expediente que emite o actualiza un carnet
|--------------------------------------------------------------------------
|
|     PENDIENTE ──▶ EN REVISIÓN ──▶ APROBADO   (el carnet queda habilitado)
|         │              │
|         └──────────────┴────────▶ RECHAZADO  (con motivo, obligatorio)
|
| Qué salto vale desde dónde lo decide App\Enums\EstadoTramite, no esta tabla.
|
| CUELGA DEL CARNET, NO DEL BENEFICIARIO: así la gestión y la actividad vienen
| dadas por construcción. A la persona se llega con un hasOneThrough.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('carnet_id')->constrained('carnets')->restrictOnDelete();

            /*
             * El mismo rubro que el del carnet, a propósito: los listados
             * filtran y agrupan sin join, y el expediente queda legible solo.
             *
             * Contrapartida: son dos columnas que tienen que decir lo mismo y la
             * base no lo impide. Las mantiene de acuerdo SolicitudCarnetService.
             */
            $table->foreignId('rubro_id')->constrained('rubros')->restrictOnDelete();

            // Ver App\Enums\TipoTramite. NO LO ELIGE EL OPERADOR: lo decide el
            // sistema mirando si ya hay carnet de ese rubro en la gestión.
            $table->string('tipo_tramite', 50)->comment('emision_inicial | actualizacion');

            // Ver App\Enums\EstadoTramite.
            $table->string('estado', 30)->default('pendiente')
                ->comment('pendiente | en_revision | aprobado | rechazado');

            /*
             * Los dos respaldos. Se guarda la RUTA, no el archivo: puede ser
             * local o una dirección de s3 según el disco, y las dos formas
             * conviven acá. El enlace se arma con App\Support\Archivos::url().
             *
             * Nullable aunque el formulario los exija: un expediente migrado del
             * padrón en papel puede no tenerlos. La obligatoriedad es de la
             * pantalla —RegistrarSolicitudRequest—, no del almacenamiento. Lo
             * mismo vale para `asociacion` y `capacidad_kg`.
             */
            $table->string('ciFile')->nullable()->comment('Fotocopia de carnet de identidad');
            $table->string('certAsociacionFile')->nullable()->comment('Certificado de la asociacion');

            /*
             * La asociación declarada en ESTE trámite, que es lo que el
             * certificado adjunto respalda: los dos datos viajan juntos.
             *
             * Texto libre y no una tabla: hay decenas, nacen y se disuelven, y
             * ninguna oficina mantiene ese padrón.
             */
            $table->string('asociacion', 150)->nullable()
                ->comment('Asociacion que certifica al beneficiario');

            // Lo PEDIDO. Pasa a `carnets.capacidad_kg` al aprobar — ver el
            // comentario de esa columna, que explica por qué son dos.
            $table->decimal('capacidad_kg', 10, 2)->nullable()
                ->comment('Cupo SOLICITADO en kilos. Pasa a carnets.capacidad_kg al aprobar');

            // Copia congelada de `rubros.costo`. Leído en vivo, subir la tarifa
            // en marzo dejaría impagos de golpe los expedientes de febrero.
            $table->decimal('monto_requerido', 10, 2)->default(0)
                ->comment('Copia de rubros.costo al registrar');

            $table->timestamp('fecha_solicitud')->useCurrent();

            // Con `fecha_solicitud` responde cuánto tarda un expediente en que
            // alguien lo mire. Sin ella solo se mide «presentado → aprobado»,
            // que mezcla la espera con la revisión y no dice dónde está el cuello.
            $table->timestamp('fecha_revision')->nullable()->comment('Cuando se tomo para revisar');

            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamp('fecha_generacion')->nullable()->comment('Cuando se imprimio el carnet');
            $table->timestamp('fecha_entrega')->nullable();

            $table->text('observaciones')->nullable();
            $table->text('motivo_rechazo')->nullable();

            $table->timestamps();

            // Sin `recepcionado_por` / `aprobado_por`: quién hizo cada
            // movimiento queda en `auditorias`, con la IP y los valores.

            $table->index(['estado', 'fecha_solicitud']);
            $table->index(['carnet_id', 'estado']);

            /*
             * NO HAY UNIQUE (carnet_id, rubro_id), y no es un olvido: un trámite
             * rechazado se vuelve a presentar y un carnet recibe varias
             * actualizaciones al año. Lo que no puede repetirse es el CARNET, y
             * eso lo impide el unique de `carnets`.
             *
             * Que no haya DOS trámites abiertos sobre el mismo carnet lo
             * comprueba el servicio: la base no puede dar el mensaje que
             * ventanilla necesita.
             */
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramites');
    }
};

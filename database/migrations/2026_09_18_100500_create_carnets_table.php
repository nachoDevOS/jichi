<?php

use App\Enums\EstadoCarnet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Carnets — la credencial física que se entrega en ventanilla.
*/
/*
| SIN COLUMNA DE CÓDIGO: la llave de verificación se mudó a la tabla `codigos`
| el 22/09/2026, compartida con los otros cuatro documentos. Ver MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carnets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('beneficiario_id')->constrained('beneficiarios')->restrictOnDelete();
            $table->foreignId('asociacion_id')->constrained('asociaciones')->restrictOnDelete();
            $table->foreignId('tipo_carnet_id')->constrained('tipos_carnet')->restrictOnDelete();

            /*
             * NULLABLE PORQUE SOLO EL PESCADOR LLEVA CUPO, y eso lo dice
             * `TipoActor::requiereAprovechamiento()`, NUNCA un match sobre el
             * nombre del tipo de carnet —que es un catálogo que edita la unidad—.
             */
            $table->foreignId('aprovechamiento_id')->nullable()
                ->constrained('aprovechamientos_pesq')->nullOnDelete()
                ->comment('Solo si tipo_actor = pescador');

            $table->string('tipo_actor', 20)->comment('pescador | comercializador');

            /*
             * EL NÚMERO DE REGISTRO, el que va impreso en el plástico.
             *
             * Correlativo por GESTIÓN y compartido entre pescadores y
             * comercializadores: la serie es del registro, no de la actividad.
             * Se asigna AL APROBAR —lo reserva `CorrelativoService`, que
             * bloquea la fila del contador— así que va nullable: un carnet
             * pendiente todavía no ocupa número, y uno rechazado no gasta uno.
             *
             * LA GESTIÓN NO SE GUARDA: es el año de `fecha_emision`, y dos
             * columnas que dicen lo mismo terminan contradiciéndose. Se lee
             * con `Carnet::gestion`, que es un accesor.
             */
            $table->unsignedInteger('nro_registro')->nullable()->comment('Correlativo anual, al aprobar');

            /*
             * LOS DOS PAPELES QUE RESPALDAN LA EMISIÓN: la fotocopia de la
             * cédula y la carta o certificación de la asociación. Nullable
             * porque muchos carnets se cargan para poner al día lo emitido en
             * PAPEL, donde esos escaneos no existen; el formulario del panel sí
             * los exige. El tope de 3 MB y el nombre aleatorio los pone
             * StorageController, que es el único que escribe archivos.
             */
            $table->string('archivo_ci', 255)->nullable()->comment('Escaneo de la cédula');
            $table->string('archivo_asociacion', 255)->nullable()->comment('Carta o certificación del gremio');

            // NACE PENDIENTE y solo la firma lo activa: el plástico no se
            // entrega hasta que el arancel esté cobrado. Ver RevisarCarnetService.
            $table->string('estado', 20)->default(EstadoCarnet::Pendiente->value);

            /*
             * DOS FECHAS DISTINTAS, igual que en el aprovechamiento: el día
             * que la persona lo pidió y el día que alguien lo firmó.
             * `fecha_emision` queda en NULL hasta la aprobación —un carnet
             * pendiente no se emitió todavía, y el plástico no salió—.
             */
            $table->date('fecha_solicitud');
            $table->date('fecha_emision')->nullable()->comment('Se llena al aprobar');
            $table->date('fecha_vencimiento');

            /*
             * ÍNDICE Y NO ÚNICO, a propósito. El único tendría que ser «un
             * número por AÑO», y el año no es una columna: expresarlo pediría
             * un índice funcional sobre `fecha_emision`, que solo existe en
             * PostgreSQL —y el sistema tiene que correr igual en SQLite—.
             *
             * Quien garantiza que no se repita es `CorrelativoService`, que
             * reserva el número BLOQUEANDO la fila del contador dentro de la
             * misma transacción que aprueba el carnet. Es el mismo mecanismo
             * que numera los recibos.
             */
            $table->index(['nro_registro']);

            // «Los carnets de esta persona», que es como entra siempre la ficha.
            $table->index(['beneficiario_id', 'estado']);

            // Los listados filtran por tipo de actor y por gremio.
            $table->index(['tipo_actor', 'estado']);
            $table->index('asociacion_id');

            // Para el comando diario que marca los vencidos.
            $table->index('fecha_vencimiento');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carnets');
    }
};

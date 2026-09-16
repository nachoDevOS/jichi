<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| carnet_rubro — qué actividades tiene habilitadas un carnet
|--------------------------------------------------------------------------
|
| Esta es la tabla que da sentido a la «adición de rubro»: el carnet es uno
| solo por gestión, y lo que crece es esta lista.
|
|     carnet 4K7R-J2MX-P9TQ
|       ├── Pescador          habilitado el 03/02/2026
|       └── Comercializador   habilitado el 19/06/2026   <- adición
|
| NO SE ESCRIBE ACÁ AL PEDIR, SE ESCRIBE AL APROBAR.
|
| La fila nace recién cuando un supervisor aprueba el trámite. Mientras el
| expediente está pendiente, el rubro figura en `tramites` y no acá. Si se
| escribiera al solicitar, un pescador quedaría habilitado por el solo hecho
| de haber presentado papeles. Ver SolicitudCarnetService::aprobar().
|
| El nombre de la tabla va en singular y en orden alfabético (`carnet_rubro`)
| porque es la convención de Laravel para tablas intermedias; el resto del
| sistema usa plural porque son tablas de entidades.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carnet_rubro', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete acá y restrictOnDelete en `rubros`, a propósito:
            // si un día se borra un carnet, sus habilitaciones no tienen
            // sentido solas y se van con él; un rubro, en cambio, no se puede
            // borrar mientras haya carnets que lo usen.
            $table->foreignId('carnet_id')->constrained('carnets')->cascadeOnDelete();
            $table->foreignId('rubro_id')->constrained('rubros')->restrictOnDelete();

            // El día en que el supervisor aprobó el trámite. No es igual a la
            // fecha de emisión del carnet: una adición de junio se habilita en
            // junio sobre un carnet emitido en febrero.
            $table->date('fecha_habilitacion');

            /*
             * CUPO AUTORIZADO EN KILOS — copia congelada de `tramites.capacidad_kg`.
             *
             * Se copia al aprobar, igual que `carnets.asociacion` copia la del
             * trámite que emitió el carnet. El motivo de duplicarlo es que acá
             * es donde se consulta: la pregunta real es «cuánto tiene
             * autorizado HOY esta persona para este rubro», y respondida desde
             * el trámite obliga a remontar el expediente que la habilitó —que
             * puede no ser el último, si hubo correcciones—.
             *
             * ES POR RUBRO, NO POR CARNET. Un mismo carnet puede tener Pescador
             * con 600 kg y Comercializador con otro cupo: son autorizaciones
             * distintas y la unidad las resuelve por separado. Guardarlo en
             * `carnets` forzaría un único número para las dos.
             *
             * NO SE IMPRIME EN EL CARNET. El plástico lleva registro, titular,
             * asociación, domicilio, gestión y QR; el cupo queda del lado del
             * sistema. Es dato de control —para cruzar contra guías de
             * transporte y para los reportes de gestión—, no de exhibición.
             *
             * Nullable por lo mismo que en `tramites`: el formulario exige el
             * cupo, pero un expediente cargado por consola desde el padrón en
             * papel puede no traerlo, y entonces la habilitación nace sin él.
             */
            $table->decimal('capacidad_kg', 10, 2)->nullable()
                ->comment('Copia de tramites.capacidad_kg al aprobar. No se imprime');

            // 'habilitado' | 'suspendido'. Ver App\Enums\EstadoHabilitacion.
            //
            // La suspensión es por rubro y no por carnet: a un pescador se le
            // puede cortar el transporte sin quitarle la pesca.
            $table->string('estado', 30)->default('habilitado')->comment('habilitado | suspendido');

            $table->timestamps();

            /*
             * UN RUBRO NO SE HABILITA DOS VECES EN EL MISMO CARNET.
             *
             * El script SQL de referencia no traía esta restricción. Sin ella,
             * aprobar dos veces la misma adición —un doble clic en el botón,
             * dos supervisores mirando el mismo expediente— dejaría el rubro
             * repetido en el carnet, y el reverso impreso mostraría la misma
             * actividad dos veces.
             *
             * El servicio ya comprueba el duplicado antes de insertar; esto es
             * la red de abajo, la que sí resiste dos peticiones simultáneas.
             */
            $table->unique(['carnet_id', 'rubro_id'], 'carnet_rubro_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carnet_rubro');
    }
};

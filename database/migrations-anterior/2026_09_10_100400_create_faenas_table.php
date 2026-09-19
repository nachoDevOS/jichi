<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Faenas — el permiso de UNA salida de pesca
|--------------------------------------------------------------------------
|
|     carnet (Pescador, 2026) ──< faena ──< faena ──< faena
|
| El carnet es la llave anual; la faena autoriza el viaje: esta embarcación,
| este comandante, de tal día a tal día, con tanto en kilos. Se emiten muchas
| por gestión, así que NO hay único sobre `carnet_id`.
|
| Cuelga del CARNET por lo mismo que `tramites`: la gestión y la actividad
| vienen dadas por construcción.
|
| Que el rubro emita faenas —`rubros.emite_faenas`— y que el carnet esté
| vigente lo comprueba el SERVICIO: son reglas que necesitan explicarse en
| castellano en el mostrador, y una restricción solo sabe decir que no.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faenas', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete: la faena circuló por el río y tiene que quedar
            // consultable. No se borra con el carnet.
            $table->foreignId('carnet_id')->constrained('carnets')->restrictOnDelete();

            /*
             * EL NÚMERO DEL TALONARIO DE PAPEL — «N 002190». Lo TIPEA el
             * operador; no se genera acá. `varchar` porque viene con prefijo y
             * ceros a la izquierda.
             *
             * ÚNICO EN TODO EL SISTEMA: dos faenas con el mismo número serían
             * dos papeles que dicen ser el mismo, y en un control nadie sabría
             * cuál vale.
             */
            $table->string('nro_permiso', 50)->unique()
                ->comment('Numero preimpreso del talonario fisico. Lo tipea el operador');

            // Nullable: la faena puede registrarse antes de que entre el
            // depósito, y el cobro se sigue por `pagos`.
            $table->string('nro_recibo', 50)->nullable()
                ->comment('Numero del recibo de caja, tal como figura en el papel');

            // Tarifa al emitir, copia congelada. Es POR SALIDA, distinta de
            // `rubros.costo`, que cobra la emisión del carnet una vez al año.
            // El mismo número está en `Faena::TARIFA`, que es el que muestra el
            // formulario. Subirla por ordenanza obliga a tocar los dos.
            $table->decimal('monto', 10, 2)->default(15.00)
                ->comment('Tarifa de la faena al emitirla. Copia congelada');

            /*
             * LOS DATOS DEL FORMULARIO FÍSICO.
             *
             * Nullable no es descuido: el papel se llena a mano y llega
             * incompleto —una embarcación sin matrícula, un kardex que no
             * trajeron—. La obligatoriedad es del FORMULARIO, no de la tabla.
             *
             * Texto libre y no tablas propias: no hay padrón de embarcaciones ni
             * de comandantes, y una tabla obligaría a darlos de alta antes de
             * poder atender a alguien.
             */
            $table->string('embarcacion', 150)->nullable();
            $table->string('propietario', 150)->nullable();
            $table->string('comandante_barco', 150)->nullable();
            $table->string('matricula_naval', 50)->nullable();
            $table->string('nro_kardex', 50)->nullable();
            $table->string('region_desde', 150)->nullable();
            $table->string('region_hasta', 150)->nullable();

            // OBLIGATORIAS: definen la VENTANA del permiso, y un control en el
            // río compara contra eso. Que desembarque no sea anterior a salida
            // lo valida el formulario: un CHECK no puede dar ese mensaje.
            $table->date('fecha_salida');
            $table->date('fecha_desembarque');

            // Tope de ESTE viaje. No es el cupo del carnet, que es el de toda la
            // gestión: son dos topes que se controlan en momentos distintos.
            $table->decimal('cantidad_autorizada_kg', 10, 2)
                ->comment('Tope de esta salida. Distinto del cupo anual del carnet');

            // Donde queda escrito el motivo cuando se anula: es lo único que
            // explica después por qué ese número del talonario no vale.
            $table->text('observaciones')->nullable();

            // Ver App\Enums\EstadoPermiso. SIN BORRADO: el número del talonario
            // ya se gastó y el papel puede estar circulando; un hueco en la
            // serie no se puede explicar después.
            $table->string('estado', 30)->default('emitido')->comment('emitido | anulado');

            $table->timestamps();

            // «Las faenas de este carnet», que es la consulta de la ficha.
            $table->index(['carnet_id', 'estado']);

            // Para el listado general y los reportes por período.
            $table->index('fecha_salida');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faenas');
    }
};

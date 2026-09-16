<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Recibos — el comprobante que se le entrega al pescador
|--------------------------------------------------------------------------
|
| Es la versión digital del RECIBO OFICIAL del SEDAG, el talonario verde de tres
| copias. Nace solo, cuando el expediente pasa a EN REVISIÓN: en ese momento el
| pescador ya presentó los papeles y pagó, y se va del mostrador con su recibo.
|
| ----------------------------------------------------------------------------
|  POR QUÉ ESTA TABLA GUARDA UNA COPIA DE DATOS QUE YA ESTÁN EN OTRO LADO
| ----------------------------------------------------------------------------
|
| El nombre, la cédula, el concepto y el monto se pueden leer siguiendo
| `tramite_id`. Se copian igual, y es la decisión central de esta tabla.
|
| Un recibo es un papel NUMERADO QUE YA SE ENTREGÓ. Si mañana alguien corrige un
| apellido mal tipeado en la ficha del beneficiario, o la unidad sube la tarifa
| del rubro por ordenanza, el original que el pescador tiene en el bolsillo no
| cambia — y la reimpresión tampoco puede cambiar, o dejaría de coincidir con lo
| que se entregó y con lo que Contabilidad archivó.
|
| Es el mismo criterio de `tramites.monto_requerido`, que congela la tarifa el
| día de la solicitud, llevado hasta el final: acá se congela el documento
| entero.
|
| ----------------------------------------------------------------------------
|  EL RECIBO SOBREVIVE AL BORRADO DEL TRÁMITE
| ----------------------------------------------------------------------------
|
| `tramite_id` es nullable y va con nullOnDelete(): si el trámite desaparece, la
| fila del recibo QUEDA, con su número y su copia de los datos.
|
| Borrarlo en cascada sería abrir un hueco en la serie numerada sin que nadie
| pueda explicar después qué fue el 0016. Un talonario con saltos no se puede
| rendir. Y la copia congelada de arriba es lo que hace que esa fila huérfana
| siga siendo legible sola.
|
| HOY ESE BORRADO NO DEBERÍA OCURRIR: el recibo nace al enviar a revisión, y un
| expediente en revisión ya no se elimina —solo se aprueba o se rechaza, ver
| EstadoTramite::permiteEliminacion()—. Cuando se escribió esta migración sí se
| podía, y la columna se dejó nullable por eso.
|
| Se deja igual, y a propósito. La numeración del talonario es hacia afuera: esos
| papeles se rinden ante la contraloría, y un hueco en la serie no se explica con
| «cambiamos una regla del sistema». Que el recibo no dependa de que el trámite
| exista es más barato que confiar en que la regla de borrado nunca se afloje.
|
| OJO CON EL UNIQUE SOBRE UNA COLUMNA NULLABLE: acá el comportamiento de SQL
| —`NULL != NULL`, así que los nulos no chocan entre sí— es JUSTO lo que se
| quiere. Muchos recibos pueden quedar huérfanos; lo que no puede es que dos
| recibos apunten al mismo trámite. Es el caso inverso al de
| `beneficiarios_ci_unico`, donde esa misma regla obligaba a un índice parcial.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();

            // Un recibo por trámite. Ver el comentario de arriba sobre por qué
            // es nullable y por qué el unique funciona igual.
            $table->foreignId('tramite_id')->nullable()->unique()->constrained('tramites')->nullOnDelete();

            /*
             * EL NÚMERO IMPRESO EN GRANDE, ARRIBA A LA DERECHA: 0016.
             *
             * Lo entrega CorrelativoService con la fila del contador bloqueada,
             * así dos ventanillas cobrando en el mismo segundo nunca reciben el
             * mismo. Se guarda como entero y se rellena con ceros al imprimir:
             * guardado como texto no se podría ordenar ni sacar el último.
             */
            $table->unsignedInteger('numero');

            // La serie se reinicia cada año, igual que el talonario de papel: en
            // enero vuelve a empezar por el 1. Por eso la unicidad es del par y
            // no del número solo.
            $table->unsignedSmallInteger('gestion');

            // --- La copia congelada de lo que dice el papel entregado
            $table->string('beneficiario_nombre', 200);
            $table->string('beneficiario_ci', 40)->nullable();

            // Lo que se cobró, en las palabras del recibo. Sale del rubro, pero
            // queda escrito acá: renombrar el rubro no reescribe recibos viejos.
            $table->string('concepto', 200);

            // Cuál de las seis casillas de DESCRIPCIÓN queda marcada.
            // Ver App\Enums\ConceptoRecibo.
            $table->string('descripcion', 40)->comment('permiso_faena | guia_transporte | otros ...');

            $table->decimal('monto', 10, 2);

            // 'deposito' | 'efectivo'. Son las dos casillas del papel.
            // Ver App\Enums\FormaPago.
            $table->string('forma_pago', 20)->default('deposito');

            // El número de transacción del banco, cuando hubo depósito. Con
            // efectivo queda en NULL: no hay ningún número que anotar.
            $table->string('nro_deposito', 120)->nullable();

            // «Lugar y Fecha» del encabezado. El lugar se guarda porque sale de
            // Configuración y esa se puede editar; la fecha, porque es la del
            // día en que se entregó y no la de una reimpresión posterior.
            $table->string('lugar', 120);
            $table->date('fecha_emision');

            $table->timestamps();

            // No hay `emitido_por`. Quién lo emitió queda en `auditorias`, con
            // el usuario, la IP y el momento — igual que en `tramites`, que
            // tampoco guarda `aprobado_por`.

            $table->unique(['gestion', 'numero'], 'recibos_serie_unica');

            // «Los recibos de esta gestión, del más nuevo al más viejo»: el
            // orden del libro de recibos.
            $table->index(['gestion', 'fecha_emision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos');
    }
};

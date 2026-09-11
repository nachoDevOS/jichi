<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_tramite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->string('codigo', 20)->comment('Prefijo del documento, ej: PPA (Permiso Pesca Artesanal)');
            $table->text('descripcion')->nullable();
            $table->string('categoria_documento', 30)->default('permiso')
                ->comment('certificacion | credencial | permiso | licencia');
            /*
             * Lo que cuesta el servicio.
             *
             * Antes vivia en una tabla `tasas` aparte, con fecha de vigencia,
             * para poder guardar el historial de tarifas y varias tarifas por
             * servicio (emision Bs 80 y reposicion Bs 50). Se retiro: el
             * sistema cobraba siempre la principal y el historial no lo leia
             * nadie. Un servicio, un precio.
             *
             * Si algun dia hace falta versionar tarifas, vuelve la tabla junto
             * con la pantalla que la administre.
             */
            $table->decimal('monto', 12, 2)->default(0)->comment('Lo que se cobra en ventanilla, en Bs');
            $table->string('ordenanza')->nullable()->comment('Norma departamental que respalda el monto');

            $table->boolean('requiere_foto')->default(false);

            /*
             * CÓMO VENCE EL DOCUMENTO.
             *
             * No todos vencen igual. El permiso por faena vale un mes desde
             * que se emite —cuenta días—, pero la cédula de pescador vale LA
             * GESTIÓN: vence el 31 de diciembre del año en que se emitió, sin
             * importar si se sacó en enero o en diciembre.
             *
             * Con una sola columna de días esa segunda regla no se puede
             * expresar, y el error no es menor: una credencial sacada en
             * diciembre habría durado hasta diciembre del año siguiente, casi
             * un año de más habilitando a pescar.
             *
             * `vigencia_dias` solo se lee cuando el tipo es 'dias'.
             * Ver App\Enums\VigenciaTipo.
             */
            $table->string('vigencia_tipo', 20)->default('dias')
                ->comment('dias | gestion | sin_vencimiento');
            $table->unsignedSmallInteger('vigencia_dias')->nullable()
                ->comment('Solo si vigencia_tipo = dias. NULL = sin vencimiento');

            /*
             * Se agota al usarse una vez, aunque todavía no haya vencido.
             *
             * El permiso por faena ampara UNA salida de pesca: vale un mes,
             * pero si se usa a los tres días se terminó igual. Para otra
             * faena hay que sacar otro permiso.
             */
            $table->boolean('uso_unico')->default(false);

            /*
             * LA COMPUERTA DE LA CREDENCIAL.
             *
             * El permiso por faena y la guía de transporte no se le emiten a
             * cualquiera: hay que tener la cédula de pescador VIGENTE al
             * momento de pedirlos. Sin credencial vigente el trámite no se
             * puede ni registrar; primero se emite o se renueva la cédula.
             *
             * La cédula misma, obviamente, no se exige a sí misma.
             */
            $table->boolean('requiere_credencial')->default(false)
                ->comment('Exige cedula de pescador vigente del solicitante');
            $table->jsonb('requisitos')->nullable()->comment('Lista de requisitos exigidos en ventanilla');
            $table->text('texto_plantilla')->nullable()->comment('Cuerpo formal del documento con placeholders');
            $table->boolean('requiere_aprobacion')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['area_id', 'codigo']);
            $table->index(['area_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_tramite');
    }
};

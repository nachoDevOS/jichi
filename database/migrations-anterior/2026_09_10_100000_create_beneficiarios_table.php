<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Beneficiarios — la persona que saca el carnet
|--------------------------------------------------------------------------
|
| El nombre va PARTIDO EN CINCO COLUMNAS porque así llega: la cédula boliviana
| lo trae separado. Partirlo después con código no se puede acertar siempre.
|
| De `primerNombre` en adelante van en camelCase. En PostgreSQL eso obliga a
| entrecomillar en SQL escrito a mano: SELECT "primerNombre", nunca sin comillas.
| Eloquent entrecomilla solo; el problema aparece con whereRaw / orderByRaw.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiarios', function (Blueprint $table) {
            $table->id();

            // --- Documento de identidad
            $table->string('ci_nit', 30);
            $table->string('complemento', 5)->nullable()->comment('Complemento de CI boliviano');
            $table->string('expedido', 5)->nullable()->comment('BN, LP, SC, CB...');

            // --- Nombre, tal como figura en la cédula
            $table->string('primerNombre', 60);
            $table->string('segundoNombre', 60)->nullable()->comment('Mucha gente no tiene');
            $table->string('apellidoPaterno', 60);
            $table->string('apellidoMaterno', 60)->nullable();
            // Sin el «de»: en la base va «Justiniano». Con el «de» adentro,
            // buscar «Justiniano» no encontraría a esa persona.
            $table->string('apellidoCasado', 60)->nullable()->comment('Sin el "de": se agrega al imprimir');

            // NO hay columna `nombreCompleto`: se arma en el modelo, así no
            // puede quedar desfasado de sus partes.

            // --- Datos personales
            $table->date('fechaNacimiento');
            $table->string('genero', 20)->nullable()->comment('masculino | femenino');
            $table->string('nacionalidad')->default('Boliviana');

            // --- Contacto
            $table->text('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('provincia')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('foto')->nullable();

            // Sin `created_by` / `updated_by`: quién cargó y quién modificó ya
            // lo guarda `auditorias`, con el detalle de lo que cambió.
            $table->timestamps();
            $table->softDeletes();

            // Para el orden alfabético del listado, no para la búsqueda.
            $table->index(['apellidoPaterno', 'apellidoMaterno', 'primerNombre'], 'beneficiarios_nombre_index');
        });

        /*
         * UNA PERSONA, UNA FICHA. Sin esto, la misma persona cargada dos veces
         * sacaría dos carnets del mismo rubro en la misma gestión.
         *
         * El índice es PARCIAL a propósito: meter `deleted_at` dentro del unique
         * no sirve, porque en SQL NULL != NULL y todas las filas vivas se
         * considerarían distintas. El COALESCE es por lo mismo, para el
         * complemento opcional. SQLite entiende esta misma sintaxis.
         */
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX beneficiarios_ci_unico
                ON beneficiarios (ci_nit, COALESCE(complemento, ''))
                WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiarios');
    }
};

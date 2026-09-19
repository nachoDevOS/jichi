<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Beneficiarios — la persona, UNA SOLA VEZ.
|
| Tabla unificada y SIN columna de rol: quien pesca y además comercializa es una
| persona con dos credenciales, no dos fichas. El rol vive en
| `carnets.tipo_actor`, que es del documento.
|
| De `primerNombre` en adelante van en camelCase, así que en SQL escrito a mano
| hay que entrecomillar: SELECT "primerNombre". Sin comillas, PostgreSQL pasa el
| nombre a minúscula y responde «column "primernombre" does not exist». Eloquent
| entrecomilla solo; el problema aparece con whereRaw / orderByRaw.
|
| Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiarios', function (Blueprint $table) {
            $table->id();

            // --- Documento de identidad
            $table->string('ci', 30);
            $table->string('complemento', 5)->nullable()->comment('Complemento de CI boliviano');
            $table->string('expedido', 5)->nullable()->comment('BN, LP, SC, CB...');

            // --- Nombre, en cinco partes porque así lo trae la cédula. Partirlo
            //     después con código no se puede acertar siempre. No hay columna
            //     `nombreCompleto`: se arma en el modelo.
            $table->string('primerNombre', 60);
            $table->string('segundoNombre', 60)->nullable()->comment('Mucha gente no tiene');
            $table->string('apellidoPaterno', 60);
            $table->string('apellidoMaterno', 60)->nullable();
            $table->string('apellidoCasado', 60)->nullable()->comment('Sin el "de": se agrega al imprimir');

            // --- Datos personales
            $table->date('fechaNacimiento');
            $table->string('genero', 20)->nullable()->comment('masculino | femenino');
            $table->string('nacionalidad', 60)->default('Boliviana');

            // --- Contacto
            $table->text('direccion')->nullable();
            $table->string('ciudad', 80)->nullable();
            $table->string('provincia', 80)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('foto')->nullable()->comment('Ruta del archivo; la sube StorageController');

            // Para el orden alfabético del listado, no para la búsqueda.
            $table->index(['apellidoPaterno', 'apellidoMaterno', 'primerNombre'], 'beneficiarios_nombre_index');

            // Sin `created_by` / `updated_by`: eso lo guarda `auditorias`.
            $table->timestamps();
            $table->softDeletes();
        });

        /*
         * UNA PERSONA, UNA FICHA. Cargada dos veces, sacaría dos credenciales
         * del mismo tipo — o sea, el doble de cupo de pesca.
         *
         * Índice PARCIAL y no `unique()` con `deleted_at` adentro: en SQL
         * NULL != NULL y ese unique no bloquearía nada.
         *
         * Va sobre `ci` SOLO: el complemento es parte del mismo documento, y con
         * él adentro la misma persona pasaría cargada una vez con y otra sin.
         */
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX beneficiarios_ci_unico
                ON beneficiarios (ci)
                WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiarios');
    }
};

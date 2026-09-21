<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Beneficiarios — la persona, UNA SOLA VEZ.
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
            /*
             * DÓNDE SE EXPIDIÓ LA CÉDULA, por ID y no con el código copiado:
             * el departamento vive en su tabla y acá va la referencia, nada
             * más. El código —«BN»— se lee de la relación al imprimir, así que
             * quien muestre la cédula completa tiene que cargar
             * `beneficiario.departamento` en el `with()`.
             */
            $table->foreignId('departamento_id')
                ->nullable()
                ->constrained('departamentos')
                ->restrictOnDelete();

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

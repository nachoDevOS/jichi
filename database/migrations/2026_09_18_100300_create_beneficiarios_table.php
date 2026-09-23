<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Beneficiarios — la persona, UNA SOLA VEZ. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiarios', function (Blueprint $table) {
            $table->id();

            $table->string('ci', 30);
            $table->string('complemento', 5)->nullable();

            // Por id y no con el código copiado: «BN» se lee de la relación al
            // imprimir, así que quien muestre la cédula carga `departamento`.
            $table->foreignId('departamento_id')
                ->nullable()
                ->constrained('departamentos')
                ->restrictOnDelete();

            // En cinco partes porque así lo trae la cédula: partirlo después
            // con código no se acierta siempre. `nombreCompleto` lo arma el modelo.
            $table->string('primerNombre', 60);
            $table->string('segundoNombre', 60)->nullable();
            $table->string('apellidoPaterno', 60);
            $table->string('apellidoMaterno', 60)->nullable();
            $table->string('apellidoCasado', 60)->nullable()->comment('Sin el «de»: se agrega al imprimir');

            $table->date('fechaNacimiento');
            $table->string('genero', 20)->nullable()->comment('masculino | femenino');
            $table->string('nacionalidad', 60)->default('Boliviana');

            $table->text('direccion')->nullable();
            $table->string('ciudad', 80)->nullable();
            $table->string('provincia', 80)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('foto')->nullable()->comment('Ruta; la sube StorageController');

            // Para el orden alfabético del listado, no para la búsqueda.
            $table->index(['apellidoPaterno', 'apellidoMaterno', 'primerNombre'], 'beneficiarios_nombre_index');

            // Sin `created_by`: eso lo guarda `auditorias`.
            $table->timestamps();
            $table->softDeletes();
        });

        // UNA PERSONA, UNA FICHA. Parcial y no inline: con `deleted_at` adentro
        // el índice no bloquea nada, porque en SQL NULL != NULL.
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

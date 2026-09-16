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
| EL NOMBRE VA PARTIDO EN CINCO COLUMNAS, NO EN UNA
|
| La cédula boliviana trae el nombre separado y los formularios en papel lo
| piden separado. Guardarlo en un solo campo obligaría a partirlo después con
| código, y ahí no hay forma de acertar siempre: «Rosa Elena Antezana Áñez»
| puede ser dos nombres y dos apellidos, o un nombre y tres apellidos, y la
| computadora no puede saberlo. Se pide separado porque separado es como llega.
|
| El apellido de casada se guarda SIN el «de». En la base va «Justiniano»; el
| «de Justiniano» lo arma el sistema al imprimir. Si el «de» estuviera dentro
| de la columna, buscar «Justiniano» no encontraría a esa persona.
|
| NOTA SOBRE LOS NOMBRES DE COLUMNA
|
| De `primerNombre` en adelante las columnas van en camelCase, por pedido
| expreso. En PostgreSQL eso obliga a entrecomillarlas en toda consulta escrita
| a mano:
|
|     SELECT primerNombre FROM beneficiarios;     -- ERROR
|     SELECT "primerNombre" FROM beneficiarios;   -- así sí
|
| Laravel y Eloquent no se ven afectados porque siempre entrecomillan solos.
| El problema aparece al usar whereRaw / orderByRaw (ver Beneficiario::SQL_NOMBRE).
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

            // El departamento donde se expidió la cédula. Se imprime al lado
            // del número en el carnet: «C.I. 7656924 BN».
            $table->string('expedido', 5)->nullable()->comment('BN, LP, SC, CB...');

            // --- Nombre, tal como figura en la cédula
            $table->string('primerNombre', 60);
            $table->string('segundoNombre', 60)->nullable()->comment('Mucha gente no tiene');
            $table->string('apellidoPaterno', 60);
            $table->string('apellidoMaterno', 60)->nullable();
            $table->string('apellidoCasado', 60)->nullable()->comment('Sin el "de": se agrega al imprimir');

            // NO hay columna `nombreCompleto`. El nombre armado no se guarda:
            // se concatena cuando hace falta, en el modelo. Así no puede quedar
            // desfasado de sus partes —corregir un apellido cambia el nombre
            // impreso en el acto—. Ver Beneficiario::nombreCompleto().

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

            // Ruta dentro de storage/app/public. Se imprime en el carnet.
            $table->string('foto')->nullable();

            // No hay `created_by` ni `updated_by`. Quién cargó y quién modificó
            // cada ficha queda registrado igual, en la tabla `auditorias`: el
            // trait Auditable escribe una fila por cada alta, edición y baja,
            // con el usuario y el detalle de lo que cambió. Dos columnas acá
            // solo repetirían —y peor— lo que esa tabla ya guarda completo.
            $table->timestamps();
            $table->softDeletes();

            // Índice sobre las partes del nombre, en el orden en que se ordena
            // el padrón. No sirve para la BÚSQUEDA —que compara contra el
            // nombre concatenado y por eso recorre la tabla entera—, pero sí
            // para el orden alfabético de cada página del listado.
            $table->index(['apellidoPaterno', 'apellidoMaterno', 'primerNombre'], 'beneficiarios_nombre_index');
        });

        /*
         * UNA PERSONA, UNA FICHA — y por qué el índice es PARCIAL.
         *
         * El script SQL de referencia no traía esta restricción, pero sin ella
         * nada impide cargar dos veces al mismo pescador, y entonces
         * `carnets_beneficiario_gestion_unique` deja de servir: la misma
         * persona sacaría dos carnets en la misma gestión, uno por cada ficha
         * duplicada. La regla de negocio «un carnet por persona por año» se
         * apoya sobre esta.
         *
         * OJO CON EL BORRADO LÓGICO: la tentación es escribir
         * unique(['ci_nit','complemento','deleted_at']) para que una ficha dada
         * de baja no bloquee el alta de una nueva. NO FUNCIONA: en SQL
         * `NULL != NULL`, así que dos filas vivas —las dos con deleted_at en
         * NULL— se consideran distintas y el índice no bloquea nada. La forma
         * correcta es un índice PARCIAL, que solo indexa las filas vivas.
         *
         * El COALESCE sobre `complemento` es por lo mismo: el complemento es
         * opcional, y sin él dos fichas con la misma cédula y sin complemento
         * pasarían el índice sin chistar.
         *
         * SQLite entiende esta misma sintaxis, así que la sentencia sirve en
         * los dos motores y las pruebas en memoria la ejecutan igual.
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

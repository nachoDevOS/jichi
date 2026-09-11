<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Solicitantes — los pescadores que hacen trámites
|--------------------------------------------------------------------------
|
| EL NOMBRE VA PARTIDO EN CINCO COLUMNAS, NO EN UNA
|
| La cédula boliviana trae el nombre separado y los formularios en papel del
| SEDAG lo piden separado. Guardarlo en un solo campo obligaría a partirlo
| después con código, y ahí no hay forma de acertar siempre: «Rosa Elena
| Antezana Áñez» puede ser dos nombres y dos apellidos, o un nombre y tres
| apellidos, y la computadora no puede saberlo. Se pide separado porque
| separado es como llega.
|
| El apellido de casada se guarda SIN el «de». En la base va «Justiniano»;
| el «de Justiniano» lo arma el sistema al imprimir. Si el «de» estuviera
| dentro de la columna, buscar «Justiniano» no encontraría a esa persona.
|
| NOTA SOBRE LOS NOMBRES DE COLUMNA
|
| De `primerNombre` en adelante las columnas van en camelCase, por pedido
| expreso. En PostgreSQL eso obliga a entrecomillarlas en toda consulta
| escrita a mano:
|
|     SELECT primerNombre FROM solicitantes;     -- ERROR
|     SELECT "primerNombre" FROM solicitantes;   -- así sí
|
| Laravel y Eloquent no se ven afectados porque siempre entrecomillan solos.
| `ci_nit`, `complemento` y `expedido` conservan el guión bajo o van en una
| sola palabra.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitantes', function (Blueprint $table) {
            $table->id();

            // --- Documento de identidad
            $table->string('ci_nit', 30);
            $table->string('complemento', 5)->nullable()->comment('Complemento de CI boliviano');

            // El departamento donde se expidió la cédula. Se imprime al lado
            // del número en la credencial: «C.I. 7656924 BN».
            $table->string('expedido', 5)->nullable()->comment('BN, LP, SC, CB...');

            // --- Nombre, tal como figura en la cédula
            $table->string('primerNombre', 60);
            $table->string('segundoNombre', 60)->nullable()->comment('Mucha gente no tiene');
            $table->string('apellidoPaterno', 60)->nullable();
            $table->string('apellidoMaterno', 60)->nullable();
            $table->string('apellidoCasada', 60)->nullable()->comment('Sin el "de": se agrega al imprimir');

            // NO hay columna `nombreCompleto`. El nombre armado no se guarda:
            // se concatena cuando hace falta, en el modelo. Así no puede
            // quedar desfasado de sus partes —corregir un apellido cambia el
            // nombre impreso en el acto, sin depender de que algo lo
            // recalcule—. Ver Solicitante::nombreCompleto().

            // --- Datos personales
            $table->date('fechaNacimiento')->nullable();
            $table->string('genero', 20)->nullable()->comment('masculino | femenino');
            $table->string('nacionalidad')->default('Boliviana');

            // --- Contacto
            $table->text('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('provincia')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();

            // Ruta dentro de storage/app/public. Se usa en las credenciales.
            $table->string('foto')->nullable();

            // No hay `created_by` ni `updated_by`. Quién cargó y quién
            // modificó cada ficha queda registrado igual, en la tabla
            // `auditorias`: el trait Auditable escribe una fila por cada alta,
            // edición y baja, con el usuario y el detalle de lo que cambió.
            // Dos columnas acá solo repetirían —y peor— lo que esa tabla ya
            // guarda completo. Ver app/Traits/Auditable.php.
            $table->timestamps();
            $table->softDeletes();

            // OJO: este índice no protege nada por incluir deleted_at, y la
            // migración 2026_09_08_100000 lo reemplaza por uno parcial. Se
            // deja acá tal como estaba porque esa migración explica el error
            // y necesita encontrarlo para corregirlo.
            $table->unique(['ci_nit', 'complemento', 'deleted_at']);

            // Índice sobre las partes del nombre, en el orden en que se ordena
            // el listado. No sirve para la BÚSQUEDA —que compara contra el
            // nombre concatenado y por eso recorre la tabla entera—, pero sí
            // para el orden alfabético de cada página del padrón.
            $table->index(['apellidoPaterno', 'apellidoMaterno', 'primerNombre'], 'solicitantes_nombre_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitantes');
    }
};

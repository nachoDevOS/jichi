<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Departamentos — los nueve de Bolivia, para el «expedido» de la cédula.
|
| Es un catálogo CERRADO: no tiene pantalla ni CRUD, porque no se agrega un
| departamento. Por eso se siembra acá y no en un seeder — la tabla tiene que
| existir llena aunque nadie corra `db:seed`, o no se puede cargar ni una ficha.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();

            // EL CÓDIGO ES LA LLAVE DE NEGOCIO: es lo que el SEGIP imprime en
            // la cédula y lo que `beneficiarios.expedido` referencia.
            $table->string('codigo', 5)->unique()->comment('BN, LP, SC…');
            $table->string('nombre', 60);

            // Sin timestamps ni softDeletes, al revés que el resto del dominio:
            // no es una fila que alguien cargue, corrija o dé de baja.
        });

        /*
         * LA LISTA SALE DE `config('jichi.expedido')`, que ya la tenía escrita.
         * Se siembra desde ahí para no dejar los nueve nombres en dos lugares
         * que después se contradicen.
         */
        $filas = collect(config('jichi.expedido', []))
            ->map(fn (string $nombre, string $codigo): array => [
                'codigo' => $codigo,
                'nombre' => $nombre,
            ])
            ->values()
            ->all();

        if ($filas !== []) {
            DB::table('departamentos')->insert($filas);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};

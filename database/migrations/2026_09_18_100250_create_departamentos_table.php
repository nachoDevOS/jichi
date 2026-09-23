<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Departamentos — los nueve de Bolivia, para el «expedido» de la cédula.
|
| Catálogo CERRADO: se siembra acá y no en un seeder porque la tabla tiene que
| existir llena aunque nadie corra `db:seed`. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();

            // La llave de negocio: es lo que el SEGIP imprime en la cédula.
            $table->string('codigo', 5)->unique()->comment('BN, LP, SC…');
            $table->string('nombre', 60);

            // Sin timestamps ni softDeletes: no es una fila que alguien cargue.
        });

        // Desde la config, que ya tenía la lista: escrita en dos lugares, se
        // contradicen.
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

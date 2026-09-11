<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Corrección: el contador de escaneos del QR se podía desbordar
|--------------------------------------------------------------------------
|
| EL PROBLEMA
|
| La columna `veces_verificado` se creó como unsignedSmallInteger. En
| PostgreSQL no existen los enteros sin signo, así que Laravel lo traduce a
| `smallint`, cuyo valor máximo es 32.767.
|
| Esa columna la incrementa la página pública de verificación: cada vez que
| alguien escanea el QR de un permiso, sube uno. Un solo documento muy
| consultado (o un robot recorriendo la URL) llega a 32.767 y a partir de
| ahí la base de datos responde:
|
|     ERROR: smallint out of range
|
| ...y la página pública de ese documento deja de cargar. Un contador de
| estadística terminaría tumbando la función más importante del sistema.
|
| LA SOLUCIÓN
|
| Pasar la columna a `integer` (máximo 2.147.483.647). Un contador que sube
| de a uno por escaneo no llega ahí ni en un siglo.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->unsignedInteger('veces_verificado')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->unsignedSmallInteger('veces_verificado')->default(0)->change();
        });
    }
};

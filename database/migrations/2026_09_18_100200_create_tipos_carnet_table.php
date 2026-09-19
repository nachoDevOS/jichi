<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Tipos de carnet — el catálogo de credenciales y su arancel.
|
| `precio_bs` es el precio de HOY, para armar un cobro nuevo. Lo cobrado de
| verdad queda en `pagos` y no se recalcula: un carnet emitido a 80 Bs sigue
| diciendo 80 aunque el arancel suba.
|
| NO confundir con `carnets.tipo_actor`: eso es la REGLA —qué habilita el
| documento— y vive en un enum. Esto es el CATÁLOGO —cómo se llama y cuánto
| sale— y nunca se decide nada con un match sobre este nombre.
|
| Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_carnet', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 120);
            $table->decimal('precio_bs', 10, 2)->default(0);
            $table->boolean('estado')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        // Dos tipos con el mismo nombre serían indistinguibles en un desplegable.
        // PARCIAL por lo mismo que en los otros catálogos: uno dado de baja
        // libera su nombre, y un unique con `deleted_at` adentro no bloquea nada
        // porque en SQL NULL != NULL.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX tipos_carnet_nombre_unico
                ON tipos_carnet (nombre)
                WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_carnet');
    }
};

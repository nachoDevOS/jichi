<?php

use App\Enums\TipoActor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tipos de carnet — el catálogo de credenciales y su arancel. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_carnet', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 120);

            // Sin esta columna la coherencia con `carnets.tipo_actor` solo se
            // podía mirar contra el NOMBRE, que la unidad edita.
            $table->string('tipo_actor', 20)->default(TipoActor::Pescador->value);

            // El precio NO vive acá: lo pone SIREB y se congela en cada carnet. Ver docs/modulos/SIREB.md.
            // Nullable: los tipos sembrados no traen tarifa hasta que alguien la elige.
            $table->uuid('servicio_sireb')->nullable()->comment('Id del servicio en SIREB');
            $table->uuid('tarifa_sireb')->nullable()->comment('Id de la tarifa del tipo dentro del servicio');
            $table->json('sireb_historial')->nullable()->comment('Servicio y tarifa que tuvo antes, con desde/hasta');
            $table->boolean('estado')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        // El nombre único lo exige el Request, no la base. Ver docs/MER.md.
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_carnet');
    }
};

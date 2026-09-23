<?php

use App\Enums\EstadoAprovechamiento;
use App\Enums\ModalidadAprovechamiento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Aprovechamientos pesqueros — la bolsa madre del pescador. Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aprovechamientos_pesq', function (Blueprint $table) {
            $table->id();

            // RESTRICT: borrar a una persona no puede llevarse el historial que
            // respalda cuánto se pescó bajo su nombre.
            $table->foreignId('beneficiario_id')->constrained('beneficiarios')->restrictOnDelete();
            $table->foreignId('categoria_aprov_id')->constrained('categorias_aprovechamiento')->restrictOnDelete();

            // Los dos son COPIA CONGELADA del tramo: editar el catálogo en
            // agosto no puede mover un cupo otorgado en marzo.
            $table->string('modalidad', 30)
                ->default(ModalidadAprovechamiento::EscalaGeneral->value)
                ->comment('Copiada del tramo al otorgar');
            $table->decimal('volumen_total_kg', 12, 2)->comment('Kilos, copiados de la escala');

            // Renglón del talonario. Texto libre: no hay padrón de embarcaciones.
            $table->string('tipo_embarcacion', 120)->comment('Canoa, peque-peque, bote…');

            $table->string('estado', 20)->default(EstadoAprovechamiento::Pendiente->value);

            // El día que se pidió y el día que se firmó son distintos.
            $table->date('fecha_solicitud');
            $table->date('fecha_emision')->nullable()->comment('Se llena al aprobar');
            $table->date('fecha_vencimiento')->index()->comment('Índice: lo lee el comando diario');

            // «¿Esta persona tiene bolsa?» es la consulta caliente.
            $table->index(['beneficiario_id', 'estado']);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aprovechamientos_pesq');
    }
};

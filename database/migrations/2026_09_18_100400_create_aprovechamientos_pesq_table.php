<?php

use App\Enums\EstadoAprovechamiento;
use App\Enums\ModalidadAprovechamiento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Aprovechamientos pesqueros — la BOLSA MADRE del pescador.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aprovechamientos_pesq', function (Blueprint $table) {
            $table->id();

            // RESTRICT y no CASCADE: borrar a una persona no puede llevarse su
            // historial de cupos, que respalda cuánto se pescó bajo su nombre.
            $table->foreignId('beneficiario_id')->constrained('beneficiarios')->restrictOnDelete();
            $table->foreignId('categoria_aprov_id')->constrained('categorias_aprovechamiento')->restrictOnDelete();

            // COPIA CONGELADA del tramo, igual que el volumen: reclasificar el
            // tramo en el catálogo no puede cambiarle el régimen a lo ya otorgado.
            $table->string('modalidad', 30)
                ->default(ModalidadAprovechamiento::EscalaGeneral->value)
                ->comment('Copiada del tramo al otorgar');

            // También congelado: un cupo dado en marzo bajo una escala de 500 kg
            // no pasa a valer 800 porque alguien editó el catálogo en agosto.
            $table->decimal('volumen_total_kg', 12, 2)->comment('Kilos otorgados, COPIADOS de la escala');

            // El renglón «Tipo de Embarcación» del talonario. Texto libre porque
            // no hay padrón de embarcaciones; nullable porque el papel tampoco
            // lo exige.
            $table->string('tipo_embarcacion', 120)
                ->nullable()
                ->comment('Canoa, peque-peque, bote…');

            // Nace PENDIENTE y solo la caja lo activa, al cobrarlo entero: la
            // concesión pagada ES la autorización. Ver CobrarService.
            $table->string('estado', 20)->default(EstadoAprovechamiento::Pendiente->value);

            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');

            // «¿Esta persona tiene bolsa?» es la pregunta caliente, y el filtro
            // por persona es el que reduce de golpe el conjunto.
            $table->index(['beneficiario_id', 'estado']);

            // Para el comando diario que marca los vencidos.
            $table->index('fecha_vencimiento');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aprovechamientos_pesq');
    }
};

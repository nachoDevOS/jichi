<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exenciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_tramite_id')->nullable()->constrained('tipos_tramite')
                ->cascadeOnUpdate()->cascadeOnDelete()
                ->comment('NULL = exencion aplicable a cualquier tramite');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('tipo', 20)->default('porcentaje')->comment('porcentaje | monto_fijo');
            $table->decimal('valor', 12, 2)->comment('0-100 si es porcentaje, monto en BOB si es fijo');
            $table->string('respaldo_legal')->nullable();
            $table->boolean('requiere_documento')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exenciones');
    }
};

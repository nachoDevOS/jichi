<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->string('codigo', 20)->unique()->comment('Prefijo usado en la numeracion correlativa, ej: PESCA');
            $table->text('descripcion')->nullable();
            $table->string('icono', 20)->nullable()->comment('Emoji o nombre de icono lucide');
            $table->string('color', 9)->nullable()->comment('Color hex para graficos del dashboard');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};

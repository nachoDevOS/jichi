<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->text('valor')->nullable();
            $table->string('tipo', 20)->default('string')->comment('string | number | boolean | json | archivo');
            $table->string('grupo', 40)->default('general')->comment('general | municipio | documentos | caja');
            $table->string('etiqueta');
            $table->text('descripcion')->nullable();
            $table->boolean('publico')->default(false)->comment('Expuesto en la vista de verificacion publica');
            $table->timestamps();

            $table->index('grupo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};

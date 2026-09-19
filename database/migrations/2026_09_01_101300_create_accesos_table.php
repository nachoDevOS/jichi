<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable()->comment('Se guarda aunque el login falle y no exista el usuario');
            $table->string('evento', 30)->comment('login | logout | fallido | bloqueado');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            // La columna suelta y NO timestamps(), a propósito: esto es una
            // bitácora y una fila nunca se modifica, así que `updated_at`
            // siempre valdría lo mismo que `created_at`.
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['evento', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos');
    }
};

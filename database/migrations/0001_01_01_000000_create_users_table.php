<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        /*
         * NO HAY TABLA DE RECUPERACION DE CONTRASEÑA.
         *
         * Laravel la trae de fabrica —password_reset_tokens—, pero este sistema
         * no tiene «olvide mi contraseña»: las cuentas las crea el
         * administrador y ahi mismo se resetean. Ver routes/auth.php, que solo
         * declara login y logout.
         *
         * Se retira porque una tabla que nadie escribe ni lee es una tabla que
         * el proximo que lea el esquema va a tratar de entender. Si algun dia
         * se agrega la recuperacion, vuelve con el flujo que la use.
         */
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};

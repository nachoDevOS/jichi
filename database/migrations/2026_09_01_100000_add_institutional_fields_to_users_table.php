<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Lleno = cuenta del PORTAL: solo entra a /mi-cuenta, nunca al panel.
            // Sin FK: `beneficiarios` se crea después. Ver docs/MER.md.
            $table->unsignedBigInteger('beneficiario_id')->nullable()->after('id');
            $table->string('ci', 20)->nullable()->after('name');
            // Vínculo con Ibare: el `sub` del token es este id de mamoré.
            $table->string('mamore_id', 50)->nullable()->unique()->after('ci');
            $table->string('cargo')->nullable()->after('email');
            $table->string('telefono', 30)->nullable()->after('cargo');
            $table->boolean('activo')->default(true)->after('telefono');
            $table->timestamp('ultimo_acceso_at')->nullable()->after('activo');
            // La clave que se entrega en ventanilla es temporal: al entrar hay que cambiarla.
            $table->boolean('debe_cambiar_password')->default(false)->after('password');
            $table->softDeletes();
        });

        // Una cuenta de portal por beneficiario; la baja lógica libera el lugar.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX users_beneficiario_unico
                ON users (beneficiario_id)
                WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_beneficiario_unico');

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['beneficiario_id', 'ci', 'mamore_id', 'cargo', 'telefono', 'activo', 'ultimo_acceso_at', 'debe_cambiar_password']);
        });
    }
};

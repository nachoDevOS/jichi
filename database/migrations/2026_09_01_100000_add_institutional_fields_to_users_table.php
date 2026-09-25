<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ci', 20)->nullable()->after('name');
            // Vínculo con Ibare: el `sub` del token es este id de mamoré.
            $table->string('mamore_id', 50)->nullable()->unique()->after('ci');
            $table->string('cargo')->nullable()->after('email');
            $table->string('telefono', 30)->nullable()->after('cargo');
            $table->boolean('activo')->default(true)->after('telefono');
            $table->timestamp('ultimo_acceso_at')->nullable()->after('activo');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['ci', 'mamore_id', 'cargo', 'telefono', 'activo', 'ultimo_acceso_at']);
        });
    }
};

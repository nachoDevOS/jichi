<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Corrección: los índices únicos no estaban protegiendo nada
|--------------------------------------------------------------------------
|
| EL PROBLEMA
|
| La tabla `solicitantes` declaraba su índice único incluyendo la columna
| `deleted_at` (el borrado lógico de Laravel):
|
|     $table->unique(['ci_nit', 'complemento', 'deleted_at']);
|
| La intención era buena: permitir que un registro borrado y uno vivo
| convivan con el mismo CI. Pero en SQL vale una regla que rompe todo:
|
|     NULL nunca es igual a NULL.
|
| Una fila viva tiene deleted_at = NULL. Al comparar dos filas vivas, la
| base de datos compara NULL con NULL, decide que son distintas, y deja
| pasar el duplicado. El índice existía pero jamás bloqueaba nada.
|
| CONSECUENCIA REAL QUE ESTO CAUSABA
|
| Se podían registrar DOS solicitantes con la misma cédula. Ventanilla
| terminaba con el mismo pescador cargado dos veces y su historial de
| trámites partido entre ambas fichas.
|
| La misma falla afectaba a `cierres_caja` —un operador podía tener dos
| cajas abiertas el mismo día—, pero ese módulo se eliminó del sistema y
| la tabla ya no existe.
|
| LA SOLUCIÓN
|
| Un índice único PARCIAL: solo aplica a las filas vivas.
|
|     CREATE UNIQUE INDEX ... WHERE deleted_at IS NULL
|
| Así `deleted_at` sale de la comparación y pasa a ser un filtro. Las filas
| borradas quedan fuera del índice y no estorban.
|
| Para `solicitantes` hay un segundo detalle: `complemento` también puede
| ser NULL, y volvería a caer en la misma trampa. Por eso el índice se
| construye sobre COALESCE(complemento, ''), que convierte el NULL en
| cadena vacía y lo vuelve comparable.
|
| NOTA SOBRE MOTORES: los índices parciales existen en PostgreSQL y SQLite
| (que son los dos motores que usa este proyecto) pero NO en MySQL. Por eso
| la migración pregunta primero qué motor está corriendo.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        $motor = Schema::getConnection()->getDriverName();

        // Se quitan los índices viejos, los que incluían deleted_at.
        Schema::table('solicitantes', function (Blueprint $table) {
            $table->dropUnique(['ci_nit', 'complemento', 'deleted_at']);
        });

        if (in_array($motor, ['pgsql', 'sqlite'], true)) {
            // Un solicitante activo por cédula + complemento.
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX solicitantes_ci_nit_vivo_unique
                ON solicitantes (ci_nit, COALESCE(complemento, ''))
                WHERE deleted_at IS NULL
            SQL);

        } else {
            // MySQL/MariaDB no soportan índices parciales. Se deja el índice
            // completo, que al menos impide duplicados exactos, y queda como
            // deuda técnica documentada.
            Schema::table('solicitantes', function (Blueprint $table) {
                $table->unique(['ci_nit', 'complemento'], 'solicitantes_ci_nit_vivo_unique');
            });
        }
    }

    public function down(): void
    {
        $motor = Schema::getConnection()->getDriverName();

        if (in_array($motor, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS solicitantes_ci_nit_vivo_unique');
        } else {
            Schema::table('solicitantes', function (Blueprint $table) {
                $table->dropUnique('solicitantes_ci_nit_vivo_unique');
            });
        }

        Schema::table('solicitantes', function (Blueprint $table) {
            $table->unique(['ci_nit', 'complemento', 'deleted_at']);
        });
    }
};

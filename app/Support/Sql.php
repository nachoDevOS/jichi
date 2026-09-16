<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Fragmentos de SQL que cambian entre motores.
 *
 * El sistema corre sobre PostgreSQL en producción, pero en desarrollo puede
 * usarse SQLite. Todo lo que no sea SQL estándar pasa por acá para que no
 * haya consultas que funcionen en un motor y revienten en el otro.
 */
class Sql
{
    /**
     * Operador de comparación insensible a mayúsculas para búsquedas de texto.
     *
     * PostgreSQL tiene ILIKE. SQLite y MySQL ya tratan LIKE como insensible
     * para ASCII, que es suficiente para nombres y números de documento.
     */
    public static function like(?Connection $conexion = null): string
    {
        return ($conexion ?? DB::connection())->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * Expresión que reduce una columna de fecha al día 'YYYY-MM-DD', para
     * agrupar por jornada.
     *
     * No alcanza con `DATE($columna)`: en PostgreSQL eso devuelve un tipo date
     * y en SQLite una cadena, así que la clave con la que vuelve el resultado
     * cambia de forma según el motor y el `pluck` deja de encontrarla. Forzando
     * el texto 'YYYY-MM-DD' en los dos, la clave es la misma.
     */
    public static function periodoDia(string $columna, ?Connection $conexion = null): Expression
    {
        $driver = ($conexion ?? DB::connection())->getDriverName();

        return DB::raw(match ($driver) {
            'pgsql' => "to_char($columna, 'YYYY-MM-DD')",
            'sqlite' => "strftime('%Y-%m-%d', $columna)",
            'mysql', 'mariadb' => "date_format($columna, '%Y-%m-%d')",
            'sqlsrv' => "format($columna, 'yyyy-MM-dd')",
            default => "to_char($columna, 'YYYY-MM-DD')",
        });
    }

    /**
     * Expresión que reduce una columna de fecha al período 'YYYY-MM',
     * para agrupar por mes.
     */
    public static function periodoMes(string $columna, ?Connection $conexion = null): Expression
    {
        $driver = ($conexion ?? DB::connection())->getDriverName();

        return DB::raw(match ($driver) {
            'pgsql' => "to_char($columna, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', $columna)",
            'mysql', 'mariadb' => "date_format($columna, '%Y-%m')",
            'sqlsrv' => "format($columna, 'yyyy-MM')",
            default => "to_char($columna, 'YYYY-MM')",
        });
    }
}

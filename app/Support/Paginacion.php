<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Cuántas filas por página muestra un listado del panel.
 */
class Paginacion
{
    /**
     * Los tamaños de página que la pantalla puede pedir.
     *
     * @var array<int, int>
     */
    public const OPCIONES = [15, 30, 50];

    /**
     * Lee el tamaño pedido y lo deja en un valor permitido.
     */
    public static function filas(Request $request): int
    {
        $pedido = $request->integer('por_pagina');

        if (in_array($pedido, self::OPCIONES, true)) {
            return $pedido;
        }

        $porDefecto = (int) config('jichi.por_pagina');

        return in_array($porDefecto, self::OPCIONES, true)
            ? $porDefecto
            : self::OPCIONES[0];
    }
}

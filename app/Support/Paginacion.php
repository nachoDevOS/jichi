<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Cuántas filas por página muestra un listado del panel.
 *
 * POR QUÉ LA LISTA ES CERRADA
 *
 * El número llega por la barra de direcciones (?por_pagina=30), así que
 * cualquiera puede escribir lo que se le ocurra. Sin una lista cerrada, un
 * ?por_pagina=500000 traería la tabla entera a memoria y voltearía el
 * servidor —y no haría falta mala intención: alcanza con que alguien juegue
 * con la dirección—.
 *
 * Cualquier valor que no esté en OPCIONES se ignora y se usa el de siempre.
 *
 * POR QUÉ VIVE ACÁ Y NO EN CADA CONTROLADOR
 *
 * Porque es una regla de seguridad, y una regla de seguridad escrita en cuatro
 * lugares es una regla que tarde o temprano queda distinta en uno de ellos.
 * Los listados de beneficiarios, trámites, carnets y pagos la usan; los de
 * reportes la van a usar mañana.
 */
class Paginacion
{
    /**
     * Los tamaños de página que la pantalla puede pedir.
     *
     * El primero es el que se usa cuando no se pide nada, y es también el que
     * el selector muestra marcado al entrar.
     *
     * @var array<int, int>
     */
    public const OPCIONES = [15, 30, 50];

    /**
     * Lee el tamaño pedido y lo deja en un valor permitido.
     *
     * El valor por defecto sale de `config('jichi.por_pagina')`, que es el del
     * sistema entero. Si alguien lo cambiara ahí por un número que no está
     * entre las opciones, el selector quedaría marcando una opción que no es
     * la que se ve; por eso en ese caso se cae a la primera de la lista, que
     * siempre existe.
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

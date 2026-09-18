<?php

namespace App\Enums;

/**
 * En qué situación está un permiso operativo: una FAENA o una GUÍA.
 *
 * ----------------------------------------------------------------------------
 *  DOS VALORES, Y ESA POBREZA ES DELIBERADA
 * ----------------------------------------------------------------------------
 *
 * Un trámite recorre un circuito —se arma, se presenta, se firma— porque es un
 * expediente. Una faena y una guía no: se llenan en el mostrador, se cobran y
 * se entregan en el acto. Nacen valiendo.
 *
 * Por eso no hay «pendiente» ni «en revisión». Agregarlos obligaría a que
 * alguien confirmara un papel que la persona ya se llevó, y lo que quedaría en
 * la base es un montón de permisos colgados que en la realidad están vigentes.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ SE ANULA Y NO SE BORRA
 * ----------------------------------------------------------------------------
 *
 * El número sale del TALONARIO DE PAPEL. Emitido mal, ese número ya se gastó y
 * la hoja puede estar circulando. Borrar la fila deja un hueco en la serie que
 * después nadie puede explicar, y peor: deja libre un número que el índice
 * único volvería a aceptar, así que dos permisos distintos podrían terminar
 * diciendo ser el mismo papel.
 *
 * ----------------------------------------------------------------------------
 *  ES COMPARTIDO POR LAS DOS TABLAS A PROPÓSITO
 * ----------------------------------------------------------------------------
 *
 * Faenas y guías son documentos distintos, pero su ciclo de vida es idéntico.
 * Dos enums iguales se separan con el tiempo sin que nadie lo decida: alguien
 * agrega un estado de un lado y la pantalla del otro deja de entenderlo. Si
 * algún día uno de los dos necesita un estado propio, ahí se parten.
 */
enum EstadoPermiso: string
{
    /** Vale. Es como nace todo permiso. */
    case Emitido = 'emitido';

    /**
     * Dado de baja. El papel existe, se puede consultar y su número sigue
     * ocupado, pero no autoriza nada.
     *
     * No se vuelve atrás: si hizo falta anularlo, lo que corresponde es emitir
     * uno nuevo con otro número del talonario. «Desanular» dejaría vigente un
     * papel que alguien ya sabe que no vale.
     */
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Emitido => 'Emitido',
            self::Anulado => 'Anulado',
        };
    }

    /**
     * Nombre del color del badge.
     *
     * Devuelve el NOMBRE y no las clases armadas con texto: Tailwind solo
     * incluye en el CSS final las que puede leer literalmente. Un color nuevo
     * acá tiene que agregarse también al mapa de
     * resources/js/components/ui/badge.tsx.
     */
    public function color(): string
    {
        return match ($this) {
            self::Emitido => 'emerald',
            self::Anulado => 'rose',
        };
    }

    /** ¿Este permiso autoriza a trabajar? */
    public function habilita(): bool
    {
        return $this === self::Emitido;
    }

    /**
     * ¿Se le pueden seguir cargando depósitos?
     *
     * Sobre un permiso anulado no, aunque quede saldo: lo que se debe se
     * resuelve por caja, no cargando plata a un papel que no vale.
     */
    public function admitePagos(): bool
    {
        return $this === self::Emitido;
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $estado): array => [
            'value' => $estado->value,
            'label' => $estado->etiqueta(),
            'color' => $estado->color(),
        ], self::cases());
    }
}

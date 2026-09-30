<?php

namespace App\Enums;

/**
 * Los cobros que no cuelgan de un catálogo: una fila de `aranceles_sireb` por
 * caso. Uno nuevo es un caso más acá y una fila en el seeder, sin migración.
 */
enum ConceptoArancel: string
{
    // La guía NO va acá: cobra por kilo con la tarifa de cada producto.
    case Faena = 'faena';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Faena => 'Permiso de faena',
        };
    }

    /** Qué dice la pantalla debajo del nombre, para que no haya que adivinar. */
    public function descripcion(): string
    {
        return match ($this) {
            self::Faena => 'Se cobra por salida: es el precio que congela cada permiso al emitirse.',
        };
    }
}

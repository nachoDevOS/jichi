<?php

namespace App\Enums;

/**
 * Cómo se cuenta la vigencia de un trámite: hasta fin de año o N días desde la
 * aprobación. Lo elige la unidad en Administración › Vigencias.
 */
enum ModoVigencia: string
{
    case FinDeAnio = 'fin_de_anio';
    case Dias = 'dias';

    public function etiqueta(): string
    {
        return match ($this) {
            self::FinDeAnio => 'Hasta el 31 de diciembre',
            self::Dias => 'Una cantidad de días',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function opciones(): array
    {
        return array_map(fn (self $m): array => ['value' => $m->value, 'label' => $m->etiqueta()], self::cases());
    }
}

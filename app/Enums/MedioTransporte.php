<?php

namespace App\Enums;

/**
 * Por dónde viaja la carga — el casillero 10 del papel.
 */
enum MedioTransporte: string
{
    case Fluvial = 'fluvial';
    case Aerea = 'aerea';
    case Terrestre = 'terrestre';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Fluvial => 'Fluvial',
            self::Aerea => 'Aérea',
            self::Terrestre => 'Terrestre',
        };
    }

    /** La letra del casillero: a, b, c. La usa la maqueta del PDF. */
    public function letra(): string
    {
        return match ($this) {
            self::Fluvial => 'a',
            self::Aerea => 'b',
            self::Terrestre => 'c',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $e): array => [
            'value' => $e->value,
            'label' => $e->etiqueta(),
        ], self::cases());
    }
}

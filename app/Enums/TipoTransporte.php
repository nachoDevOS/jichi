<?php

namespace App\Enums;

/**
 * En qué se traslada — los renglones a/b/c del bloque C del papel.
 *
 * Va aparte de `MedioTransporte` porque el papel los pregunta por separado: el
 * medio dice por dónde viaja y esto dice en qué, y una chata absorbente y una
 * embarcación son las dos fluviales.
 */
enum TipoTransporte: string
{
    case Embarcacion = 'embarcacion';
    case ChataAbsorbente = 'chata_absorbente';
    case Automotriz = 'automotriz';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Embarcacion => 'Embarcaciones',
            self::ChataAbsorbente => 'Chata absorbente',
            self::Automotriz => 'Automotriz',
        };
    }

    /** El renglón del bloque C: a, b, c. */
    public function letra(): string
    {
        return match ($this) {
            self::Embarcacion => 'a',
            self::ChataAbsorbente => 'b',
            self::Automotriz => 'c',
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

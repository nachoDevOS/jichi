<?php

namespace App\Enums;

/**
 * Qué habilita una credencial: PESCAR o COMERCIALIZAR.
 */
enum TipoActor: string
{
    case Pescador = 'pescador';
    case Comercializador = 'comercializador';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pescador => 'Pescador',
            self::Comercializador => 'Comercializador',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pescador => 'sky',
            self::Comercializador => 'violet',
        };
    }

    /**
     * ¿Esta credencial lleva colgada una bolsa madre de aprovechamiento?
     */
    public function requiereAprovechamiento(): bool
    {
        return $this === self::Pescador;
    }

    /** ¿Puede emitir permisos de faena? */
    public function emiteFaenas(): bool
    {
        return $this === self::Pescador;
    }

    /** ¿Puede emitir guías de movimiento? */
    public function emiteGuias(): bool
    {
        return $this === self::Comercializador;
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $e): array => [
            'value' => $e->value,
            'label' => $e->etiqueta(),
            'color' => $e->color(),
        ], self::cases());
    }
}

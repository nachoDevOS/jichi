<?php

namespace App\Enums;

/**
 * Si una asociación se puede elegir hoy en un formulario.
 */
enum EstadoAsociacion: string
{
    case Activo = 'activo';
    case Inactivo = 'inactivo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activo => 'Activa',
            self::Inactivo => 'Inactiva',
        };
    }

    /**
     * Nombre del color del badge, no las clases armadas con texto: Tailwind
     * solo incluye en el CSS final las que puede leer literalmente. Un color
     * nuevo acá va también al mapa de resources/js/components/ui/badge.tsx.
     */
    public function color(): string
    {
        return match ($this) {
            self::Activo => 'emerald',
            self::Inactivo => 'slate',
        };
    }

    /** ¿Se puede elegir en un alta nueva? */
    public function seleccionable(): bool
    {
        return $this === self::Activo;
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

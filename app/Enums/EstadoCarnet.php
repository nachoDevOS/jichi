<?php

namespace App\Enums;

/**
 * En qué situación está una credencial.
 */
enum EstadoCarnet: string
{
    /** Vale. Es como nace toda credencial. */
    case Activo = 'activo';

    /**
     * Dado de baja por decisión de la unidad, antes de su vencimiento.
     */
    case Revocado = 'revocado';

    /** Se le pasó la fecha. Lo que corresponde es emitir el de la gestión nueva. */
    case Vencido = 'vencido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Revocado => 'Revocado',
            self::Vencido => 'Vencido',
        };
    }

    /**
     * Nombre del color del badge. Devuelve el NOMBRE y no las clases armadas
     * con texto: Tailwind solo incluye en el CSS final las que puede leer
     * literalmente. Un color nuevo acá va también al mapa de
     * resources/js/components/ui/badge.tsx.
     */
    public function color(): string
    {
        return match ($this) {
            self::Activo => 'emerald',
            self::Revocado => 'rose',
            self::Vencido => 'slate',
        };
    }

    /**
     * ¿Esta credencial autoriza a trabajar HOY, según su estado?
     *
     * Solo mira el estado; la fecha la agrega `Carnet::estaVigente()`, por lo
     * dicho arriba sobre el desfase de esta columna.
     */
    public function habilita(): bool
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

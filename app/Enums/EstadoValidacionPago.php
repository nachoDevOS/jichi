<?php

namespace App\Enums;

/**
 * El estado del CONTROL de un depósito, que no es el estado del pago.
 */
enum EstadoValidacionPago: string
{
    /** Cargado y sin mirar. Es como nace todo depósito. */
    case Pendiente = 'pendiente';

    /** La boleta cuadra con el extracto del banco. */
    case Validado = 'validado';

    /** No cuadra, con el motivo escrito. Se corrige, no se valida. */
    case Observado = 'observado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Sin validar',
            self::Validado => 'Validado',
            self::Observado => 'Observado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'amber',
            self::Validado => 'emerald',
            self::Observado => 'rose',
        };
    }

    /** Solo lo que nadie miró: un observado se corrige, no se valida. */
    public function admiteControl(): bool
    {
        return $this === self::Pendiente;
    }

    /** ¿Este depósito ya está dado por bueno? */
    public function estaControlado(): bool
    {
        return $this === self::Validado;
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

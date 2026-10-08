<?php

namespace App\Enums;

/**
 * En qué situación está la BOLSA MADRE de un pescador.
 */
enum EstadoAprovechamiento: string
{
    /** Otorgado y esperando el pago en SIREB. Es el borrador: se edita y se elimina. */
    case Pendiente = 'pendiente';

    /** SIREB confirmó el pago. Recién acá autoriza a pescar. */
    case Aprobado = 'aprobado';
    case Agotado = 'agotado';

    /**
     * Dado de baja por la unidad, con motivo, aunque siga en fecha. No autoriza
     * faenas ni carnets nuevos, y libera el lugar para otorgar otro. Ver REGLAS-NEGOCIO.
     */
    case Revocado = 'revocado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Aprobado => 'Aprobado',
            self::Agotado => 'Agotado',
            self::Revocado => 'Revocado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'sky',
            self::Aprobado => 'emerald',
            self::Agotado => 'amber',
            self::Revocado => 'rose',
        };
    }

    /**
     * ¿Según el estado, todavía se le pueden colgar faenas?
     */
    public function habilita(): bool
    {
        return $this === self::Aprobado;
    }

    /**
     * ¿Se puede revocar? Solo lo firmado que todavía cuenta: aprobado o agotado.
     * El borrador se elimina.
     */
    public function permiteRevocacion(): bool
    {
        return $this === self::Aprobado || $this === self::Agotado;
    }

    /**
     * ¿Se pueden corregir sus datos?
     */
    public function permiteEdicion(): bool
    {
        return $this === self::Pendiente;
    }

    /**
     * ¿Se puede borrar la fila entera?
     */
    public function permiteEliminacion(): bool
    {
        return $this === self::Pendiente;
    }

    /** Esperando el pago en SIREB: todavía no autoriza nada. */
    public function estaAbierto(): bool
    {
        return $this === self::Pendiente;
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

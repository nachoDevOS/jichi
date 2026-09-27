<?php

namespace App\Enums;

/**
 * En qué situación está la BOLSA MADRE de un pescador.
 */
enum EstadoAprovechamiento: string
{
    /** Otorgado y todavía sin cobrar. Es el borrador: se edita y se elimina. */
    case Pendiente = 'pendiente';

    /**
     * Los depósitos están cargados y cubren el monto; falta que alguien firme.
     */
    case EnRevision = 'en_revision';

    /** Firmado. Recién acá autoriza a pescar. */
    case Aprobado = 'aprobado';
    case Vencido = 'vencido';
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
            self::EnRevision => 'En revisión',
            self::Aprobado => 'Aprobado',
            self::Vencido => 'Vencido',
            self::Agotado => 'Agotado',
            self::Revocado => 'Revocado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'sky',
            self::EnRevision => 'indigo',
            self::Aprobado => 'emerald',
            self::Vencido => 'slate',
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
     * El borrador se elimina, el presentado se rechaza, el vencido ya no autoriza.
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
     * ¿Se pueden CARGAR depósitos contra él?
     */
    public function permitePagos(): bool
    {
        return $this === self::Pendiente;
    }

    /**
     * ¿Se puede mandar a que alguien lo firme?
     */
    public function permiteEnvio(): bool
    {
        return $this === self::Pendiente;
    }

    /** ¿Se puede aprobar o rechazar? Solo lo que está presentado. */
    public function permiteRevision(): bool
    {
        return $this === self::EnRevision;
    }

    /**
     * ¿Se puede borrar la fila entera?
     */
    public function permiteEliminacion(): bool
    {
        return $this === self::Pendiente;
    }

    /**
     * Pendiente + en revisión: nadie lo firmó todavía.
     *
     * ⚠️ NO ES PERMISO DE ESCRITURA. Para eso están `permiteEdicion()`,
     * `permiteEliminacion()` y `permitePagos()`, que solo dejan PENDIENTE.
     */
    public function estaAbierto(): bool
    {
        return $this === self::Pendiente || $this === self::EnRevision;
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

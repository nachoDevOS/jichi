<?php

namespace App\Enums;

/**
 * En qué situación está una credencial.
 */
enum EstadoCarnet: string
{
    /**
     * Emitido y todavía sin cobrar. Es como NACE toda credencial: el plástico
     * no se entrega hasta que el arancel esté pagado y firmado.
     */
    case Pendiente = 'pendiente';

    /** Los depósitos cubren el arancel; falta que alguien firme. */
    case EnRevision = 'en_revision';

    /** Vale. Recién acá el carnet habilita a trabajar. */
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
            self::Pendiente => 'Pendiente',
            self::EnRevision => 'En revisión',
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
            self::Pendiente => 'sky',
            self::EnRevision => 'indigo',
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
     *  EL CIRCUITO, igual que el del aprovechamiento
     *
     * Los cuatro métodos que siguen son los que deciden qué se puede hacer en
     * cada estado. Viven acá y NO en el controlador: el servicio pregunta, y
     * React recibe la respuesta ya resuelta en los campos `puede_*`.
     */

    /**
     * ¿Se pueden corregir sus datos?
     *
     * PENDIENTE es un BORRADOR: mientras no entró plata ni nadie lo firmó, el
     * expediente se está armando en el mostrador y equivocarse de asociación o
     * de tipo se arregla corrigiendo la fila.
     */
    public function permiteEdicion(): bool
    {
        return $this === self::Pendiente;
    }

    /** ¿Se puede borrar la fila entera? Mismo criterio que corregir. */
    public function permiteEliminacion(): bool
    {
        return $this === self::Pendiente;
    }

    /** ¿Se le pueden cargar depósitos? Solo mientras nadie lo firmó. */
    public function permitePagos(): bool
    {
        return $this === self::Pendiente;
    }

    /** ¿Se puede mandar a que alguien lo firme? */
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
     * Pendiente + en revisión: nadie lo firmó todavía.
     *
     * ⚠️ NO ES PERMISO DE ESCRITURA ni de trabajo. Para lo segundo está
     * `habilita()`, que solo deja pasar ACTIVO.
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

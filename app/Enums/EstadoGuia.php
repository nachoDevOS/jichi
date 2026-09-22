<?php

namespace App\Enums;

/**
 * En qué situación está una guía de movimiento — el amparo de UN traslado.
 *
 * Mismo circuito que el carnet, el cupo y la faena: pendiente → en revisión →
 * activa. El operador aprende uno solo.
 */
enum EstadoGuia: string
{
    /** Cargada y sin cobrar. Es como NACE toda guía: todavía no ampara nada. */
    case Pendiente = 'pendiente';

    /** Los depósitos cubren el arancel; falta que alguien firme. */
    case EnRevision = 'en_revision';

    /** Firmada y vigente: la carga está en camino. */
    case Activa = 'activa';

    /** Llegó a destino y se descargó. */
    case Cerrada = 'cerrada';

    /** Dada de baja con motivo. No vuelve atrás: si hace falta, se emite otra. */
    case Anulada = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnRevision => 'En revisión',
            self::Activa => 'Activa',
            self::Cerrada => 'Cerrada',
            self::Anulada => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'sky',
            self::EnRevision => 'indigo',
            self::Activa => 'emerald',
            self::Cerrada => 'teal',
            self::Anulada => 'rose',
        };
    }

    /** ¿Ampara un traslado en curso? Solo la firmada. */
    public function habilita(): bool
    {
        return $this === self::Activa;
    }

    /**
     *  EL CIRCUITO, el mismo de la faena
     *
     * Estos métodos deciden qué se puede hacer en cada estado. Viven acá y NO
     * en el controlador: el servicio pregunta, y React recibe la respuesta ya
     * resuelta en los campos `puede_*`.
     */

    /**
     * ¿Se pueden corregir sus datos? Solo el BORRADOR.
     *
     * Al enviarla a revisión sale el recibo y el comerciante se va con el
     * papel, así que desde ahí lo que no sirve se rechaza, no se edita.
     */
    public function permiteEdicion(): bool
    {
        return $this === self::Pendiente;
    }

    /** ¿Se puede borrar la fila? Mismo criterio que la edición. */
    public function permiteEliminacion(): bool
    {
        return $this === self::Pendiente;
    }

    /** ¿Se puede mandar a que alguien la firme? */
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
     * ¿Se le pueden seguir cargando abonos? Solo mientras nadie la firmó.
     *
     * Sobre una firmada no, aunque quede saldo: lo que se deba se resuelve por
     * caja, no cargando plata a un papel ya entregado.
     */
    public function admitePagos(): bool
    {
        return $this === self::Pendiente;
    }

    /**
     * Pendiente + en revisión: nadie la firmó todavía.
     *
     * ⚠️ NO ES PERMISO DE TRASLADO —para eso está `habilita()`— ni de
     * escritura: eso lo dicen `permiteEdicion()` y `permiteEliminacion()`.
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

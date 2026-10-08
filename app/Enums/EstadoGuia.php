<?php

namespace App\Enums;

/**
 * En qué situación está una guía de movimiento — el amparo de UN traslado.
 *
 * Mismo circuito que el carnet, el cupo y la faena: pendiente → (pago en SIREB)
 * → aprobada. El operador aprende uno solo.
 */
enum EstadoGuia: string
{
    /** Cargada y esperando el pago en SIREB. Es como NACE toda guía: todavía no ampara nada. */
    case Pendiente = 'pendiente';

    /** Pagada y vigente: la carga está en camino. */
    case Aprobada = 'aprobado';

    /** Dada de baja con motivo, igual que el carnet y la autorización. No vuelve atrás: si hace falta, se emite otra. */
    case Revocada = 'revocado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Aprobada => 'Aprobada',
            self::Revocada => 'Revocada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'sky',
            self::Aprobada => 'emerald',
            self::Revocada => 'rose',
        };
    }

    /** ¿Ampara un traslado en curso? Solo la firmada. */
    public function habilita(): bool
    {
        return $this === self::Aprobada;
    }

    /**
     *  El circuito, el mismo de la faena
     *
     * Estos métodos deciden qué se puede hacer en cada estado. Viven acá y NO
     * en el controlador: el servicio pregunta, y React recibe la respuesta ya
     * resuelta en los campos `puede_*`.
     */

    /**
     * ¿Se pueden corregir sus datos? Solo el BORRADOR.
     *
     * Corregirla anula la liquidación en SIREB y registra otra.
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

    /**
     * ¿Se revoca? Solo la APROBADA, cuyo papel está en la calle: el borrador se
     * elimina.
     */
    public function permiteRevocacion(): bool
    {
        return $this === self::Aprobada;
    }

    /** Esperando el pago en SIREB. */
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

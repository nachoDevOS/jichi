<?php

namespace App\Enums;

/**
 * En qué situación está un permiso de faena — la autorización de UNA salida.
 */
enum EstadoFaena: string
{
    /** Cargada y esperando el pago en SIREB. Es como NACE toda faena: todavía no autoriza nada. */
    case Pendiente = 'pendiente';

    /** Pagada y en curso: el pescador está afuera. */
    case Aprobado = 'aprobado';

    /** Dada de baja por la unidad estando aprobada, con motivo. Ya no autoriza la salida, pero sus kilos siguen descontados. */
    case Revocado = 'revocado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Aprobado => 'Aprobado',
            self::Revocado => 'Revocado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'sky',
            self::Aprobado => 'emerald',
            self::Revocado => 'rose',
        };
    }

    /** ¿Autoriza a estar pescando hoy? Solo la aprobada. */
    public function habilita(): bool
    {
        return $this === self::Aprobado;
    }

    /**
     *  El circuito, el mismo del carnet y del aprovechamiento
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

    /** ¿Se revoca? Solo la APROBADA: el borrador se elimina y la completada ya cerró su salida. */
    public function permiteRevocacion(): bool
    {
        return $this === self::Aprobado;
    }

    /** Esperando el pago en SIREB. No es permiso de trabajo: para eso, `habilita()`. */
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

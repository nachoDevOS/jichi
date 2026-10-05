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

    /** Volvió y descargó. Los kilos quedaron firmes contra el cupo. */
    case Completado = 'completado';

    /**
     * Cortada por la unidad al revocar su Autorización de Pesca para Aprovechamiento
     * Pesquero, estando aprobada y en fecha. Ya no autoriza la salida.
     */
    case Revocado = 'revocado';

    /** Venció el plazo de pago en SIREB sin ningún pago: no sigue su curso y libera los kilos reservados. NO es la vigencia, que la dicen las fechas. */
    case NoPagado = 'no_pagado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Aprobado => 'Aprobado',
            self::Completado => 'Completado',
            self::Revocado => 'Revocado',
            self::NoPagado => 'No pagado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'sky',
            self::Aprobado => 'emerald',
            self::Completado => 'teal',
            self::Revocado => 'rose',
            self::NoPagado => 'slate',
        };
    }

    /**
     * ¿Sus kilos pesan contra el cupo de la bolsa madre?
     *
     * Solo desde la aprobación (19/09/2026, a pedido del responsable). Una faena
     * pendiente es una SOLICITUD: todavía no autoriza a pescar, así que no puede
     * estar restándole kilos a la bolsa.
     *
     * En modo estricto la solicitud no descuenta pero RESERVA —ver `reservaCupo()`—,
     * así que el cupo no se sobrecompromete. `RevisarFaenaService::aprobar()`
     * vuelve a medir igual, por si la faena nació en modo flexible.
     */
    public function consumeCupo(): bool
    {
        return $this === self::Aprobado || $this === self::Completado;
    }

    /**
     * ¿APARTA sus kilos sin descontarlos? La solicitud abierta: en modo estricto
     * nadie más puede usarlos hasta que se elimine. Ver REGLAS-NEGOCIO.md.
     */
    public function reservaCupo(): bool
    {
        return $this === self::Pendiente;
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

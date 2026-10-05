<?php

namespace App\Enums;

/**
 * En qué situación está una credencial.
 */
enum EstadoCarnet: string
{
    /**
     * Emitido y esperando el pago en SIREB. Es como NACE toda credencial: el
     * plástico no se entrega hasta que el arancel esté pagado.
     */
    case Pendiente = 'pendiente';

    /** SIREB confirmó el pago: recién acá el carnet habilita a trabajar. */
    case Aprobado = 'aprobado';

    /**
     * Dado de baja por decisión de la unidad, antes de su vencimiento.
     */
    case Revocado = 'revocado';

    /** Venció el plazo de pago en SIREB sin ningún pago: no sigue su curso. NO es la vigencia, que la dicen las fechas. */
    case NoPagado = 'no_pagado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Aprobado => 'Aprobado',
            self::Revocado => 'Revocado',
            self::NoPagado => 'No pagado',
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
            self::Aprobado => 'emerald',
            self::Revocado => 'rose',
            self::NoPagado => 'slate',
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
        return $this === self::Aprobado;
    }

    /**
     *  El circuito, igual que el del aprovechamiento
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

    /**
     * ¿Se puede revocar? Solo el APROBADO: el pendiente se elimina, y el no pagado
     * o revocado ya no habilita nada.
     */
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

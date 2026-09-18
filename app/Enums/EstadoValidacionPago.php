<?php

namespace App\Enums;

/**
 * En qué punto está el control de UN depósito.
 *
 * ----------------------------------------------------------------------------
 *  QUÉ SE CONTROLA, EXACTAMENTE
 * ----------------------------------------------------------------------------
 *
 * Que la boleta escaneada diga lo mismo que la fila: el número de transacción,
 * el monto y la fecha, contra el extracto del banco. Es la única forma de saber
 * que el dinero entró de verdad — la fila la tipea una persona y el archivo
 * adjunto puede ser cualquier cosa.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ NACE PENDIENTE Y NO VALIDADO
 * ----------------------------------------------------------------------------
 *
 * Porque si arrancara en «validado», el control no existiría: todo estaría
 * aprobado por omisión y solo se marcaría lo malo, que es justamente lo que
 * nadie se acuerda de hacer. Un depósito recién cargado NO está revisado, y el
 * sistema tiene que decirlo.
 *
 * ----------------------------------------------------------------------------
 *  TRES ESTADOS Y NO DOS
 * ----------------------------------------------------------------------------
 *
 * «Sin validar» y «no cuadra» parecen lo mismo y no lo son. El primero es
 * trabajo que falta hacer; el segundo es un problema encontrado, con su motivo
 * escrito. Con dos estados, un depósito observado se vería igual que uno que
 * nadie miró todavía, y ventanilla no sabría cuál tiene que ir a corregir.
 */
enum EstadoValidacionPago: string
{
    /** Cargado, nadie lo revisó todavía. */
    case Pendiente = 'pendiente';

    /** La boleta cuadra con el extracto. */
    case Validado = 'validado';

    /**
     * No cuadra, y el motivo está escrito.
     *
     * NO es definitivo: ventanilla corrige el dato o vuelve a subir la boleta, y
     * quien revisa lo valida. Se distingue de un rechazo de trámite en que acá
     * el problema es de UN depósito y se puede arreglar sin voltear el
     * expediente entero.
     */
    case Observado = 'observado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Sin validar',
            self::Validado => 'Validado',
            self::Observado => 'Observado',
        };
    }

    /**
     * Nombre del color del badge.
     *
     * Devuelve el NOMBRE y no las clases armadas con texto: Tailwind solo
     * incluye en el CSS final las que puede leer literalmente. Un color nuevo
     * acá tiene que agregarse también al mapa de
     * `resources/js/components/ui/badge.tsx`.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'amber',
            self::Validado => 'emerald',
            self::Observado => 'rose',
        };
    }

    /**
     * ¿Este depósito cuenta como dinero controlado?
     *
     * Es la pregunta que hace `Tramite::puedeAprobarse()`: basta con que UNO de
     * los depósitos no la conteste que sí para que el expediente no se pueda
     * firmar.
     */
    public function cuenta(): bool
    {
        return $this === self::Validado;
    }

    /**
     * ¿Sigue habiendo trabajo pendiente sobre este depósito?
     *
     * Agrupa PENDIENTE y OBSERVADO, que es lo que el tablero tiene que contar
     * como cola: los dos frenan una aprobación, aunque por motivos distintos.
     */
    public function estaAbierto(): bool
    {
        return $this !== self::Validado;
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $estado): array => [
            'value' => $estado->value,
            'label' => $estado->etiqueta(),
            'color' => $estado->color(),
        ], self::cases());
    }
}

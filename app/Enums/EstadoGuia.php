<?php

namespace App\Enums;

/**
 * En qué situación está una guía de movimiento — el amparo de UN traslado.
 */
enum EstadoGuia: string
{
    /** Vigente: la carga está en camino. */
    case Activa = 'activa';

    /** Llegó a destino y se descargó. */
    case Cerrada = 'cerrada';

    /** Dada de baja con motivo. No vuelve atrás: si hace falta, se emite otra. */
    case Anulada = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Cerrada => 'Cerrada',
            self::Anulada => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Activa => 'sky',
            self::Cerrada => 'emerald',
            self::Anulada => 'rose',
        };
    }

    /** ¿Ampara un traslado en curso? */
    public function habilita(): bool
    {
        return $this === self::Activa;
    }

    /**
     * ¿Se le pueden seguir cargando abonos?
     *
     * Sobre una guía anulada no, aunque quede saldo: lo que se deba se resuelve
     * por caja, no cargando plata a un papel que no vale.
     */
    public function admitePagos(): bool
    {
        return $this !== self::Anulada;
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

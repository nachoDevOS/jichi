<?php

namespace App\Enums;

/**
 * El estado de UN rubro dentro de UN carnet (tabla `carnet_rubro`).
 *
 * No se confunde con EstadoCarnet: la suspensión es por actividad, no por
 * documento. A un pescador se le puede cortar el transporte de producto sin
 * quitarle la pesca artesanal, y el carnet sigue siendo válido para lo demás.
 *
 * Por eso la fila no se borra al suspender. Borrarla dejaría al carnet como si
 * ese rubro nunca se hubiera habilitado, y se perdería el dato de que estuvo
 * autorizado hasta tal fecha —que es justamente lo que un inspector necesita
 * saber cuando revisa una infracción del mes pasado—.
 */
enum EstadoHabilitacion: string
{
    case Habilitado = 'habilitado';
    case Suspendido = 'suspendido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Habilitado => 'Habilitado',
            self::Suspendido => 'Suspendido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Habilitado => 'emerald',
            self::Suspendido => 'rose',
        };
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

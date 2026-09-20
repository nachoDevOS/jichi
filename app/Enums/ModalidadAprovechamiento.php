<?php

namespace App\Enums;

/**
 *  LAS DOS VERTIENTES DEL APROVECHAMIENTO PESQUERO
 */
enum ModalidadAprovechamiento: string
{
    /**
     * ESCALAS GENERALES — el cupo acumulativo y consumible.
     *
     * Rangos progresivos de peso, de 1 kg en adelante. Cada faena descuenta de
     * su volumen, y cuando se acaba el pescador tramita otro cupo.
     */
    case EscalaGeneral = 'escala_general';

    /**
     * ESPECIES ESPECIALES — el cupo de gran porte, con tasación fija.
     */
    case EspecieEspecial = 'especie_especial';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EscalaGeneral => 'Escala general',
            self::EspecieEspecial => 'Especie especial',
        };
    }

    /** Qué dice la pantalla debajo del nombre, para que no haya que adivinar. */
    public function descripcion(): string
    {
        return match ($this) {
            self::EscalaGeneral => 'Tramo de la escala progresiva: a más kilos, más valor.',
            self::EspecieEspecial => 'Cuota específica de la especie, con tasación fija por resolución.',
        };
    }

    /**
     * Nombre del color del badge.
     */
    public function color(): string
    {
        return match ($this) {
            self::EscalaGeneral => 'sky',
            self::EspecieEspecial => 'violet',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $m): array => [
            'value' => $m->value,
            'label' => $m->etiqueta(),
            'color' => $m->color(),
        ], self::cases());
    }
}

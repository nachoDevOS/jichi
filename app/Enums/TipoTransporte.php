<?php

namespace App\Enums;

/**
 * En qué se traslada la carga amparada por una guía.
 *
 * ----------------------------------------------------------------------------
 *  NO ES UN DATO DECORATIVO: DICE QUIÉN CONTROLA Y DÓNDE
 * ----------------------------------------------------------------------------
 *
 * Una carga fluvial la para la naval en el río; una terrestre, un retén en la
 * carretera; una aérea se revisa en la pista. Sin este dato la guía no le dice
 * a nadie dónde tiene que aparecer el papel, y por eso la columna es
 * obligatoria mientras el resto de los datos del transporte son opcionales.
 *
 * ----------------------------------------------------------------------------
 *  TRES VALORES, Y LOS TRES SE USAN
 * ----------------------------------------------------------------------------
 *
 * En el Beni la mayor parte del pescado sale por río, pero la carga con destino
 * a La Paz o Santa Cruz va por carretera, y la de mayor valor —fresco que tiene
 * que llegar en el día— viaja en avioneta desde las pistas del interior.
 */
enum TipoTransporte: string
{
    case Fluvial = 'fluvial';
    case Aerea = 'aerea';
    case Terrestre = 'terrestre';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Fluvial => 'Fluvial',
            self::Aerea => 'Aérea',
            self::Terrestre => 'Terrestre',
        };
    }

    /**
     * Nombre del color del badge. Ver la nota de EstadoPermiso::color() sobre
     * por qué se devuelve el nombre y no las clases.
     */
    public function color(): string
    {
        return match ($this) {
            self::Fluvial => 'sky',
            self::Aerea => 'violet',
            self::Terrestre => 'amber',
        };
    }

    /**
     * Cómo se llama en el formulario lo que va en `transporte_placa`.
     *
     * Un camión tiene placa; una embarcación y una aeronave, matrícula. Es la
     * misma columna —no hay motivo para partirla— pero el rótulo cambia, y en
     * el mostrador pedir «la placa» de una canoa confunde.
     */
    public function rotuloIdentificacion(): string
    {
        return match ($this) {
            self::Fluvial, self::Aerea => 'Matrícula',
            self::Terrestre => 'Placa',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $tipo): array => [
            'value' => $tipo->value,
            'label' => $tipo->etiqueta(),
            'color' => $tipo->color(),
        ], self::cases());
    }
}

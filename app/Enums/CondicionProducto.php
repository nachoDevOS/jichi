<?php

namespace App\Enums;

/**
 * Cómo viaja el producto declarado en una fila del detalle de la guía.
 *
 * ----------------------------------------------------------------------------
 *  CAMBIA EL CONTROL Y CAMBIA EL VALOR
 * ----------------------------------------------------------------------------
 *
 * No es lo mismo trasladar cien kilos frescos que cien kilos secos: el fresco
 * exige cadena de frío y se cotiza distinto, y el seco pesa una fracción de lo
 * que pesaba vivo. Por eso la condición va por FILA y no por guía: un mismo
 * viaje lleva pescado fresco en hielo y charque en bolsas.
 *
 * ----------------------------------------------------------------------------
 *  ES UNA LISTA CERRADA, A DIFERENCIA DE LA ESPECIE
 * ----------------------------------------------------------------------------
 *
 * La especie vecina se guarda como texto libre porque el padrón de especies del
 * Beni no existe escrito y una lista incompleta impediría emitir la guía. Con
 * la condición no pasa: son cuatro, las usa el formulario de papel, y no
 * aparecen nuevas. Dejarla libre solo traería «fresca», «FRESCO» y «freco»
 * conviviendo en la columna por la que después se filtra.
 */
enum CondicionProducto: string
{
    case Fresco = 'fresco';
    case Congelado = 'congelado';
    case Seco = 'seco';
    case Salado = 'salado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Fresco => 'Fresco',
            self::Congelado => 'Congelado',
            self::Seco => 'Seco',
            self::Salado => 'Salado',
        };
    }

    /**
     * Nombre del color del badge. Ver la nota de EstadoPermiso::color() sobre
     * por qué se devuelve el nombre y no las clases.
     */
    public function color(): string
    {
        return match ($this) {
            self::Fresco => 'emerald',
            self::Congelado => 'sky',
            self::Seco => 'amber',
            self::Salado => 'slate',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $condicion): array => [
            'value' => $condicion->value,
            'label' => $condicion->etiqueta(),
            'color' => $condicion->color(),
        ], self::cases());
    }
}

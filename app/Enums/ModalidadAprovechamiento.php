<?php

namespace App\Enums;

/**
 * ============================================================================
 *  LAS DOS VERTIENTES DEL APROVECHAMIENTO PESQUERO
 * ============================================================================
 *
 * No son dos tablas ni dos flujos: son la MISMA bolsa madre con dos reglas de
 * recarga distintas. Lo que cambia entre una y otra es qué se puede hacer
 * cuando el cupo se agota.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ES UN ENUM Y NO UNA COLUMNA BOOLEANA `es_paiche`
 * ----------------------------------------------------------------------------
 *
 * Porque el criterio no es la especie sino el RÉGIMEN. Hoy la única especie
 * especial es el paiche; mañana la resolución puede sumar otra, y con un
 * booleano llamado por la especie habría que renombrar la columna —o peor,
 * dejarla mintiendo—. Con el enum, agregar una especie es marcar su tramo de la
 * escala con la modalidad que ya existe.
 *
 * ----------------------------------------------------------------------------
 *  Y POR QUÉ VIVE EN EL TRAMO DE LA ESCALA
 * ----------------------------------------------------------------------------
 *
 * La modalidad la fija la resolución al definir el tramo —«1001 kg Hasta 2000 Kg
 * PAICHE» ES el régimen especial—, no el operador al otorgar. Puesta en el
 * formulario de otorgamiento sería una decisión de ventanilla, y entonces dos
 * cupos del mismo tramo podrían tener reglas distintas.
 *
 * Se COPIA al aprovechamiento al otorgarlo, por lo mismo que el volumen: si
 * alguien reclasifica el tramo en el catálogo, los cupos ya otorgados no pueden
 * cambiar de régimen retroactivamente.
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
     *
     * Paiche y lo que la resolución sume después. Es una autorización específica
     * sobre la cuota de la especie, con TASACIÓN FIJA: el valor no sale de una
     * progresión por kilos como en los tramos menores, lo fija la resolución
     * para esa especie.
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
     *
     * Devuelve el NOMBRE y no las clases armadas con texto: Tailwind solo
     * incluye en el CSS final las que puede leer literalmente. Un color nuevo
     * acá va también al mapa de resources/js/components/ui/badge.tsx.
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

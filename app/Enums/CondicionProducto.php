<?php

namespace App\Enums;

/**
 * Cómo viaja cada especie — las DIEZ columnas de tilde del cuadro D del papel.
 *
 * Un solo enum y no dos columnas (estado × presentación) porque el talonario
 * tampoco las cruza: «Seco» y «Vivos» no se subdividen, y un par de columnas
 * dejaría combinaciones que en el papel no existen.
 */
enum CondicionProducto: string
{
    case FrescoEntero = 'fresco_entero';
    case FrescoEviscerado = 'fresco_eviscerado';
    case CongeladoEntero = 'congelado_entero';
    case CongeladoEviscerado = 'congelado_eviscerado';
    case CongeladoFileteado = 'congelado_fileteado';
    case Seco = 'seco';
    case SalPreso = 'sal_preso';
    case Vivos = 'vivos';
    case AGranel = 'a_granel';
    case Otros = 'otros';

    /** Como se lee en la pantalla: el grupo y la presentación juntos. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::FrescoEntero => 'Fresco/refrigerado — entero',
            self::FrescoEviscerado => 'Fresco/refrigerado — eviscerado',
            self::CongeladoEntero => 'Congelado — entero',
            self::CongeladoEviscerado => 'Congelado — eviscerado',
            self::CongeladoFileteado => 'Congelado — fileteado',
            self::Seco => 'Seco',
            self::SalPreso => 'Sal preso',
            self::Vivos => 'Vivos',
            self::AGranel => 'A granel',
            self::Otros => 'Otros',
        };
    }

    /** El encabezado de grupo del cuadro. Vacío en las columnas sueltas. */
    public function grupo(): string
    {
        return match ($this) {
            self::FrescoEntero, self::FrescoEviscerado => 'Fresco o Refrigerado',
            self::CongeladoEntero, self::CongeladoEviscerado, self::CongeladoFileteado => 'Congelado',
            default => '',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, grupo: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $e): array => [
            'value' => $e->value,
            'label' => $e->etiqueta(),
            'grupo' => $e->grupo(),
        ], self::cases());
    }
}

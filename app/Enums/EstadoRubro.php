<?php

namespace App\Enums;

/**
 * Si un rubro se puede solicitar hoy.
 *
 * Un rubro se da de baja cambiando el estado, nunca borrando la fila: los
 * carnets y trámites históricos apuntan a él y no pueden quedar huérfanos.
 *
 * La diferencia práctica: un rubro `Inactivo` desaparece del formulario de
 * solicitud —no se puede pedir—, pero los carnets que ya lo tenían habilitado
 * lo siguen mostrando en su reverso. Es lo correcto: la habilitación se otorgó
 * cuando el rubro existía.
 */
enum EstadoRubro: string
{
    case Activo = 'activo';
    case Inactivo = 'inactivo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Inactivo => 'Inactivo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Activo => 'emerald',
            self::Inactivo => 'slate',
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

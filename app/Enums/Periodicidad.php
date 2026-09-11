<?php

namespace App\Enums;

enum Periodicidad: string
{
    case Unico = 'unico';
    case Mensual = 'mensual';
    case Anual = 'anual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Unico => 'Pago único',
            self::Mensual => 'Mensual',
            self::Anual => 'Anual',
        };
    }

    /**
     * Días de vigencia por defecto cuando el tipo de trámite no define los suyos.
     */
    public function diasVigencia(): ?int
    {
        return match ($this) {
            self::Unico => null,
            self::Mensual => 30,
            self::Anual => 365,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $p) => [
            'value' => $p->value,
            'label' => $p->etiqueta(),
        ], self::cases());
    }
}

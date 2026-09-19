<?php

namespace App\Enums;

/**
 * Por dónde entró el dinero de un abono.
 *
 * Existe para que la forma de pago no se escriba como texto suelto en la vista
 * ni en el reporte de caja: si mañana la unidad habilita otro canal, se agrega
 * un `case` acá y las pantallas ya lo saben dibujar.
 *
 * La columna `pagos.metodo_pago` es un `string`, no un ENUM nativo de
 * PostgreSQL: así sumar un canal no exige un ALTER TYPE que bloquea la tabla.
 */
enum MetodoPago: string
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Qr = 'qr';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Transferencia => 'Transferencia',
            self::Qr => 'QR',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Efectivo => 'emerald',
            self::Transferencia => 'sky',
            self::Qr => 'violet',
        };
    }

    /**
     * ¿Este canal deja un número de comprobante que anotar?
     *
     * Con efectivo no hay ninguno: el respaldo es el propio recibo de caja. Con
     * transferencia o QR sí, y es el dato con el que después se cuadra contra
     * el extracto del banco.
     */
    public function llevaComprobante(): bool
    {
        return $this !== self::Efectivo;
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

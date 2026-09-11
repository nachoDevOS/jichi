<?php

namespace App\Enums;

enum FormaPago: string
{
    case Efectivo = 'efectivo';
    case Qr = 'qr';
    case Transferencia = 'transferencia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Qr => 'Pago QR',
            self::Transferencia => 'Transferencia bancaria',
        };
    }

    /**
     * QR y transferencia exigen número de transacción: es el único rastro
     * que permite cruzar el pago con el extracto del banco. El efectivo no
     * lo necesita porque se cuenta en el momento, contra el comprobante.
     */
    public function requiereReferencia(): bool
    {
        return $this !== self::Efectivo;
    }

    /**
     * Las formas de pago que HOY se pueden registrar en ventanilla.
     *
     * Por ahora es solo la transferencia bancaria. El efectivo y el QR no
     * están habilitados todavía: falta definir cómo se rinde la caja del día y
     * quién concilia el QR, y aceptarlos antes de eso dejaría pagos
     * registrados que después nadie puede cruzar con nada.
     *
     * El enum conserva los tres casos a propósito. `disponibles()` es lo que
     * se puede ELEGIR; los casos son lo que el sistema sabe LEER, y un pago
     * viejo o un reporte tienen que poder seguir leyendo 'efectivo' aunque hoy
     * no se pueda elegir. Habilitarlos de nuevo es sumarlos a esta lista y
     * nada más.
     *
     * @return array<int, self>
     */
    public static function disponibles(): array
    {
        return [self::Transferencia];
    }

    /**
     * Lo mismo, servido a la pantalla.
     *
     * Sale de disponibles() y no de cases() para que la lista del formulario y
     * la que valida el servidor sean la misma: si fueran dos, alcanzaría con
     * habilitar una y olvidarse de la otra para que el operador vea una opción
     * que al guardar da error.
     *
     * @return array<int, array{value: string, label: string, requiere_referencia: bool}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $forma) => [
            'value' => $forma->value,
            'label' => $forma->etiqueta(),
            'requiere_referencia' => $forma->requiereReferencia(),
        ], self::disponibles());
    }
}

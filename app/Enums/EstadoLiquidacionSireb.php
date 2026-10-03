<?php

namespace App\Enums;

/**
 * Dónde está una venta respecto de SIREB. No es el pago: el cobro sigue en Jichi.
 */
enum EstadoLiquidacionSireb: string
{
    /** Guardada con su clave, sin respuesta de SIREB todavía. Se reintenta con la misma clave. */
    case PorEnviar = 'por_enviar';

    /** SIREB la registró: tiene id y código público. */
    case Registrada = 'registrada';

    /** Anulada en SIREB (se corrigió o eliminó el borrador). */
    case Anulada = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PorEnviar => 'Sin registrar en Recaudaciones',
            self::Registrada => 'Registrada en Recaudaciones',
            self::Anulada => 'Anulada en Recaudaciones',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PorEnviar => 'amber',
            self::Registrada => 'emerald',
            self::Anulada => 'slate',
        };
    }
}

<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una regla de las cuentas del portal del beneficiario dijo que no.
 */
class CuentaPortalException extends RuntimeException
{
    public static function yaTieneCuenta(string $persona): self
    {
        return new self(
            "{$persona} ya tiene acceso al portal. Si olvidó la contraseña, use «Resetear contraseña».",
        );
    }

    public static function sinCuenta(string $persona): self
    {
        return new self("{$persona} todavía no tiene acceso al portal: primero hay que darle acceso.");
    }
}

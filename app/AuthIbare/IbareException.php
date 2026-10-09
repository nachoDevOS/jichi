<?php

namespace App\AuthIbare;

use RuntimeException;

/**
 * El login con Ibare no se pudo completar. El mensaje es el que ve el funcionario.
 */
class IbareException extends RuntimeException
{
    public static function noResponde(): self
    {
        return new self('El sistema no responde en este momento. Intente de nuevo en unos minutos.');
    }

    // El `state` no coincide o se perdió la sesión: la vuelta no es la de este navegador.
    public static function solicitudVencida(): self
    {
        return new self('Tardó demasiado. Vuelva a presionar el botón para ingresar.');
    }

    public static function rechazado(): self
    {
        return new self('No se completó el ingreso. Vuelva a intentar.');
    }

    public static function tokenInvalido(): self
    {
        return new self('Algo salió mal. Vuelva a intentar en unos minutos.');
    }

    // Existe en Ibare pero nadie le dio cuenta en Jichi. El id va al log, no a la pantalla.
    public static function sinCuenta(): self
    {
        return new self('Todavía no tiene acceso a este sistema. Pida al encargado que lo habilite.');
    }
}

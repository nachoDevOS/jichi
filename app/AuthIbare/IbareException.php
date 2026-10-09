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
        return new self('El servicio de ingreso de la Gobernación no responde en este momento. Espere unos minutos y vuelva a intentar. Si sigue igual, llame a la Unidad de Sistemas.');
    }

    // El `state` no coincide o se perdió la sesión: la vuelta no es la de este navegador.
    public static function solicitudVencida(): self
    {
        return new self('Pasó demasiado tiempo y el ingreso se canceló. Presione de nuevo el botón para ingresar.');
    }

    public static function rechazado(): self
    {
        return new self('No se completó el ingreso. Si lo canceló sin querer, presione de nuevo el botón para ingresar.');
    }

    public static function tokenInvalido(): self
    {
        return new self('No pudimos confirmar su identidad. Vuelva a intentar; si sigue pasando, llame a la Unidad de Sistemas.');
    }

    // El funcionario existe en Ibare pero nadie le dio cuenta en Jichi: los roles los asigna un administrador.
    public static function sinCuenta(string $mamoreId): self
    {
        return new self("Su usuario de la Gobernación es correcto, pero todavía no está habilitado en este sistema. Pida al encargado de Jichi que le dé acceso y dígale su número de funcionario: {$mamoreId}.");
    }
}

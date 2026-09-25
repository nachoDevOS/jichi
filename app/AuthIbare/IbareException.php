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
        return new self('Ibare no responde. Intente de nuevo en unos minutos o avise a la Unidad de Sistemas.');
    }

    // El `state` no coincide o se perdió la sesión: la vuelta no es la de este navegador.
    public static function solicitudVencida(): self
    {
        return new self('La solicitud de ingreso venció. Vuelva a presionar «Ingresar con Ibare».');
    }

    public static function rechazado(): self
    {
        return new self('Ibare no autorizó el ingreso.');
    }

    public static function tokenInvalido(): self
    {
        return new self('La respuesta de Ibare no es válida. Avise a la Unidad de Sistemas.');
    }

    // El funcionario existe en Ibare pero nadie le dio cuenta en Jichi: los roles los asigna un administrador.
    public static function sinCuenta(string $mamoreId): self
    {
        return new self("Su cuenta de funcionario (id {$mamoreId}) no tiene acceso a Jichi. Pida el alta a un administrador.");
    }
}

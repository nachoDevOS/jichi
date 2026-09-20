<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una regla de caja dijo que no.
 */
class CobroInvalidoException extends RuntimeException
{
    /** Un recibo sin líneas no es un recibo. */
    public static function sinLineas(): self
    {
        return new self('Elija al menos un trámite para cobrar.');
    }

    /**
     *  NO SE COBRA MÁS DE LO QUE SE DEBE
     */
    public static function excedeElSaldo(string $tramite, float $monto, float $saldo): self
    {
        return new self(sprintf(
            'Para %s se quiere cobrar %s Bs y solo se deben %s Bs. Pagar de más no genera saldo a '.
            'favor: el excedente se perdería.',
            $tramite,
            number_format($monto, 2, ',', '.'),
            number_format($saldo, 2, ',', '.'),
        ));
    }

    /**
     *  TAMPOCO SE COBRA DE MENOS: los depósitos entran todos juntos
     */
    public static function noCubreElMonto(string $tramite, float $suma, float $saldo): self
    {
        return new self(sprintf(
            'Los depósitos de %s suman %s Bs y se deben %s Bs. Cargue las boletas que falten hasta '.
            'cubrir el monto: el trámite se registra cobrado entero, no en cuotas.',
            $tramite,
            number_format($suma, 2, ',', '.'),
            number_format($saldo, 2, ',', '.'),
        ));
    }

    /** Un recibo es de UNA persona: no se cobran trámites de dos a la vez. */
    public static function variosTitulares(): self
    {
        return new self(
            'Los trámites elegidos son de personas distintas. Un recibo sale a nombre de una sola: '.
            'cobre por separado.',
        );
    }

    /** Ya no se debe nada de ese trámite. */
    public static function yaEstaPagado(string $tramite): self
    {
        return new self("{$tramite} ya está cubierto: no hay nada que cobrar.");
    }

    /**
     * Sobre un papel anulado no se cobra, aunque quede saldo.
     *
     * Lo que se deba se resuelve por caja, no cargándole plata a una guía que
     * no ampara ningún traslado. Ver EstadoGuia::admitePagos().
     */
    public static function noAdmitePagos(string $tramite): self
    {
        /*
         * La frase se arma con el sujeto ENTRECOMILLADO y el adjetivo referido
         * al «papel», no al trámite: así no hay que resolver el género de un
         * nombre que puede ser «Carnet», «Guía» o «Aprovechamiento». Es la misma
         * corrección que hubo que hacer en PermisoOperativoException.
         */
        return new self("«{$tramite}» no admite cobros: el papel está anulado.");
    }

    /**
     * El TRÁMITE no está en un estado que acepte depósitos.
     */
    public static function noAdmiteDepositos(string $tramite, string $estado): self
    {
        return new self(
            "«{$tramite}» no admite depósitos: está en «{$estado}». ".
            'Solo se cargan mientras el trámite está pendiente.',
        );
    }

    /**
     * No se puede validar ni observar este depósito.
     */
    public static function noAdmiteControl(string $motivo): self
    {
        return new self($motivo);
    }

    /** Un depósito de un expediente ya firmado no se toca. */
    public static function noAdmiteCorreccion(string $tramite): self
    {
        return new self(
            "«{$tramite}» ya no admite correcciones en sus depósitos: el expediente está firmado. ".
            'Lo que haya que arreglar se resuelve por caja.',
        );
    }

    /** El formulario mandó un tipo de trámite que no se cobra. */
    public static function tipoDesconocido(string $tipo): self
    {
        return new self("«{$tipo}» no es un trámite cobrable.");
    }
}

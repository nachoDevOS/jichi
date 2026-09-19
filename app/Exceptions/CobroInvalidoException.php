<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una regla de caja dijo que no.
 *
 * Mismo criterio que las otras: es una excepción y no un `return false` porque
 * el cobro entero —el recibo y todos sus abonos— corre dentro de una
 * transacción. Con la excepción, `DB::transaction()` deshace todo solo; con un
 * booleano quedaría un recibo emitido sin la mitad de sus pagos, y su número ya
 * gastado.
 */
class CobroInvalidoException extends RuntimeException
{
    /** Un recibo sin líneas no es un recibo. */
    public static function sinLineas(): self
    {
        return new self('Elija al menos un trámite para cobrar.');
    }

    /**
     * ========================================================================
     *  NO SE COBRA MÁS DE LO QUE SE DEBE
     * ========================================================================
     *
     * Y es una regla, no una comodidad. `Pagable::saldoPendiente()` se corta en
     * cero: pagar de más NO genera saldo a favor, así que el excedente
     * DESAPARECE — queda escrito en `pagos`, suma en la recaudación del día, y
     * no se le acredita a nadie.
     *
     * Si entró dinero de más, no es un abono de este trámite y se resuelve por
     * caja. Por eso acá se rechaza en vez de aceptarlo callado.
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

    /** El formulario mandó un tipo de trámite que no se cobra. */
    public static function tipoDesconocido(string $tipo): self
    {
        return new self("«{$tipo}» no es un trámite cobrable.");
    }
}

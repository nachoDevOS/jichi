<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una regla del otorgamiento de cupo dijo que no.
 */
class CupoInvalidoException extends RuntimeException
{
    /**
     *  UNA PERSONA, UNA BOLSA MADRE VIGENTE A LA VEZ
     */
    public static function yaTieneCupoVigente(string $persona, float $saldo, string $vence): self
    {
        return new self(sprintf(
            '%s ya tiene un aprovechamiento vigente hasta el %s, con %s kg sin usar. '.
            'No se otorga un segundo cupo. Si el que tiene todavía está pendiente de pago, '.
            'corríjalo al tramo que corresponda; si ya se cobró, hay que esperar a que venza.',
            $persona,
            $vence,
            number_format($saldo, 2, ',', '.'),
        ));
    }

    /**
     * Se eligió un tramo de la escala que ya no está vigente.
     */
    public static function escalaDerogada(int $nroEscala): self
    {
        return new self(
            "La escala {$nroEscala} fue derogada y ya no se puede otorgar. ".
            'Vuelva a abrir el formulario para ver los tramos vigentes.',
        );
    }

    /** Se quiso presentar un cupo que ya no está en el borrador. */
    public static function noSePuedeEnviar(string $estado): self
    {
        return new self(sprintf(
            'Este aprovechamiento está %s y no se puede enviar a revisión. '.
            'Solo se presenta lo que está pendiente de pago.',
            mb_strtolower($estado),
        ));
    }

    /**
     * Se quiso presentar o aprobar un cupo con saldo sin cubrir.
     */
    public static function faltaCubrirElMonto(float $saldo): self
    {
        return new self(sprintf(
            'Todavía faltan %s Bs por cobrar. Un aprovechamiento se presenta a revisión '.
            'cuando los depósitos cubren el monto entero.',
            number_format($saldo, 2, ',', '.'),
        ));
    }

    /**
     * Se quiso firmar con boletas sin controlar.
     */
    public static function faltaControlarBoletas(int $cuantas): self
    {
        return new self(sprintf(
            'Quedan %d depósito(s) sin validar. Un aprovechamiento se aprueba cuando cada boleta '.
            'se comparó contra el extracto del banco; un depósito observado se corrige antes de firmar.',
            $cuantas,
        ));
    }

    /** Se quiso aprobar o rechazar algo que no está presentado. */
    public static function noSePuedeRevisar(string $estado): self
    {
        return new self(sprintf(
            'Este aprovechamiento está %s: solo se aprueba o se rechaza lo que está EN REVISIÓN.',
            mb_strtolower($estado),
        ));
    }

    /** Se quiso cargar un depósito contra un cupo que ya no los admite. */
    public static function noAdmitePagos(string $estado): self
    {
        return new self(sprintf(
            'Este aprovechamiento está %s y ya no admite pagos. '.
            'Solo se cobra mientras está pendiente; si hay que corregir algo cobrado, se resuelve por caja.',
            mb_strtolower($estado),
        ));
    }

    /**
     * Se quiso corregir un cupo que ya salió del borrador.
     *
     * El mensaje dice el estado en el que está, porque la salida es distinta en
     * cada caso: uno cobrado se corrige por caja, uno vencido ya no se corrige.
     */
    public static function noSePuedeEditar(string $estado): self
    {
        return new self(sprintf(
            'Este aprovechamiento está %s y ya no se puede editar. '.
            'Solo se corrige mientras está pendiente de pago, antes de que exista un recibo que lo respalde.',
            mb_strtolower($estado),
        ));
    }

    /** Se quiso borrar un cupo que ya salió del borrador. */
    public static function noSePuedeEliminar(string $estado): self
    {
        return new self(sprintf(
            'Este aprovechamiento está %s y ya no se puede eliminar. '.
            'Un cupo con pagos o faenas encima no se borra: se corrige por caja, para no dejar plata colgando de algo que no existe.',
            mb_strtolower($estado),
        ));
    }

    /** Tiene faenas emitidas: borrarlo dejaría permisos sin bolsa madre. */
    public static function tieneFaenas(int $cuantas): self
    {
        return new self(sprintf(
            'No se puede eliminar: ya tiene %d faena(s) emitida(s) colgando. '.
            'Esos permisos salieron de un talonario de papel y no pueden quedar sin el cupo que los respalda.',
            $cuantas,
        ));
    }

    /** Tiene pagos: borrarlo dejaría los abonos huérfanos. */
    public static function tienePagos(int $cuantos): self
    {
        return new self(sprintf(
            'No se puede eliminar: ya tiene %d pago(s) registrado(s). '.
            'Lo cobrado se resuelve por caja, no borrando la fila.',
            $cuantos,
        ));
    }

    /**
     * No se amplía un cupo que ya no corre.
     */
}

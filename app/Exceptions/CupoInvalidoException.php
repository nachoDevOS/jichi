<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una regla del otorgamiento de cupo dijo que no.
 */
class CupoInvalidoException extends RuntimeException
{
    /** No es culpa de un campo del formulario (escala o SIREB): va en el aviso de arriba. */
    public bool $avisoGeneral = false;

    /**
     *  Una persona, una bolsa madre vigente a la vez
     */
    public static function yaTieneCupoVigente(string $persona, float $saldo, string $vence): self
    {
        return new self(sprintf(
            '%s ya tiene un aprovechamiento vigente hasta el %s, con %s kg sin usar. '.
            'No se otorga un segundo cupo. Si el que tiene todavía está pendiente de pago, '.
            'corríjalo al tramo que corresponda; si ya se pagó, hay que esperar a que venza o revocarlo.',
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
        $e = new self(
            "La escala {$nroEscala} fue derogada y ya no se puede otorgar. ".
            'Vuelva a abrir el formulario para ver los tramos vigentes.',
        );
        $e->avisoGeneral = true;

        return $e;
    }

    /** SIREB no dio el precio o no aceptó la venta. Texto para ventanilla: el detalle va al log. */
    public static function sireb(string $tramo, bool $noResponde): self
    {
        $e = new self($noResponde
            ? 'Recaudaciones no responde en este momento. Espere unos minutos y vuelva a intentarlo.'
            : "La escala de la autorización «{$tramo}» no está habilitada para cobrar en este momento. ".
              'Elija otra escala o consulte con el encargado del sistema.');
        $e->avisoGeneral = true;

        return $e;
    }

    /** Se quiso aprobar algo que ya no está pendiente de pago. */
    public static function noSePuedeRevisar(string $estado): self
    {
        return new self(sprintf(
            'Este aprovechamiento está %s: solo se aprueba el que está PENDIENTE de pago.',
            mb_strtolower($estado),
        ));
    }

    /**
     * Se quiso corregir un cupo que ya salió del borrador.
     *
     * El mensaje dice el estado en el que está: la salida es distinta en cada caso.
     */
    public static function noSePuedeEditar(string $estado): self
    {
        return new self(sprintf(
            'Este aprovechamiento está %s y ya no se puede editar. '.
            'Solo se corrige mientras está pendiente de pago.',
            mb_strtolower($estado),
        ));
    }

    /** Se quiso borrar un cupo que ya salió del borrador. */
    public static function noSePuedeEliminar(string $estado): self
    {
        return new self(sprintf(
            'Este aprovechamiento está %s y ya no se puede eliminar. '.
            'Solo se elimina mientras está pendiente de pago y sin faenas.',
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

    /**
     * No se amplía un cupo que ya no corre.
     */

    /** Se quiso revocar sin escribir por qué. */
    public static function motivoObligatorio(): self
    {
        return new self('Escriba el motivo de la revocación.');
    }

    /** Otra ventanilla lo revocó recién. */
    public static function yaRevocado(): self
    {
        return new self('Esta Autorización de Pesca para Aprovechamiento Pesquero ya está revocada.');
    }

    /** Solo se revoca lo aprobado o agotado. */
    public static function noSePuedeRevocar(string $estado): self
    {
        return new self(
            "No se puede revocar una autorización {$estado}: el borrador se elimina.",
        );
    }
}

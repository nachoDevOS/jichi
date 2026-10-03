<?php

namespace App\Exceptions;

use App\Enums\TipoActor;
use RuntimeException;

/**
 * Una regla de emisión de credenciales dijo que no.
 */
class CarnetInvalidoException extends RuntimeException
{
    /**
     *  Una credencial vigente por actividad y por persona
     */
    public static function yaTieneCarnetVigente(string $persona, TipoActor $actor, string $codigo, string $vence): self
    {
        return new self(sprintf(
            '%s ya tiene un carnet de %s vigente hasta el %s (%s). No se emite un segundo de la '.
            'misma actividad: si el carnet se perdió o se estropeó, hay que revocar el actual y '.
            'emitir uno nuevo.',
            $persona,
            mb_strtolower($actor->etiqueta()),
            $vence,
            $codigo,
        ));
    }

    /**
     * Sin precio de SIREB no se emite: ninguna tarifa se escribe a mano.
     */
    public static function sinPrecio(string $tipo, string $motivo): self
    {
        return new self("No se puede emitir un «{$tipo}»: {$motivo}");
    }

    /**
     * Un carnet de pescador sin bolsa madre detrás.
     */
    public static function pescadorSinCupo(string $persona): self
    {
        return new self(
            "{$persona} no tiene un cupo de pesca vigente. El carnet de pescador imprime el volumen ".
            'autorizado, así que hay que otorgarle el aprovechamiento antes de emitirlo.',
        );
    }

    /**
     * El tipo elegido es de la otra actividad.
     */
    public static function tipoNoCorresponde(string $tipo, TipoActor $delTipo, TipoActor $delCarnet): self
    {
        return new self(sprintf(
            'El tipo «%s» es de %s y el carnet se está emitiendo como %s. Elija un tipo de %s.',
            $tipo,
            mb_strtolower($delTipo->etiqueta()),
            mb_strtolower($delCarnet->etiqueta()),
            mb_strtolower($delCarnet->etiqueta()),
        ));
    }

    /**
     * Un comercializador con bolsa madre: no extrae, así que no lleva cupo.
     */
    public static function comercializadorConCupo(): self
    {
        return new self(
            'Un carnet de comercializador no lleva cupo de pesca: la comercialización no se '.
            'autoriza por volumen. Emítalo sin aprovechamiento.',
        );
    }

    /**
     * Se eligió una asociación o un tipo que ya no se puede usar.
     */
    public static function catalogoInactivo(string $que, string $nombre): self
    {
        return new self(
            "{$que} «{$nombre}» ya no está activa y no se puede usar para emitir. ".
            'Vuelva a abrir el formulario para ver las opciones vigentes.',
        );
    }

    /** Revocar sin motivo escrito no deja nada que explicar después. */
    public static function noSePuedeRevisar(string $estado): self
    {
        return new self(
            "El carnet está {$estado} y solo se aprueba el que está PENDIENTE de pago. ".
            'Vuelva a abrir la ficha para ver en qué estado quedó.',
        );
    }

    /**
     *  Corregir y eliminar solo sobre el borrador
     */
    public static function noSePuedeEditar(string $estado): self
    {
        return new self(
            "El carnet está {$estado} y solo se corrige lo que está PENDIENTE.",
        );
    }

    public static function noSePuedeEliminar(string $estado): self
    {
        return new self(
            "El carnet está {$estado} y solo se elimina lo que está PENDIENTE. Una credencial que ".
            'ya se firmó no se borra: se REVOCA, y queda su historia.',
        );
    }

    /** Ya emitió permisos: el papel está afuera. */
    public static function tienePermisos(int $cuantos, string $que): self
    {
        return new self(sprintf(
            'El carnet ya emitió %d %s: no se elimina. Ese papel está en manos de la persona y '.
            'borrar la credencial lo dejaría sin respaldo.',
            $cuantos,
            $que,
        ));
    }

    public static function motivoObligatorio(): self
    {
        return new self('Hay que escribir el motivo de la revocación: es una sanción y tiene que quedar explicada.');
    }

    /**
     * No se revoca dos veces.
     */
    public static function noSePuedeRevocar(string $estado): self
    {
        return new self(
            "El carnet está {$estado}: solo se revoca uno APROBADO. ".
            'Un pendiente se elimina.',
        );
    }

    public static function yaRevocado(): self
    {
        return new self(
            'Este carnet ya está revocado, y la revocación no se revierte. '.
            'Si la persona vuelve a estar en regla, emítale uno nuevo.',
        );
    }

    /** Se quiso aprobar un carnet cuya autorización revocaron mientras esperaba el pago. */
    public static function cupoRevocado(): self
    {
        return new self(
            'La Autorización de Pesca para Aprovechamiento Pesquero de este carnet fue revocada: '.
            'no se puede emitir un carnet con ella. Rechácelo y emítalo sobre una autorización vigente.',
        );
    }
}

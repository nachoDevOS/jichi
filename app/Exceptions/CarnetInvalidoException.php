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
     *  UNA CREDENCIAL VIGENTE POR ACTIVIDAD Y POR PERSONA
     */
    public static function yaTieneCarnetVigente(string $persona, TipoActor $actor, string $codigo, string $vence): self
    {
        return new self(sprintf(
            '%s ya tiene un carnet de %s vigente hasta el %s (%s). No se emite un segundo de la '.
            'misma actividad: si el plástico se perdió o se estropeó, hay que revocar el actual y '.
            'emitir uno nuevo.',
            $persona,
            mb_strtolower($actor->etiqueta()),
            $vence,
            $codigo,
        ));
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
    public static function motivoObligatorio(): self
    {
        return new self('Hay que escribir el motivo de la revocación: es una sanción y tiene que quedar explicada.');
    }

    /**
     * No se revoca dos veces.
     */
    public static function yaRevocado(): self
    {
        return new self(
            'Este carnet ya está revocado, y la revocación no se revierte. '.
            'Si la persona vuelve a estar en regla, emítale uno nuevo.',
        );
    }
}

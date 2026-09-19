<?php

namespace App\Exceptions;

use App\Enums\TipoActor;
use RuntimeException;

/**
 * Una regla de emisión de credenciales dijo que no.
 *
 * Mismo criterio que CupoInvalidoException: es una excepción y no un
 * `return false` porque el servicio trabaja dentro de una transacción, y así
 * `DB::transaction()` deshace todo solo.
 *
 * El mensaje sale tal cual en el aviso rojo de la pantalla, así que va en
 * castellano de mostrador y DICE QUÉ HACER.
 */
class CarnetInvalidoException extends RuntimeException
{
    /**
     * ========================================================================
     *  UNA CREDENCIAL VIGENTE POR ACTIVIDAD Y POR PERSONA
     * ========================================================================
     *
     * La regla es POR ACTIVIDAD, no por persona: quien pesca y además
     * comercializa tiene DOS carnets vigentes al mismo tiempo, y eso es lo
     * normal. Lo que no puede tener son dos de pescador.
     *
     * No la garantiza ningún índice de la base —«vigente» depende de la fecha
     * de hoy— así que la sostiene el servicio con la fila del beneficiario
     * bloqueada.
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
     *
     * El plástico imprime el cupo en kilos, así que emitirlo sin cupo daría una
     * credencial con un renglón vacío en el lugar donde un control espera un
     * número. Y sin cupo tampoco se pueden emitir faenas, que es para lo único
     * que sirve ese carnet.
     *
     * POR ESO EL CUPO ES EL PASO 2 Y EL CARNET EL 3, y no al revés.
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
     *
     * Puede pasar entre que el operador abre el formulario y aprieta guardar:
     * la lista se armó con lo vigente en ese momento y en el medio alguien lo
     * desactivó desde el catálogo.
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
     *
     * Y tampoco se «desrevoca»: la revocación es definitiva. Si la persona
     * vuelve a estar en regla, lo que corresponde es emitirle un carnet nuevo,
     * con su propio código — el plástico viejo puede estar circulando.
     */
    public static function yaRevocado(): self
    {
        return new self(
            'Este carnet ya está revocado, y la revocación no se revierte. '.
            'Si la persona vuelve a estar en regla, emítale uno nuevo.',
        );
    }
}

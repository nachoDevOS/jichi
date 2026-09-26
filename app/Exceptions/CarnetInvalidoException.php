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
            'misma actividad: si el carnet se perdió o se estropeó, hay que revocar el actual y '.
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
    /**
     *  LAS TRES DEL CIRCUITO DE REVISIÓN
     */
    public static function noSePuedeEnviar(string $estado): self
    {
        return new self(
            "El carnet está {$estado} y solo se presenta a revisión lo que está PENDIENTE. ".
            'Vuelva a abrir la ficha para ver en qué estado quedó.',
        );
    }

    public static function noSePuedeRevisar(string $estado): self
    {
        return new self(
            "El carnet está {$estado} y solo se firma o se rechaza lo que está EN REVISIÓN. ".
            'Vuelva a abrir la ficha para ver en qué estado quedó.',
        );
    }

    /** Falta plata: el carnet no se presenta a medio cobrar. */
    public static function faltaCubrirElArancel(float $saldo): self
    {
        return new self(sprintf(
            'Faltan %s Bs por cobrar. El carnet se presenta con el arancel cubierto: cargue los '.
            'depósitos que falten.',
            number_format($saldo, 2, ',', '.'),
        ));
    }

    /** Quedan boletas que nadie controló contra el extracto del banco. */
    public static function faltaControlarBoletas(int $cuantas): self
    {
        return new self(sprintf(
            'Quedan %d depósito(s) sin validar. Se controlan las boletas contra el extracto del '.
            'banco antes de firmar: sin eso, la validación no serviría de nada.',
            $cuantas,
        ));
    }

    /**
     *  CORREGIR Y ELIMINAR SOLO SOBRE EL BORRADOR
     */
    public static function noSePuedeEditar(string $estado): self
    {
        return new self(
            "El carnet está {$estado} y solo se corrige lo que está PENDIENTE y sin cobrar. ".
            'Si hay un error en un carnet ya presentado, hay que rechazarlo para que vuelva a '.
            'ventanilla.',
        );
    }

    public static function noSePuedeEliminar(string $estado): self
    {
        return new self(
            "El carnet está {$estado} y solo se elimina lo que está PENDIENTE. Una credencial que ".
            'ya se firmó no se borra: se REVOCA, y queda su historia.',
        );
    }

    /**
     * Hay plata cargada contra este carnet: ni se corrige ni se elimina.
     *
     * Vale para las dos porque el motivo es el mismo —cambiarle el tipo le
     * cambiaría el arancel por debajo a algo que alguien ya pagó— y la salida
     * también: dar de baja los depósitos por caja.
     */
    public static function tienePagos(int $cuantos): self
    {
        return new self(sprintf(
            'El carnet tiene %d depósito(s) cargados: no se corrige ni se elimina con plata '.
            'cobrada encima. Lo que corresponde es dar de baja los depósitos por caja.',
            $cuantos,
        ));
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
            'Un pendiente se elimina y uno en revisión se rechaza.',
        );
    }

    public static function yaRevocado(): self
    {
        return new self(
            'Este carnet ya está revocado, y la revocación no se revierte. '.
            'Si la persona vuelve a estar en regla, emítale uno nuevo.',
        );
    }
}

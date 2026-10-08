<?php

namespace App\Exceptions;

use App\Enums\EstadoCarnet;
use App\Enums\TipoActor;
use RuntimeException;

/**
 * Una regla de emisión dijo que no.
 */
class PermisoOperativoException extends RuntimeException
{
    /**
     * La credencial no habilita ESTE papel.
     */
    public static function actorNoEmite(string $permiso, TipoActor $actor): self
    {
        // Lo que SÍ emite esa credencial. Estaba al revés: a un pescador le
        // decía que su carnet sirve para emitir guías.
        $correcto = $actor === TipoActor::Pescador ? 'permisos de faena' : 'guías de movimiento';

        return new self(
            "Un carnet de {$actor->etiqueta()} no emite {$permiso}. Esa credencial sirve para ".
            "emitir {$correcto}. Si la persona hace las dos actividades, necesita el otro carnet.",
        );
    }

    /** El carnet existe pero hoy no habilita. */
    public static function carnetNoVigente(EstadoCarnet $estado): self
    {
        $detalle = match ($estado) {
            EstadoCarnet::Pendiente => 'Está PENDIENTE: falta que se pague en Recaudaciones.',
            EstadoCarnet::Revocado => 'Está REVOCADO, y eso no se revierte: hay que emitir uno nuevo.',
            // El estado dice «activo» pero la fecha ya pasó: la columna la
            // escribe un comando diario y entre corrida y corrida miente.
            EstadoCarnet::Aprobado => 'Pasó su fecha de vencimiento.',
        };

        return new self("El carnet no está vigente. {$detalle}");
    }

    /** Se pidió una faena sin bolsa madre utilizable detrás. */
    public static function sinCupoVigente(): self
    {
        return new self(
            'El pescador no tiene un aprovechamiento vigente del que descontar kilos. '.
            'Hay que otorgarle la bolsa madre —y que se pague— antes de emitir faenas.',
        );
    }

    /**
     * El cupo existe y está en fecha, pero todavía no se pagó.
     */
    public static function cupoPendienteDePago(): self
    {
        return new self(
            'El aprovechamiento está PENDIENTE DE PAGO y todavía no autoriza a pescar. '.
            'Cuando se pague en Recaudaciones, verifique el pago en su ficha y emita la faena.',
        );
    }

    /**
     * La faena pedida no entra en lo que queda del cupo.
     */
    public static function excedeCupo(float $pedido, float $saldo): self
    {
        return new self(sprintf(
            'La faena declara %s kg y en la bolsa madre quedan %s kg. '.
            'Hay que bajar los kilos o tramitar un aprovechamiento nuevo.',
            number_format($pedido, 2, ',', '.'),
            number_format($saldo, 2, ',', '.'),
        ));
    }

    /**
     * Hay saldo, pero parte está apartado por otras solicitudes abiertas.
     * Lleva «quedan»: FaenaController lo manda al campo de kilos por esa palabra.
     */
    public static function excedeLibre(float $pedido, float $libre, float $reservado): self
    {
        return new self(sprintf(
            'La faena declara %s kg y quedan libres %s kg: %s kg están reservados por faenas '.
            'pendientes. Baje los kilos o elimine la faena que no vaya a salir.',
            number_format($pedido, 2, ',', '.'),
            number_format($libre, 2, ',', '.'),
            number_format($reservado, 2, ',', '.'),
        ));
    }

    /**
     * No se revoca dos veces, y no vuelve atrás.
     */
    public static function guiaYaRevocada(): self
    {
        return new self(
            'Esta guía ya está revocada. No vuelve atrás: si hace falta, emita otra con otro código.',
        );
    }

    /**
     *  El circuito de la faena: pagar en SIREB y aprobar
     */

    /** Se quiso aprobar algo que ya no está pendiente. */
    public static function faenaNoSePuedeRevisar(string $estado): self
    {
        return new self(
            "La faena está {$estado}: solo se aprueba la que está PENDIENTE de pago.",
        );
    }

    /** Se quiso corregir una faena que ya salió del borrador. */
    public static function faenaNoSePuedeEditar(string $estado): self
    {
        return new self(
            "La faena está {$estado} y solo se corrige la que está PENDIENTE.",
        );
    }

    /** Se quiso borrar una faena que ya salió del borrador. */
    public static function faenaNoSePuedeEliminar(string $estado): self
    {
        return new self(
            "La faena está {$estado} y solo se elimina la que está PENDIENTE.",
        );
    }

    public static function faenaYaRevocada(): self
    {
        return new self('Esta faena ya está revocada. No vuelve atrás: si hace falta, emita otra.');
    }

    /** Revocar es para la faena aprobada; el borrador se elimina. */
    public static function faenaNoSeRevoca(string $estado): self
    {
        return new self(
            "Una faena {$estado} no se revoca: revocar es para la faena aprobada. Un borrador se elimina.",
        );
    }

    /**
     *  El circuito de la guía: el mismo de la faena, sobre otro papel
     */

    /** Se quiso aprobar algo que ya no está pendiente. */
    public static function guiaNoSePuedeRevisar(string $estado): self
    {
        return new self(
            "La guía está {$estado}: solo se aprueba la que está PENDIENTE de pago.",
        );
    }

    /** Se quiso corregir una guía que ya salió del borrador. */
    public static function guiaNoSePuedeEditar(string $estado): self
    {
        return new self(
            "La guía está {$estado} y solo se corrige la que está PENDIENTE.",
        );
    }

    /** Se quiso borrar una guía que ya salió del borrador. */
    public static function guiaNoSePuedeEliminar(string $estado): self
    {
        return new self(
            "La guía está {$estado} y solo se elimina la que está PENDIENTE.",
        );
    }

    /** Revocar es para la guía aprobada; el borrador se elimina. */
    public static function guiaNoSeRevoca(string $estado): self
    {
        return new self(
            "Una guía {$estado} no se revoca: revocar es para la guía ya aprobada. ".
            'Un borrador se elimina.',
        );
    }

    /** Sin precio de SIREB para la salida no se emite la faena. */
    public static function faenaSinPrecio(string $motivo): self
    {
        return new self("No se puede emitir el permiso de faena: el arancel {$motivo} Revíselo en Catálogos › Aranceles.");
    }

    /** Sin precio de SIREB para un producto no se emite la guía. */
    public static function productoSinPrecio(string $nombre, string $motivo): self
    {
        return new self("El producto «{$nombre}» {$motivo} Revíselo en Catálogos › Productos.");
    }

    /** El producto elegido no está en el catálogo, o está fuera de uso. */
    public static function productoNoDisponible(string $nombre): self
    {
        return new self(sprintf(
            'El producto «%s» del detalle no está disponible en el catálogo de productos '.
            'hidrobiológicos. Elija otro o actívelo en Catálogos.',
            $nombre,
        ));
    }

    /** Una guía sin renglones no ampara nada: el cuadro D es el traslado. */
    public static function guiaSinDetalle(): self
    {
        return new self(
            'La guía necesita al menos una especie en el detalle: el cuadro de productos '.
            'es lo que el control mira en la ruta.',
        );
    }

    /**
     * Revocar sin motivo escrito no sirve de nada.
     *
     * El número del talonario queda quemado para siempre y deja un hueco en la
     * serie; sin el motivo, dentro de seis meses nadie puede explicarlo.
     */
    public static function motivoObligatorio(): self
    {
        return new self('Hay que escribir el motivo de la anulación: queda un hueco en el talonario que alguien va a tener que explicar.');
    }

    /** La autorización del carnet fue revocada: el carnet vale, pero no autoriza faenas. */
    public static function cupoRevocado(): self
    {
        return new self(
            'La Autorización de Pesca para Aprovechamiento Pesquero de este carnet fue revocada: '.
            'no autoriza faenas. Hace falta una autorización nueva y un carnet colgado de ella.',
        );
    }
}

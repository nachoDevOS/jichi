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
            EstadoCarnet::Revocado => 'Está REVOCADO, y eso no se revierte: hay que emitir uno nuevo.',
            EstadoCarnet::Vencido => 'Está VENCIDO. Hay que emitir el carnet de la gestión en curso '.
                'antes de poder emitir este papel.',
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
            'Hay que otorgarle la bolsa madre —y cobrarla— antes de emitir faenas.',
        );
    }

    /**
     * El cupo existe y está en fecha, pero todavía no se cobró.
     */
    public static function cupoPendienteDePago(): self
    {
        return new self(
            'El aprovechamiento está PENDIENTE DE PAGO y todavía no autoriza a pescar. '.
            'Cóbrelo en caja y después emita la faena.',
        );
    }

    /**
     * El cupo está presentado y esperando una firma.
     */
    public static function cupoEnRevision(): self
    {
        return new self(
            'El aprovechamiento está EN REVISIÓN y todavía no autoriza a pescar. '.
            'Los depósitos ya están cargados: falta que lo aprueben.',
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
     * El número del talonario ya está usado.
     */
    public static function numeroRepetido(string $permiso, string $numero): self
    {
        return new self(
            "Ya existe {$permiso} con el número {$numero}. El número sale del talonario y no se ".
            'puede repetir: verifique la hoja que tiene en la mano.',
        );
    }

    /**
     * No se anula dos veces, y no se desanula.
     */
    public static function guiaYaAnulada(): self
    {
        return new self(
            'Esta guía ya está anulada. No se desanula: si hace falta, emita otra con otro código.',
        );
    }

    /**
     * Una guía CERRADA ya no se anula.
     */
    public static function cerradaNoSeAnula(): self
    {
        return new self(
            'Esta guía ya está cerrada: la carga llegó a destino y el traslado ocurrió. '.
            'Anularla dejaría ese viaje sin ningún papel que lo respalde.',
        );
    }

    /**
     * Solo se cierra una guía que está EN CURSO.
     */
    public static function noSePuedeCerrar(string $estado): self
    {
        return new self(
            "La guía está {$estado} y solo se cierra una que esté en curso.",
        );
    }

    /**
     *  EL CIRCUITO DE LA FAENA: cobrar, presentar y firmar
     */

    /** Se quiso presentar una faena que no está PENDIENTE. */
    public static function faenaNoSePuedeEnviar(string $estado): self
    {
        return new self(
            "La faena está {$estado} y solo se presenta a revisión la que está PENDIENTE.",
        );
    }

    /** Se quiso firmar o rechazar algo que no está presentado. */
    public static function faenaNoSePuedeRevisar(string $estado): self
    {
        return new self(
            "La faena está {$estado}: se aprueba o se rechaza la que está EN REVISIÓN.",
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

    /**
     * Tiene depósitos encima: se resuelven por caja, no borrando la fila.
     */
    public static function faenaTienePagos(int $cuantos): self
    {
        return new self(sprintf(
            'La faena tiene %d depósito(s) cargados. Dé de baja los depósitos antes de '.
            'corregirla o eliminarla: lo que se cobró es por ESTA salida.',
            $cuantos,
        ));
    }

    /** Falta plata para presentarla. */
    public static function faltaCubrirElArancelDeLaFaena(float $saldo): self
    {
        return new self(sprintf(
            'Faltan %s Bs por cubrir del arancel de la faena. Cargue los depósitos antes de '.
            'presentarla a revisión.',
            number_format($saldo, 2, ',', '.'),
        ));
    }

    /** Quedan boletas sin controlar: firmar así dejaría la validación decorativa. */
    public static function faltaControlarBoletasDeLaFaena(int $cuantas): self
    {
        return new self(sprintf(
            'Quedan %d boleta(s) sin controlar. Validelas —o corrija lo observado— antes de '.
            'aprobar la faena.',
            $cuantas,
        ));
    }

    /**
     *  EL CIRCUITO DE LA GUÍA: el mismo de la faena, sobre otro papel
     */

    /** Se quiso presentar una guía que no está PENDIENTE. */
    public static function guiaNoSePuedeEnviar(string $estado): self
    {
        return new self(
            "La guía está {$estado} y solo se presenta a revisión la que está PENDIENTE.",
        );
    }

    /** Se quiso firmar o rechazar algo que no está presentado. */
    public static function guiaNoSePuedeRevisar(string $estado): self
    {
        return new self(
            "La guía está {$estado}: se aprueba o se rechaza la que está EN REVISIÓN.",
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

    /** Tiene depósitos encima: se resuelven por caja, no borrando la fila. */
    public static function guiaTienePagos(int $cuantos): self
    {
        return new self(sprintf(
            'La guía tiene %d depósito(s) cargados. Dé de baja los depósitos antes de '.
            'corregirla o eliminarla: lo que se cobró es por ESTE traslado.',
            $cuantos,
        ));
    }

    /** Falta plata para presentarla. */
    public static function faltaCubrirElArancelDeLaGuia(float $saldo): self
    {
        return new self(sprintf(
            'Faltan %s Bs por cubrir del arancel de la guía. Cargue los depósitos antes de '.
            'presentarla a revisión.',
            number_format($saldo, 2, ',', '.'),
        ));
    }

    /** Quedan boletas sin controlar: firmar así dejaría la validación decorativa. */
    public static function faltaControlarBoletasDeLaGuia(int $cuantas): self
    {
        return new self(sprintf(
            'Quedan %d boleta(s) sin controlar. Validelas —o corrija lo observado— antes de '.
            'aprobar la guía.',
            $cuantas,
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
     * Anular sin motivo escrito no sirve de nada.
     *
     * El número del talonario queda quemado para siempre y deja un hueco en la
     * serie; sin el motivo, dentro de seis meses nadie puede explicarlo.
     */
    public static function motivoObligatorio(): self
    {
        return new self('Hay que escribir el motivo de la anulación: queda un hueco en el talonario que alguien va a tener que explicar.');
    }
}

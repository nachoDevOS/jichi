<?php

namespace App\Exceptions;

use App\Enums\EstadoCarnet;
use App\Enums\TipoActor;
use RuntimeException;

/**
 * Una regla de emisión dijo que no.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ UNA EXCEPCIÓN Y NO UN `return false`
 * ----------------------------------------------------------------------------
 *
 * Los servicios trabajan dentro de una transacción, y un `return false` a la
 * mitad obliga a que quien llama se acuerde de deshacerla. Con una excepción,
 * `DB::transaction()` hace el rollback solo — y si alguien olvida el `catch`,
 * el error se ve, que es infinitamente mejor que una transacción a medias que
 * nadie notó.
 *
 * ----------------------------------------------------------------------------
 *  EL MENSAJE LO LEE EL OPERADOR DE VENTANILLA
 * ----------------------------------------------------------------------------
 *
 * Sale tal cual en el aviso rojo de la pantalla, así que se escribe en
 * castellano de mostrador y DICE QUÉ HACER, no solo que no se puede: quien
 * llegó hasta acá tiene a alguien enfrente esperando un papel.
 */
class PermisoOperativoException extends RuntimeException
{
    /**
     * La credencial no habilita ESTE papel.
     *
     * Quién puede emitir qué lo dice `TipoActor`, NUNCA el nombre del tipo de
     * carnet: `tipos_carnet` es un catálogo que edita la unidad y el mismo
     * documento figura como «Carnet de Pescador» o «Pescador Artesanal» según
     * quién lo cargó.
     */
    public static function actorNoEmite(string $permiso, TipoActor $actor): self
    {
        $correcto = $actor === TipoActor::Pescador ? 'guías de movimiento' : 'permisos de faena';

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
            EstadoCarnet::Activo => 'Pasó su fecha de vencimiento.',
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
     *
     * Es un mensaje propio y no `sinCupoVigente()` a propósito: ese texto manda
     * a OTORGAR una bolsa madre, y acá la bolsa ya está otorgada. Lo que falta
     * es plata, y el operador la puede cobrar en el acto — mandarlo a otorgar
     * otra lo llevaría a una regla que va a rechazarlo, que es una vuelta
     * perdida con el pescador enfrente.
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
     *
     * Mensaje propio y no el de «pendiente de pago»: acá la plata YA entró, y
     * decirle al operador que cobre lo mandaría a buscar un depósito que no
     * existe. Lo que falta es una firma, y eso no se resuelve en la ventanilla.
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
     *
     * Se dicen los DOS números y no solo «no alcanza», porque lo que el
     * operador necesita decidir enfrente del pescador es por cuánto sí puede
     * emitirla.
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
     *
     * No se ofrece «usar el siguiente» automáticamente a propósito: el número
     * sale de un papel que el operador tiene en la mano, y si no coincide con
     * lo que el sistema propone hay algo mal que conviene mirar.
     */
    public static function numeroRepetido(string $permiso, string $numero): self
    {
        return new self(
            "Ya existe {$permiso} con el número {$numero}. El número sale del talonario y no se ".
            'puede repetir: verifique la hoja que tiene en la mano.',
        );
    }

    /**
     * Solo se completa una faena que está EN CURSO.
     *
     * Completar es registrar que el pescador volvió y descargó. Sobre una ya
     * completada no hay nada que registrar; sobre una VENCIDA tampoco, y ahí el
     * matiz importa: al vencer, la faena liberó su volumen, así que completarla
     * lo volvería a descontar de un cupo que ya se repuso.
     */
    public static function noSePuedeCompletar(string $estado): self
    {
        return new self(
            "La faena está {$estado} y solo se completa una que esté en curso. ".
            'Si el pescador volvió después del plazo, lo que corresponde es emitir una faena nueva.',
        );
    }

    /**
     * No se anula dos veces, y no se desanula.
     *
     * Es específico de las guías y no genérico a propósito: las faenas NO se
     * anulan —`EstadoFaena` no tiene ese estado— así que un método que dijera
     * «{X} ya está anulado» tendría que resolver el género del sujeto para un
     * solo caso. Escrito derecho, se lee derecho.
     */
    public static function guiaYaAnulada(): self
    {
        return new self(
            'Esta guía ya está anulada. No se desanula: si hace falta, emita otra con otro código.',
        );
    }

    /**
     * Una guía CERRADA ya no se anula.
     *
     * Cerrar significa que la carga llegó a destino: el traslado ocurrió y esta
     * guía lo amparó. Anularla después sería declarar que nunca amparó nada, y
     * eso deja un viaje real sin ningún papel que lo respalde — justo lo
     * contrario de para qué existe la guía.
     *
     * Si el problema es que se emitió mal, lo que corresponde es dejar
     * constancia por otro lado, no borrar el respaldo de un viaje que se hizo.
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
     *
     * Cerrar es registrar que la carga llegó. Sobre una anulada no hay nada que
     * cerrar —ese papel no amparó ningún traslado— y sobre una ya cerrada
     * tampoco.
     */
    public static function noSePuedeCerrar(string $estado): self
    {
        return new self(
            "La guía está {$estado} y solo se cierra una que esté en curso.",
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

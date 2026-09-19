<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una regla del otorgamiento de cupo dijo que no.
 *
 * Mismo criterio que PermisoOperativoException: es una excepción y no un
 * `return false` porque el servicio trabaja dentro de una transacción, y con la
 * excepción `DB::transaction()` hace el rollback solo. Con un booleano, quien
 * llama tiene que acordarse de deshacerla, y si se olvida queda media operación
 * escrita sin que nadie lo note.
 *
 * El mensaje sale tal cual en el aviso rojo de la pantalla, así que se escribe
 * en castellano de mostrador y DICE QUÉ HACER: quien llegó hasta acá tiene a
 * alguien enfrente esperando.
 */
class CupoInvalidoException extends RuntimeException
{
    /**
     * ========================================================================
     *  UNA PERSONA, UNA BOLSA MADRE VIGENTE A LA VEZ
     * ========================================================================
     *
     * Es LA regla del módulo. Dos cupos vigentes al mismo tiempo son el doble
     * de kilos de los que la escala le otorgó, y no hay forma de notarlo
     * mirando: cada uno por separado se ve correcto, y `saldoKg()` de cada uno
     * da un número razonable.
     *
     * No la garantiza ningún índice de la base —no se puede, porque «vigente»
     * depende de la fecha de hoy— así que la sostiene el servicio, con la fila
     * del beneficiario bloqueada para que dos ventanillas simultáneas no pasen
     * las dos.
     *
     * Lo que corresponde cuando pasa NO es otorgar otro: es AMPLIAR el que
     * está, y por eso el mensaje lo dice.
     */
    public static function yaTieneCupoVigente(string $persona, float $saldo, string $vence): self
    {
        return new self(sprintf(
            '%s ya tiene un aprovechamiento vigente hasta el %s, con %s kg sin usar. '.
            'No se otorga un segundo cupo: si necesita más volumen, hay que AMPLIAR el que tiene.',
            $persona,
            $vence,
            number_format($saldo, 2, ',', '.'),
        ));
    }

    /**
     * Se eligió un tramo de la escala que ya no está vigente.
     *
     * Puede pasar entre que el operador abre el formulario y aprieta guardar:
     * la lista se armó con los tramos de ese momento y en el medio alguien
     * derogó uno desde el catálogo.
     */
    public static function escalaDerogada(int $nroEscala): self
    {
        return new self(
            "La escala {$nroEscala} fue derogada y ya no se puede otorgar. ".
            'Vuelva a abrir el formulario para ver los tramos vigentes.',
        );
    }

    /**
     * ========================================================================
     *  UNA ESPECIE ESPECIAL NO SE AMPLÍA
     * ========================================================================
     *
     * Es la única diferencia de comportamiento entre las dos modalidades. En la
     * escala general, ampliar es la forma prevista de seguir pescando cuando el
     * volumen se acaba — la «recarga continua» de los rangos menores.
     *
     * En la especie especial no existe esa recarga: la cuota la autoriza una
     * resolución sobre esa especie, y estirarla desde una pantalla sería
     * saltearla. Lo que corresponde es el trámite completo —elegir el tramo,
     * pagar en caja, recibo nuevo—, que es justamente lo que deja constancia de
     * que alguien volvió a autorizar.
     */
    public static function modalidadNoAmpliable(string $modalidad): self
    {
        return new self(
            "Un aprovechamiento de {$modalidad} no se amplía: su cuota la autoriza una resolución ".
            'sobre la especie. Lo que corresponde es tramitar uno nuevo — elegir el tramo, cobrarlo '.
            'y emitir el recibo.',
        );
    }

    /** Ampliar de a cero o en negativo no es ampliar. */
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

    public static function ampliacionSinKilos(): self
    {
        return new self('La ampliación tiene que sumar kilos: indique un volumen mayor que cero.');
    }

    /**
     * No se amplía un cupo que ya no corre.
     *
     * Sumarle kilos a un cupo vencido daría volumen que no se puede usar —las
     * faenas miran la fecha— así que sería puro ruido en la ficha. Lo que
     * corresponde es otorgar el de la gestión nueva.
     */
    public static function noSePuedeAmpliar(): self
    {
        return new self(
            'Este aprovechamiento no está vigente, así que no se puede ampliar. '.
            'Lo que corresponde es otorgar el cupo de la gestión en curso.',
        );
    }
}

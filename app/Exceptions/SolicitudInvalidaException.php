<?php

namespace App\Exceptions;

use App\Enums\EstadoCarnet;
use App\Enums\EstadoTramite;
use RuntimeException;

/**
 * Una regla de negocio del módulo de carnets dijo que no.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ UNA EXCEPCIÓN Y NO UN `return false`
 * ----------------------------------------------------------------------------
 *
 * Porque el servicio trabaja dentro de una transacción. Un `return false` a la
 * mitad obliga a que quien llama se acuerde de hacer el rollback, y el día que
 * alguien agregue una comprobación nueva y devuelva false sin más, la
 * transacción queda abierta con datos a medio escribir.
 *
 * Con una excepción, DB::transaction() deshace todo solo. No hay forma de
 * olvidarse.
 *
 * ----------------------------------------------------------------------------
 *  EL MENSAJE LO LEE EL OPERADOR DE VENTANILLA
 * ----------------------------------------------------------------------------
 *
 * Los mensajes de esta clase salen tal cual en el toast rojo de la pantalla, así
 * que se escriben en castellano de mostrador y no de programador: «Este rubro ya
 * está habilitado en el carnet de 2026», no «duplicate key value violates unique
 * constraint carnets_beneficiario_rubro_gestion_unique».
 *
 * Los constructores estáticos de abajo existen para eso: que el texto que ve el
 * ciudadano esté escrito en un solo lugar y no repartido por los controladores.
 */
class SolicitudInvalidaException extends RuntimeException
{
    public static function rubroInactivo(string $rubro): self
    {
        return new self("El rubro «{$rubro}» está dado de baja y no se puede solicitar.");
    }

    public static function solicitudEnCurso(string $rubro): self
    {
        return new self("Ya hay una solicitud pendiente para el rubro «{$rubro}». Resuelva esa antes de presentar otra.");
    }

    /*
     * Los mensajes nombran el carnet por su RUBRO y su GESTIÓN, no por su firma.
     *
     * Desde que se retiró la columna `codigo`, lo único que identifica un carnet
     * es una firma de dieciséis caracteres. Meterla en un mensaje de ventanilla
     * —«el carnet 4K7RJ2MXP9TQ3WHB no está vigente»— no ayuda a nadie: el
     * operador está mirando a una persona concreta, y lo que necesita saber es
     * de qué ACTIVIDAD y de qué AÑO es el carnet que no le sirve. Con un carnet
     * por rubro, la gestión sola dejó de alcanzar: una persona puede tener tres
     * carnets de 2026 y solo uno suspendido.
     */

    /**
     * El carnet existe pero no admite que se le presente un trámite.
     *
     * ------------------------------------------------------------------------
     *  EL MENSAJE CAMBIA SEGÚN EL ESTADO, Y NO ES ADORNO
     * ------------------------------------------------------------------------
     *
     * Las tres situaciones se resuelven de forma distinta y el operador tiene
     * que saber cuál le tocó:
     *
     *   - SUSPENDIDO: un supervisor tiene que levantarlo. No se tramita de nuevo,
     *     porque la autorización ya se pagó. Decirle «no está vigente» a secas lo
     *     manda a cargar un trámite que va a volver a rebotar.
     *   - ANULADO: no hay vuelta atrás, y además el carnet SIGUE OCUPANDO su
     *     lugar en el índice único, así que tampoco se puede emitir otro del
     *     mismo rubro ese año. Si el mensaje no lo dice, el operador busca la
     *     forma de emitir uno nuevo y no la encuentra.
     *   - VENCIDO: lo que corresponde es el carnet de la gestión siguiente.
     */
    public static function carnetNoAdmiteTramites(string $rubro, int $gestion, EstadoCarnet $estado): self
    {
        $detalle = match ($estado) {
            EstadoCarnet::Suspendido => 'Está SUSPENDIDO: un supervisor tiene que levantar la suspensión '.
                'desde la ficha del carnet. No corresponde tramitarlo de nuevo.',

            EstadoCarnet::Anulado => 'Está ANULADO, y eso no se revierte. Tampoco se puede emitir otro carnet '.
                'del mismo rubro para esta gestión: el anulado conserva su lugar.',

            EstadoCarnet::Vencido => 'Está VENCIDO. Lo que corresponde es emitir el carnet de la gestión siguiente.',

            EstadoCarnet::Vigente => 'No está vigente.',
        };

        return new self("El carnet de «{$rubro}» de la gestión {$gestion} no admite trámites. {$detalle}");
    }

    public static function beneficiarioDadoDeBaja(): self
    {
        return new self('El beneficiario está dado de baja. Reactive su ficha antes de iniciar el trámite.');
    }

    public static function tramiteYaResuelto(string $estado): self
    {
        return new self("El trámite ya está {$estado} y no admite más cambios.");
    }

    /**
     * Se intentó EDITAR un expediente que ya salió del borrador.
     *
     * ------------------------------------------------------------------------
     *  DOS MENSAJES, PORQUE SON DOS PROBLEMAS DISTINTOS
     * ------------------------------------------------------------------------
     *
     * A quien está en ventanilla no le sirve «no se puede»: necesita saber por
     * dónde salir, y la salida cambia según dónde esté parado el expediente.
     *
     *   EN REVISIÓN  todavía se puede hacer algo: rechazarlo devuelve los
     *                papeles con el motivo escrito, y se presenta de nuevo
     *                corregido. Eso es lo que hay que decirle.
     *
     *   RESUELTO     ya no hay vuelta: alguien firmó. El mensaje solo confirma
     *                que la decisión está tomada.
     *
     * Ver EstadoTramite::permiteEdicion().
     */
    public static function tramiteNoSePuedeEditar(EstadoTramite $estado): self
    {
        if ($estado === EstadoTramite::EnRevision) {
            return new self(
                'El trámite ya fue enviado a revisión y no se puede editar: el recibo oficial '.
                'ya está en manos del beneficiario. Si los papeles no sirven, rechácelo indicando '.
                'el motivo y preséntelo de nuevo.',
            );
        }

        return self::tramiteYaResuelto(mb_strtolower($estado->etiqueta()));
    }

    /**
     * Se intentó borrar un expediente que ya salió del borrador.
     *
     * Mismo criterio que `tramiteNoSePuedeEditar()`: el mensaje dice qué hacer
     * en su lugar y no solo que no se puede, porque quien llegó hasta acá tiene
     * un problema real —un trámite que no debería estar— y necesita saber por
     * dónde salir. Ver EstadoTramite::permiteEliminacion().
     */
    public static function tramiteNoSePuedeEliminar(EstadoTramite $estado): self
    {
        if ($estado === EstadoTramite::EnRevision) {
            return new self(
                'Un trámite enviado a revisión no se puede eliminar: ya se emitió su recibo '.
                'oficial y el beneficiario se llevó ese papel. Si el expediente no corresponde, '.
                'recházelo indicando el motivo.',
            );
        }

        return new self(
            'Un trámite '.mb_strtolower($estado->etiqueta()).' no se puede eliminar: es el registro '.
            'de una decisión ya tomada. Si el rubro se habilitó por error, suspéndalo desde la ficha '.
            'del carnet.',
        );
    }

    /**
     * Un salto de estado que el flujo no contempla.
     *
     * Se separa de `tramiteYaResuelto()` porque en ventanilla son dos
     * situaciones distintas: que un expediente ya esté cerrado se explica solo,
     * pero un salto raro —volver a «en revisión» algo que ya está en revisión,
     * por ejemplo— casi siempre es un botón que quedó a la vista cuando no
     * correspondía, y conviene que el mensaje lo delate en vez de disimularlo.
     */
    public static function transicionInvalida(string $desde, string $hacia): self
    {
        return new self("No se puede pasar un trámite de «{$desde}» a «{$hacia}».");
    }

    public static function tramiteImpago(float $saldo, string $moneda = 'Bs'): self
    {
        return new self(sprintf(
            'No se puede aprobar: falta cubrir %s %s del costo del trámite.',
            $moneda,
            number_format($saldo, 2, ',', '.'),
        ));
    }

    public static function pagoNoAdmitido(): self
    {
        return new self('El trámite está rechazado: no admite registrar pagos.');
    }

    public static function transaccionRepetida(string $nro): self
    {
        return new self("El número de transacción «{$nro}» ya fue registrado en otro pago.");
    }

    public static function motivoRechazoObligatorio(): self
    {
        return new self('Para rechazar un trámite hay que escribir el motivo.');
    }

    /**
     * Se quiso borrar un expediente sin decir por qué.
     *
     * Borrar es la única operación del sistema que NO deja la fila: el trámite,
     * sus pagos y sus archivos desaparecen. Lo único que queda es la línea de
     * `auditorias`, así que si ahí no está el motivo, no queda nada que explique
     * por qué ese expediente ya no está.
     */
    public static function motivoEliminacionObligatorio(): self
    {
        return new self('Para eliminar un trámite hay que escribir por qué: es lo único que va a quedar del expediente.');
    }

    /**
     * Se quiso tomar para revisión un expediente al que le faltan papeles.
     *
     * El mensaje ENUMERA lo que falta en vez de decir «el expediente está
     * incompleto». Es la diferencia entre que el operador sepa qué ir a buscar y
     * que tenga que adivinar abriendo la ficha pieza por pieza.
     *
     * @param  array<int, string>  $faltantes
     */
    public static function expedienteIncompleto(array $faltantes): self
    {
        $lista = count($faltantes) === 1
            ? $faltantes[0]
            : implode(', ', array_slice($faltantes, 0, -1)).' y '.end($faltantes);

        return new self(
            "No se puede tomar para revisión: falta {$lista}. ".
            'Cárguelo con «Editar trámite» y vuelva a intentarlo.',
        );
    }
}

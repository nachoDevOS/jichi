<?php

namespace App\Exceptions;

use App\Enums\EstadoCarnet;
use RuntimeException;

/**
 * Una regla de emisión de faenas o guías dijo que no.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ UNA EXCEPCIÓN Y NO UN `return false`
 * ----------------------------------------------------------------------------
 *
 * Mismo motivo que en SolicitudInvalidaException: los servicios trabajan dentro
 * de una transacción, y un `return false` a la mitad obliga a que quien llama se
 * acuerde del rollback. Con una excepción, DB::transaction() deshace todo solo.
 *
 * ----------------------------------------------------------------------------
 *  EL MENSAJE LO LEE EL OPERADOR DE VENTANILLA
 * ----------------------------------------------------------------------------
 *
 * Sale tal cual en el aviso rojo de la pantalla, así que se escribe en
 * castellano de mostrador y DICE QUÉ HACER, no solo que no se puede: quien llegó
 * hasta acá tiene a alguien enfrente esperando un papel.
 *
 * Es una clase aparte de SolicitudInvalidaException a propósito. Podrían
 * compartirla —las dos son «una regla dijo que no»— pero entonces el catch de un
 * controlador de trámites atraparía también errores de faenas y al revés, y el
 * día que alguien quiera tratarlos distinto tendría que separarlos igual.
 */
class PermisoOperativoException extends RuntimeException
{
    /**
     * El rubro del carnet no emite este permiso.
     *
     * Es el error más probable del módulo: el operador busca a la persona, le
     * aparecen sus dos carnets y elige el que no era. Por eso el mensaje nombra
     * LOS DOS rubros —el que eligió y el que hace falta—, en vez de decir «el
     * carnet no corresponde».
     */
    public static function rubroNoEmite(string $permiso, string $rubro): self
    {
        return new self(
            "El carnet de «{$rubro}» no emite {$permiso}. Elija el carnet de la actividad ".
            'que corresponde, o habilítelo desde el catálogo de rubros.',
        );
    }

    /**
     * El carnet existe pero no vale hoy.
     *
     * El detalle cambia según el estado porque cada situación se resuelve de
     * forma distinta, igual que en SolicitudInvalidaException::carnetNoAdmiteTramites().
     */
    public static function carnetNoVigente(string $rubro, int $gestion, EstadoCarnet $estado): self
    {
        $detalle = match ($estado) {
            EstadoCarnet::Suspendido => 'Está SUSPENDIDO: un supervisor tiene que levantar la suspensión '.
                'desde la ficha del carnet.',

            EstadoCarnet::Anulado => 'Está ANULADO, y eso no se revierte.',

            EstadoCarnet::Vencido => 'Está VENCIDO. Hay que emitir el carnet de la gestión en curso '.
                'antes de poder seguir trabajando.',

            // Estado vigente y aun así no vale: le pasó la fecha de vencimiento
            // y el proceso que marca los vencidos todavía no corrió.
            EstadoCarnet::Vigente => 'Pasó su fecha de vencimiento.',
        };

        return new self("El carnet de «{$rubro}» de la gestión {$gestion} no está vigente. {$detalle}");
    }

    /**
     * El número del talonario ya está usado.
     *
     * El índice único de la tabla es quien lo garantiza de verdad; esto da el
     * mensaje legible. Se comprueba antes de insertar porque en PostgreSQL un
     * INSERT fallido aborta la transacción entera.
     */
    public static function numeroRepetido(string $permiso, string $numero): self
    {
        return new self(
            "El número «{$numero}» ya está registrado en otra {$permiso}. ".
            'Revise el talonario: dos papeles no pueden llevar el mismo número.',
        );
    }

    /** Una guía sin carga no ampara nada. */
    public static function guiaSinDetalle(): self
    {
        return new self('Cargue al menos una línea de producto: una guía sin carga no ampara ningún traslado.');
    }

    /**
     * La carga declarada no entra en el vehículo.
     *
     * NO es una comprobación de la base: la capacidad es un dato del transporte
     * y puede venir vacía. Cuando está, vale la pena frenar — una guía que
     * declara más de lo que el camión puede llevar se cae sola en el control.
     */
    public static function excedeCapacidad(float $declarado, float $capacidad): self
    {
        return new self(sprintf(
            'La carga declarada (%s kg) supera la capacidad del transporte (%s kg).',
            number_format($declarado, 2, ',', '.'),
            number_format($capacidad, 2, ',', '.'),
        ));
    }

    /**
     * Se quiso anular algo que ya estaba anulado.
     *
     * No se «desanula»: si hizo falta dar de baja el papel, lo que corresponde
     * es emitir otro con un número nuevo del talonario.
     */
    public static function yaAnulado(string $permiso): self
    {
        return new self("Esta {$permiso} ya está anulada. Para reemplazarla hay que emitir otra.");
    }

    /**
     * Se quiso anular sin decir por qué.
     *
     * El motivo es lo único que queda explicando por qué un número del talonario
     * dejó de valer. Sin él, dentro de un año nadie puede reconstruirlo.
     */
    public static function motivoObligatorio(): self
    {
        return new self('Para anular hay que escribir el motivo: es lo único que va a explicar por qué ese número no vale.');
    }
}

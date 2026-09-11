<?php

namespace App\Enums;

/**
 * Los cuatro estados por los que pasa un trámite.
 *
 *     EN REVISIÓN ──▶ APROBADO ──▶ ENTREGADO
 *          │              │
 *          └──────────────┴──────▶ RECHAZADO
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ SON CUATRO Y NO SEIS
 * ----------------------------------------------------------------------------
 *
 * Antes había dos estados más, y los dos sobraban:
 *
 *   RECIBIDO — era el estado inicial de los servicios que no exigían
 *   aprobación, y había que pasarlos a revisión con un botón. Dos estados para
 *   decir lo mismo: el expediente está sobre el escritorio y nadie lo resolvió
 *   todavía. Ahora TODO trámite nace en revisión.
 *
 *   EMITIDO — decía que el documento ya estaba impreso. Pero eso no es un
 *   estado del trámite sino un hecho que se puede mirar: o existe la fila en
 *   `documentos`, o no existe. Tenerlo como estado obligaba a mantener
 *   sincronizadas dos cosas que pueden discrepar —un trámite «emitido» sin
 *   documento, o al revés—, y eso es un error que la base no puede impedir.
 *
 * Emitir el documento sigue siendo un paso propio, con su permiso y su regla
 * de «no se emite sin cobrar»; lo que ya no hace es mover el estado. El
 * trámite se queda APROBADO hasta que se entrega. Ver
 * TramiteController::emitir().
 */
enum EstadoTramite: string
{
    case EnRevision = 'en_revision';
    case Aprobado = 'aprobado';
    case Entregado = 'entregado';
    case Rechazado = 'rechazado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnRevision => 'En Revisión',
            self::Aprobado => 'Aprobado',
            self::Entregado => 'Entregado',
            self::Rechazado => 'Rechazado',
        };
    }

    /**
     * Clases Tailwind del badge de estado usado en las tablas de React.
     */
    public function color(): string
    {
        return match ($this) {
            self::EnRevision => 'amber',
            self::Aprobado => 'sky',
            self::Entregado => 'emerald',
            self::Rechazado => 'rose',
        };
    }

    /**
     * Estados a los que se puede pasar desde el estado actual.
     *
     * @return array<int, self>
     */
    public function siguientes(): array
    {
        return match ($this) {
            self::EnRevision => [self::Aprobado, self::Rechazado],
            self::Aprobado => [self::Entregado, self::Rechazado],
            self::Entregado, self::Rechazado => [],
        };
    }

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->siguientes(), true);
    }

    /**
     * ¿Se puede todavía corregir el expediente?
     *
     * Solo antes de que alguien lo resuelva. Un trámite en revisión es
     * papelería en curso: el escaneo salió ilegible, el monto se anotó mal,
     * falta un comprobante. Eso se arregla y sigue.
     *
     * Desde APROBADO ya no: alguien firmó mirando esos papeles, y cambiarlos
     * después dejaría una aprobación que no corresponde a lo que hay en el
     * expediente. Si algo estaba mal, lo que corresponde es rechazar y volver a
     * cargarlo, que además deja el motivo escrito.
     *
     * La regla vive acá y no en el controlador porque es del negocio, no de una
     * pantalla: la ficha decide con esto si dibuja el botón de editar, y el
     * servidor decide con esto si acepta el PUT.
     */
    public function permiteEdicion(): bool
    {
        return $this === self::EnRevision;
    }

    public function esFinal(): bool
    {
        return $this->siguientes() === [];
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $estado) => [
            'value' => $estado->value,
            'label' => $estado->etiqueta(),
            'color' => $estado->color(),
        ], self::cases());
    }
}

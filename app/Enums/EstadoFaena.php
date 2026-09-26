<?php

namespace App\Enums;

/**
 * En qué situación está un permiso de faena — la autorización de UNA salida.
 */
enum EstadoFaena: string
{
    /** Cargada y sin cobrar. Es como NACE toda faena: todavía no autoriza nada. */
    case Pendiente = 'pendiente';

    /** Los depósitos cubren el arancel; falta que alguien firme. */
    case EnRevision = 'en_revision';

    /** Firmada y en curso: el pescador está afuera. Se llamaba `activo` hasta el 25/09/2026. */
    case Aprobado = 'aprobado';

    /** Volvió y descargó. Los kilos quedaron firmes contra el cupo. */
    case Completado = 'completado';

    /** Se le pasó la fecha límite sin cerrarse. Libera el volumen reservado. */
    case Vencido = 'vencido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnRevision => 'En revisión',
            self::Aprobado => 'Aprobado',
            self::Completado => 'Completado',
            self::Vencido => 'Vencido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'sky',
            self::EnRevision => 'indigo',
            self::Aprobado => 'emerald',
            self::Completado => 'teal',
            self::Vencido => 'slate',
        };
    }

    /**
     * ¿Sus kilos pesan contra el cupo de la bolsa madre?
     *
     * SOLO DESDE LA FIRMA (19/09/2026, a pedido del responsable). Una faena
     * pendiente o en revisión es una SOLICITUD: todavía no autoriza a pescar,
     * así que no puede estar restándole kilos a la bolsa.
     *
     * ⚠️ El costo es que el cupo se puede sobrecomprometer: tres solicitudes
     * por el volumen entero se aceptan las tres, y el choque aparece al
     * aprobar la segunda. Por eso `RevisarFaenaService::aprobar()` vuelve a
     * medir el saldo con la fila del cupo bloqueada — ahí está el control que
     * antes hacía la reserva.
     */
    public function consumeCupo(): bool
    {
        return $this === self::Aprobado || $this === self::Completado;
    }

    /** ¿Autoriza a estar pescando hoy? Solo la aprobada. */
    public function habilita(): bool
    {
        return $this === self::Aprobado;
    }

    /**
     *  EL CIRCUITO, el mismo del carnet y del aprovechamiento
     *
     * Estos métodos deciden qué se puede hacer en cada estado. Viven acá y NO
     * en el controlador: el servicio pregunta, y React recibe la respuesta ya
     * resuelta en los campos `puede_*`.
     */

    /**
     * ¿Se pueden corregir sus datos? Solo el BORRADOR.
     *
     * Al enviarla a revisión sale el recibo y el pescador se va con el papel,
     * así que desde ahí lo que no sirve se rechaza, no se edita.
     */
    public function permiteEdicion(): bool
    {
        return $this === self::Pendiente;
    }

    /** ¿Se puede borrar la fila? Mismo criterio que la edición. */
    public function permiteEliminacion(): bool
    {
        return $this === self::Pendiente;
    }

    /** ¿Se le pueden cargar depósitos? Solo mientras nadie la firmó. */
    public function permitePagos(): bool
    {
        return $this === self::Pendiente;
    }

    /** ¿Se puede mandar a que alguien la firme? */
    public function permiteEnvio(): bool
    {
        return $this === self::Pendiente;
    }

    /** ¿Se puede aprobar o rechazar? Solo lo que está presentado. */
    public function permiteRevision(): bool
    {
        return $this === self::EnRevision;
    }

    /**
     * Pendiente + en revisión: nadie la firmó todavía.
     *
     * ⚠️ NO ES PERMISO DE TRABAJO —para eso está `habilita()`— ni de
     * escritura: eso lo dicen `permiteEdicion()` y `permiteEliminacion()`.
     */
    public function estaAbierto(): bool
    {
        return $this === self::Pendiente || $this === self::EnRevision;
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $e): array => [
            'value' => $e->value,
            'label' => $e->etiqueta(),
            'color' => $e->color(),
        ], self::cases());
    }
}

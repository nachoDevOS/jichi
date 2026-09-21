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

    /** Aprobada y en curso: el pescador está afuera. */
    case Activo = 'activo';

    /** Volvió y descargó. Los kilos quedaron firmes contra el cupo. */
    case Completado = 'completado';

    /** Se le pasó la fecha límite sin cerrarse. Libera el volumen reservado. */
    case Vencido = 'vencido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnRevision => 'En revisión',
            self::Activo => 'Aprobado',
            self::Completado => 'Completado',
            self::Vencido => 'Vencido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'sky',
            self::EnRevision => 'indigo',
            self::Activo => 'emerald',
            self::Completado => 'teal',
            self::Vencido => 'slate',
        };
    }

    /**
     * ¿Sus kilos pesan contra el cupo de la bolsa madre?
     *
     * LA PENDIENTE TAMBIÉN RESERVA, y es deliberado: el volumen se aparta
     * cuando se pide, no cuando se firma. Sin eso, tres solicitudes por el
     * cupo entero pasarían las tres —cada una leería el saldo sin ver a las
     * otras— y el pescador terminaría con más kilos autorizados que su bolsa.
     */
    public function consumeCupo(): bool
    {
        return $this !== self::Vencido;
    }

    /** ¿Autoriza a estar pescando hoy? Solo la aprobada. */
    public function habilita(): bool
    {
        return $this === self::Activo;
    }

    /**
     *  EL CIRCUITO, el mismo del carnet y del aprovechamiento
     *
     * Estos métodos deciden qué se puede hacer en cada estado. Viven acá y NO
     * en el controlador: el servicio pregunta, y React recibe la respuesta ya
     * resuelta en los campos `puede_*`.
     */

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
     * ⚠️ NO ES PERMISO DE TRABAJO. Para eso está `habilita()`.
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

<?php

namespace App\Enums;

/**
 * En qué situación está un permiso de faena — la autorización de UNA salida.
 */
enum EstadoFaena: string
{
    /** Emitida y en curso: el pescador está afuera. */
    case Activo = 'activo';

    /** Volvió y descargó. Los kilos quedaron firmes contra el cupo. */
    case Completado = 'completado';

    /** Se le pasó la fecha límite sin cerrarse. Libera el volumen reservado. */
    case Vencido = 'vencido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Completado => 'Completado',
            self::Vencido => 'Vencido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Activo => 'sky',
            self::Completado => 'emerald',
            self::Vencido => 'slate',
        };
    }

    /**
     * ¿Sus kilos pesan contra el cupo de la bolsa madre?
     */
    public function consumeCupo(): bool
    {
        return $this !== self::Vencido;
    }

    /** ¿Autoriza a estar pescando hoy? */
    public function habilita(): bool
    {
        return $this === self::Activo;
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

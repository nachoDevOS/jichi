<?php

namespace App\Enums;

/**
 * En qué situación está un permiso de faena — la autorización de UNA salida.
 *
 * ----------------------------------------------------------------------------
 *  «COMPLETADO» ES LO QUE CIERRA EL CIRCUITO DE LA BOLSA MADRE
 * ----------------------------------------------------------------------------
 *
 * Una faena nace `Activo`: el pescador se llevó el papel y salió. Cuando vuelve
 * y descarga, la faena pasa a `Completado` — y es en ese momento cuando sus
 * `kilos_extraidos` cuentan definitivamente contra el cupo.
 *
 * `Vencido` es la faena que se pasó de `fecha_limite` sin cerrarse. NO se borra
 * ni se reutiliza el número: el talonario ya gastó esa hoja.
 *
 * Los kilos de una faena activa o completada se descuentan igual del saldo —lo
 * contrario dejaría emitir faenas infinitas mientras ninguna se cierre—; los de
 * una vencida se liberan. Ver AprovechamientoPesq::kilosConsumidos().
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
     *
     * Activo SÍ, aunque todavía no se haya descargado nada: el volumen está
     * comprometido y comprometerlo dos veces es justamente lo que el cupo viene
     * a impedir. Vencido NO: la salida no ocurrió y el volumen vuelve.
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

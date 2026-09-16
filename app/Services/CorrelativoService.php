<?php

namespace App\Services;

use App\Models\Correlativo;
use Illuminate\Support\Facades\DB;

/**
 * Entrega números correlativos por serie y gestión: DOC-PESCA-2026-0001.
 *
 * El contador se bloquea con SELECT ... FOR UPDATE dentro de una transacción,
 * de modo que dos ventanillas cobrando al mismo tiempo nunca reciben el mismo
 * número. El contador se reinicia solo al cambiar de año porque la unicidad
 * de la fila es (serie, anio).
 */
class CorrelativoService
{
    public const RELLENO = 4;

    /**
     * Reserva el siguiente número de la serie y devuelve el código formateado.
     */
    public function siguiente(string $serie, ?int $anio = null): string
    {
        $anio ??= (int) now()->format('Y');

        return $this->formatear($serie, $anio, $this->siguienteNumero($serie, $anio));
    }

    /**
     * Reserva el siguiente número y lo devuelve CRUDO, sin formatear.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ EXISTEN LAS DOS FORMAS
     * ------------------------------------------------------------------------
     *
     * `siguiente()` devuelve el código completo —SERIE-2026-0001— que es lo que
     * quiere quien necesita un identificador legible y único por sí solo.
     *
     * Los RECIBOS no: el talonario de papel trae el número pelado arriba a la
     * derecha —0016— y el recibo digital tiene que decir lo mismo. Además lo
     * guarda como entero para poder ordenarlo y sacar el último de la serie,
     * cosa que con el código formateado no se puede.
     *
     * La reserva —el bloqueo de la fila del contador— es la misma para los dos,
     * y vive acá adentro una sola vez.
     */
    public function siguienteNumero(string $serie, ?int $anio = null): int
    {
        $anio ??= (int) now()->format('Y');

        return DB::transaction(function () use ($serie, $anio): int {
            $correlativo = Correlativo::query()
                ->where('serie', $serie)
                ->where('anio', $anio)
                ->lockForUpdate()
                ->first();

            if (! $correlativo) {
                // firstOrCreate no sirve acá: entre el SELECT y el INSERT otra
                // conexión puede crear la fila, y la unique la rechazaría.
                Correlativo::query()->insertOrIgnore([
                    'serie' => $serie,
                    'anio' => $anio,
                    'ultimo_numero' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $correlativo = Correlativo::query()
                    ->where('serie', $serie)
                    ->where('anio', $anio)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $correlativo->increment('ultimo_numero');

            return $correlativo->ultimo_numero;
        });
    }

    /**
     * Número que se entregaría a continuación, sin consumirlo. Solo para vistas
     * previas: no reserva nada y puede quedar obsoleto de inmediato.
     */
    public function proximoPreview(string $serie, ?int $anio = null): string
    {
        $anio ??= (int) now()->format('Y');

        $ultimo = (int) Correlativo::query()
            ->where('serie', $serie)
            ->where('anio', $anio)
            ->value('ultimo_numero');

        return $this->formatear($serie, $anio, $ultimo + 1);
    }

    public function formatear(string $serie, int $anio, int $numero): string
    {
        return sprintf('%s-%d-%s', $serie, $anio, str_pad((string) $numero, self::RELLENO, '0', STR_PAD_LEFT));
    }
}

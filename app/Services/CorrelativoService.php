<?php

namespace App\Services;

use App\Models\Correlativo;
use Illuminate\Support\Facades\DB;

/**
 * Entrega números correlativos, bloqueando la fila del contador.
 *
 * Dos usos, y son distintos: `siguienteContinuo()` para lo que se IMPRIME en un
 * papel —recibos y permisos de faena, que no reinician nunca— y
 * `siguienteNumero()` con gestión para lo que sí se cuenta por año, como el
 * número de registro del carnet.
 */
class CorrelativoService
{
    /** El «año» de las series que no reinician. Ver siguienteContinuo(). */
    public const SIN_GESTION = 0;

    /** Los ceros de una serie continua: «000001». */
    public const RELLENO_CONTINUO = 6;

    /**
     * Reserva el siguiente número de la serie y lo devuelve CRUDO.
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
     * Reserva el siguiente número de una serie CONTINUA: no reinicia por año.
     *
     * Es lo que hace un talonario de papel —002190 no volvió a 1 en enero— y
     * por eso se guarda bajo el año 0, que ninguna gestión real ocupa.
     */
    public function siguienteContinuo(string $serie): int
    {
        return $this->siguienteNumero($serie, self::SIN_GESTION);
    }

    /** Un número de serie continua con sus ceros: 42 → «000042». */
    public static function rellenar(int|string $numero): string
    {
        return str_pad((string) $numero, self::RELLENO_CONTINUO, '0', STR_PAD_LEFT);
    }
}

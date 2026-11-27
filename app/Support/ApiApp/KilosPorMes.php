<?php

namespace App\Support\ApiApp;

use App\Enums\TipoActor;
use App\Models\Beneficiario;
use App\Models\GuiaMovimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * «Kilos por mes» del inicio de la app (27/11/2026, pedido del responsable): un solo
 * gráfico para los dos rubros.
 *
 * - **Vendido** (pescador): los kilos de las guías pedidas con sus autorizaciones.
 * - **Transportado** (comercializador): los kilos de sus propias guías.
 *
 * Por mes de la gestión, según el día en que se pidió la guía. Cada mes separa lo
 * **confirmado** de lo **por confirmar** (guía sin pagar: sus kilos pueden volver).
 * Como «Mis ventas» y el panel, cuenta toda guía que no se eliminó (las revocadas
 * también: sus kilos siguen descontados). Sin estados ni nombres: solo kilos.
 */
class KilosPorMes
{
    /** @return array<string, mixed> */
    public static function de(Beneficiario $beneficiario, int $gestion): array
    {
        $rubros = collect();

        // Pescador: alguna vez tuvo una Autorización de Pesca.
        if ($beneficiario->aprovechamientos()->exists()) {
            $rubros->push(self::rubro(
                'vendido',
                'Vendido',
                GuiaMovimiento::query()->whereIn(
                    'aprovechamiento_id',
                    $beneficiario->aprovechamientos()->select('id'),
                ),
                $gestion,
            ));
        }

        // Comercializador: alguna vez tuvo un carnet de comercializador.
        if ($beneficiario->carnets()->where('tipo_actor', TipoActor::Comercializador)->exists()) {
            $rubros->push(self::rubro('transportado', 'Transportado', $beneficiario->guias(), $gestion));
        }

        return [
            'gestion' => $gestion,
            // Para que la app arranque en el mes de hoy (o en diciembre si mira otro año).
            'mes_actual' => $gestion === now()->year ? now()->month : 12,
            // Primero el de más movimiento: con los dos rubros, la app arranca en ese.
            'rubros' => $rubros->sortByDesc('total_kg')->values()->all(),
        ];
    }

    /**
     * @param  Builder<GuiaMovimiento>|Relation<GuiaMovimiento, *, *>  $guias
     * @return array<string, mixed>
     */
    private static function rubro(string $clave, string $etiqueta, Builder|Relation $guias, int $gestion): array
    {
        /** @var Collection<int, GuiaMovimiento> $delAnio */
        $delAnio = $guias
            ->whereYear('guias_movimiento.fecha_solicitud', $gestion)
            ->get(['guias_movimiento.fecha_solicitud', 'guias_movimiento.peso_total_kg', 'guias_movimiento.estado']);

        $meses = array_fill(0, 12, ['confirmado' => 0.0, 'por_confirmar' => 0.0]);
        foreach ($delAnio as $g) {
            $mes = $g->fecha_solicitud->month - 1;
            $tipo = $g->estado->estaAbierto() ? 'por_confirmar' : 'confirmado';
            $meses[$mes][$tipo] = round($meses[$mes][$tipo] + (float) $g->peso_total_kg, 2);
        }

        return [
            'clave' => $clave,
            'etiqueta' => $etiqueta,
            'total_kg' => round((float) $delAnio->sum('peso_total_kg'), 2),
            'por_confirmar_kg' => round((float) $delAnio->filter(fn (GuiaMovimiento $g): bool => $g->estado->estaAbierto())->sum('peso_total_kg'), 2),
            'guias' => $delAnio->count(),
            'meses' => $meses,
        ];
    }
}

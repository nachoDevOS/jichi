<?php

namespace App\Support\ApiApp;

use App\Models\AprovechamientoPesq;
use App\Models\GuiaDetalle;
use App\Models\GuiaMovimiento;
use Illuminate\Support\Collection;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * «Mis ventas» de una autorización (27/11/2026, pedido del responsable): lo mismo que
 * la ficha del panel (Panel\AprovechamientoController::show), compacto. Las guías que
 * le compraron pescado: las pendientes **reservan** sus kilos (ya descontados, por
 * confirmar), las demás ya se **vendieron**. Con el comprador, sus productos y kilos.
 */
class VentasDelCupo
{
    /** @return array<string, mixed> */
    public static function de(AprovechamientoPesq $cupo): array
    {
        // Como el panel: todas sus guías, la más nueva primero.
        $guias = $cupo->guias()
            ->with(['carnet.beneficiario', 'detalles'])
            ->orderByDesc('nro')
            ->get();
        [$reservadas, $vendidas] = $guias->partition(fn (GuiaMovimiento $g): bool => $g->estado->estaAbierto());

        return [
            'numero' => $cupo->numeroLegible(),
            'evolucion' => self::evolucion($cupo, $guias),
            'total_kg' => (float) $cupo->volumen_total_kg,
            'vendidos_kg' => (float) $vendidas->sum('peso_total_kg'),
            'reservados_kg' => (float) $reservadas->sum('peso_total_kg'),
            'disponibles_kg' => (float) $cupo->kilos_disponibles,
            'guias' => $guias->map(fn (GuiaMovimiento $g): array => [
                'numero' => $g->numero_legible,
                'fecha' => $g->fecha_solicitud?->toDateString(),
                // Como el panel, el nombre del comercializador (decisión del responsable).
                'comprador' => $g->comercializador()?->nombreCompleto,
                'kilos' => (float) $g->peso_total_kg,
                // Solo si está por confirmar (sus kilos pueden volver). Revocada o sin
                // efecto es asunto del comercializador, no del pescador: no viaja (27/11/2026).
                'por_confirmar' => $g->estado->estaAbierto(),
                'productos' => $g->detalles->map(fn (GuiaDetalle $d): array => [
                    'nombre' => $d->especie,
                    'condicion' => $d->condicion?->etiqueta(),
                    'kilos' => (float) $d->cantidad_kg,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Cómo fue bajando lo libre (27/11/2026), como el gráfico del panel: arranca con lo
     * otorgado, cada guía resta su carga al registrarse y termina en «Hoy». Sin estado:
     * solo si la guía está por confirmar (sus kilos pueden volver).
     *
     * @param  Collection<int, GuiaMovimiento>  $guias
     * @return list<array<string, mixed>>
     */
    private static function evolucion(AprovechamientoPesq $cupo, Collection $guias): array
    {
        $disponible = (float) $cupo->volumen_total_kg;

        return collect([[
            'etiqueta' => 'Otorgada',
            'fecha' => ($cupo->fecha_emision ?? $cupo->fecha_solicitud)?->toDateString(),
            'disponible' => $disponible,
            'kilos' => null,
            'comprador' => null,
            'por_confirmar' => false,
        ]])->concat($guias->sortBy([['created_at', 'asc'], ['nro', 'asc']])->map(function (GuiaMovimiento $g) use (&$disponible): array {
            $disponible -= (float) $g->peso_total_kg;

            return [
                'etiqueta' => 'Guía '.$g->numero_legible,
                'fecha' => $g->created_at?->toDateString(),
                'disponible' => round($disponible, 2),
                'kilos' => (float) $g->peso_total_kg,
                'comprador' => $g->comercializador()?->nombreCompleto,
                'por_confirmar' => $g->estado->estaAbierto(),
            ];
        }))->push([
            'etiqueta' => 'Hoy',
            'fecha' => now()->toDateString(),
            'disponible' => round($disponible, 2),
            'kilos' => null,
            'comprador' => null,
            'por_confirmar' => false,
        ])->values()->all();
    }
}

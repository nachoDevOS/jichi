<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoDocumento;
use App\Enums\EstadoTramite;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Configuracion;
use App\Models\Documento;
use App\Models\Pago;
use App\Models\Tramite;
use App\Support\Sql;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $hoy = now()->toDateString();
        $diasAlerta = (int) Configuracion::obtener('documentos.dias_alerta_vencimiento', 30);

        return Inertia::render('panel/dashboard', [
            'resumen' => fn (): array => $this->resumenDelDia($hoy),
            'porArea' => fn (): array => $this->recaudacionPorArea(),
            'porMes' => fn (): array => $this->recaudacionMensual(),
            'porTipoDocumento' => fn (): array => $this->documentosPorTipo(),
            'alertas' => fn (): array => $this->alertas($diasAlerta),
            'ultimosTramites' => fn (): array => $this->ultimosTramites(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenDelDia(string $hoy): array
    {
        return [
            'fecha' => $hoy,
            'tramites_recibidos' => Tramite::delDia($hoy)->count(),
            'tramites_pendientes' => Tramite::pendientes()->count(),
            'documentos_emitidos' => Documento::whereDate('fecha_emision', $hoy)->count(),
            'recaudado_hoy' => (float) Pago::vigentes()->delDia($hoy)->sum('monto'),
            'recaudado_mes' => (float) Pago::vigentes()
                ->whereBetween('fecha_pago', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('monto'),
            'solicitantes_atendidos' => Tramite::delDia($hoy)->distinct('solicitante_id')->count('solicitante_id'),
        ];
    }

    /**
     * Recaudación del mes en curso agrupada por área departamental.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recaudacionPorArea(): array
    {
        $porArea = Pago::query()
            ->vigentes()
            ->whereBetween('fecha_pago', [now()->startOfMonth(), now()->endOfMonth()])
            ->join('tramites', 'tramites.id', '=', 'pagos.tramite_id')
            ->join('tipos_tramite', 'tipos_tramite.id', '=', 'tramites.tipo_tramite_id')
            ->groupBy('tipos_tramite.area_id')
            ->select(
                'tipos_tramite.area_id',
                DB::raw('SUM(pagos.monto) as total'),
                DB::raw('COUNT(*) as cantidad'),
            )
            ->get()
            ->keyBy('area_id');

        return Area::activas()->ordenadas()->get()
            ->map(fn (Area $area): array => [
                'area' => $area->nombre,
                'icono' => $area->icono,
                'color' => $area->color,
                'total' => (float) ($porArea[$area->id]->total ?? 0),
                'cantidad' => (int) ($porArea[$area->id]->cantidad ?? 0),
            ])
            ->all();
    }

    /**
     * Recaudación de los últimos 12 meses.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recaudacionMensual(): array
    {
        $desde = now()->startOfMonth()->subMonths(11);

        $periodo = Sql::periodoMes('fecha_pago');

        $totales = Pago::query()
            ->vigentes()
            ->where('fecha_pago', '>=', $desde)
            ->groupBy($periodo)
            ->select(
                DB::raw($periodo->getValue(DB::connection()->getQueryGrammar()).' as periodo'),
                DB::raw('SUM(monto) as total'),
            )
            ->pluck('total', 'periodo');

        return collect(range(0, 11))
            ->map(function (int $i) use ($desde, $totales): array {
                $mes = $desde->copy()->addMonths($i);

                return [
                    'periodo' => $mes->format('Y-m'),
                    'etiqueta' => ucfirst($mes->locale('es')->isoFormat('MMM YY')),
                    'total' => (float) ($totales[$mes->format('Y-m')] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documentosPorTipo(): array
    {
        return Documento::query()
            ->where('fecha_emision', '>=', now()->startOfYear())
            ->groupBy('tipo')
            ->select('tipo', DB::raw('COUNT(*) as cantidad'))
            ->get()
            ->map(fn ($fila): array => [
                'tipo' => $fila->tipo->value,
                'etiqueta' => $fila->tipo->etiqueta(),
                'cantidad' => (int) $fila->cantidad,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function alertas(int $diasAlerta): array
    {
        return [
            'dias_alerta' => $diasAlerta,
            'documentos_por_vencer' => Documento::porVencer($diasAlerta)
                ->with('tramite.solicitante:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasada', 'tramite.tipoTramite:id,nombre')
                ->orderBy('fecha_vencimiento')
                ->limit(10)
                ->get()
                ->map(fn (Documento $doc): array => [
                    'id' => $doc->id,
                    // El documento se identifica por su código de verificación:
                    // no hay otro número. Es el que el ciudadano tiene impreso.
                    'codigo_verificacion' => $doc->codigo_verificacion,
                    'solicitante' => $doc->tramite->solicitante->nombreCompleto,
                    'tipo' => $doc->tramite->tipoTramite->nombre,
                    'fecha_vencimiento' => $doc->fecha_vencimiento?->toDateString(),
                    'dias_para_vencer' => $doc->dias_para_vencer,
                ])
                ->all(),
            'documentos_vencidos' => Documento::where('estado', EstadoDocumento::Vencido)->count(),
            'tramites_sin_pago' => Tramite::pendientes()
                ->whereColumn('monto_pagado', '<', 'monto_total')
                ->count(),
            'pendientes_aprobacion' => Tramite::enEstado(EstadoTramite::EnRevision)->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ultimosTramites(): array
    {
        return Tramite::query()
            ->with([
                'solicitante:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasada,ci_nit',
                'tipoTramite:id,nombre,area_id',
                'tipoTramite.area:id,nombre,icono',
                'operador:id,name',
            ])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Tramite $tramite): array => [
                'id' => $tramite->id,
                'numero' => $tramite->id,
                'solicitante' => $tramite->solicitante->nombreCompleto,
                'ci_nit' => $tramite->solicitante->ci_nit,
                'tipo' => $tramite->tipoTramite->nombre,
                'area' => $tramite->tipoTramite->area->nombre,
                'icono' => $tramite->tipoTramite->area->icono,
                'estado' => $tramite->estado->value,
                'estado_etiqueta' => $tramite->estado->etiqueta(),
                'estado_color' => $tramite->estado->color(),
                'monto_total' => (float) $tramite->monto_total,
                'creado' => $tramite->created_at?->toIso8601String(),
            ])
            ->all();
    }
}

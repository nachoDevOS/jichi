<?php

namespace App\Http\Controllers\Portal;

use App\Enums\EstadoValidacionPago;
use App\Http\Controllers\Controller;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\Pago;
use App\Models\PermisoFaena;
use App\Models\Recibo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sus depósitos y sus recibos, por gestión: el informe de aportes que el Art. 13
 * del reglamento del SEDAG le reconoce. Agrupa las boletas por trámite, con el
 * estado de su control y el comprobante que subió; no quién la validó.
 */
class PagosController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $beneficiarioId = $request->user()->beneficiario_id;

        $gestiones = $this->depositos($beneficiarioId)
            ->pluck('fecha_deposito')
            ->merge(Recibo::query()->where('beneficiario_id', $beneficiarioId)->pluck('created_at'))
            ->filter()
            ->map(fn ($fecha): int => (int) $fecha->format('Y'))
            ->push((int) now()->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();

        // La gestión llega por la URL: solo una que exista, o la actual.
        $gestion = (int) $request->query('gestion', now()->format('Y'));
        $gestion = $gestiones->contains($gestion) ? $gestion : (int) now()->format('Y');

        $depositos = $this->depositos($beneficiarioId)
            ->whereYear('fecha_deposito', $gestion)
            ->with(['pagable', 'recibo:id,numero_recibo', 'recibo.codigo'])
            ->orderByDesc('fecha_deposito')
            ->orderByDesc('id')
            ->get();

        $recibos = Recibo::query()
            ->where('beneficiario_id', $beneficiarioId)
            ->whereYear('created_at', $gestion)
            ->with('codigo')
            ->withCount('pagos')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('portal/pagos', [
            'gestion' => $gestion,
            'gestiones' => $gestiones->all(),
            // El dinero observado igual entró: lo que está en duda es el dato.
            'total_pagado' => (float) $depositos->sum('monto_parcial'),
            'observados' => $depositos->where('estado_validacion', EstadoValidacionPago::Observado)->count(),
            // Uno por TRÁMITE: una misma autorización puede pagarse con varias boletas.
            'tramites' => $depositos
                ->groupBy(fn (Pago $p): string => $p->pagable_type.'-'.$p->pagable_id)
                ->map(fn ($grupo): array => $this->tramite($grupo))
                // Los que tienen algo observado primero: son los que piden que haga algo.
                ->sortBy(fn (array $t): int => $t['control'] === EstadoValidacionPago::Observado->value ? 0 : 1)
                ->values()
                ->all(),
            'recibos' => $recibos->map(fn (Recibo $r): array => [
                'numero' => $r->numero_recibo,
                'concepto' => $r->concepto,
                'monto_total' => (float) $r->monto_total,
                'depositos' => $r->pagos_count,
                'emitido_en' => $r->created_at?->toIso8601String(),
                'descargar' => $this->urlRecibo($r),
            ])->all(),
        ]);
    }

    /**
     * Un trámite y sus boletas. El estado general es el peor de ellas: con una
     * observada, observado; con una sin controlar, en control; si no, validado.
     *
     * @param  Collection<int, Pago>  $grupo
     * @return array<string, mixed>
     */
    private function tramite($grupo): array
    {
        $control = match (true) {
            $grupo->contains('estado_validacion', EstadoValidacionPago::Observado) => EstadoValidacionPago::Observado,
            $grupo->contains('estado_validacion', EstadoValidacionPago::Pendiente) => EstadoValidacionPago::Pendiente,
            default => EstadoValidacionPago::Validado,
        };

        return [
            'concepto' => $this->concepto($grupo->first()->pagable),
            'total' => (float) $grupo->sum('monto_parcial'),
            'recibos' => $grupo->pluck('recibo')->filter()->unique('id')
                ->map(fn (Recibo $r): array => ['numero' => $r->numero_recibo, 'descargar' => $this->urlRecibo($r)])
                ->values()->all(),
            'control' => $control->value,
            'control_etiqueta' => $this->etiquetaControl($control),
            'control_color' => $control->color(),
            'depositos' => $grupo->map(fn (Pago $p): array => [
                'nro_transaccion' => $p->nro_transaccion,
                // Un DÍA, el de la boleta: no un instante (ver «Trampas» en CLAUDE.md).
                'fecha_deposito' => $p->fecha_deposito?->toDateString(),
                'monto' => (float) $p->monto_parcial,
                'control_etiqueta' => $this->etiquetaControl($p->estado_validacion),
                'control_color' => $p->estado_validacion->color(),
                'observacion' => $p->estado_validacion === EstadoValidacionPago::Observado ? $p->observacion : null,
                // La boleta que subió: es suya, y la ve solo él.
                'comprobante' => $p->comprobante_url,
            ])->values()->all(),
        ];
    }

    private function urlRecibo(Recibo $recibo): ?string
    {
        $codigo = $recibo->codigo?->codigo;

        return $codigo === null ? null : route('portal.recibos.descargar', ['codigo' => $codigo], false);
    }

    /** «En control» y no «Sin validar»: para el titular es algo que está pasando. */
    private function etiquetaControl(EstadoValidacionPago $control): string
    {
        return $control === EstadoValidacionPago::Pendiente ? 'En control' : $control->etiqueta();
    }

    /**
     * Los depósitos de la persona. `pagos` es polimórfica: la autorización y el
     * carnet cuelgan de ella; la faena y la guía, de su carnet.
     *
     * @return Builder<Pago>
     */
    private function depositos(int $beneficiarioId): Builder
    {
        return Pago::query()->where(fn (Builder $q) => $q
            ->whereHasMorph(
                'pagable',
                [AprovechamientoPesq::class, Carnet::class],
                fn (Builder $d) => $d->where('beneficiario_id', $beneficiarioId),
            )
            ->orWhereHasMorph(
                'pagable',
                [PermisoFaena::class, GuiaMovimiento::class],
                fn (Builder $d) => $d->whereHas('carnet', fn (Builder $c) => $c->where('beneficiario_id', $beneficiarioId)),
            ));
    }

    private function concepto(?Model $pagable): string
    {
        return match (true) {
            $pagable instanceof AprovechamientoPesq => 'Autorización de Pesca',
            $pagable instanceof Carnet => 'Carnet de '.mb_strtolower($pagable->tipo_actor->etiqueta()),
            $pagable instanceof PermisoFaena => 'Permiso de Faena',
            $pagable instanceof GuiaMovimiento => 'Guía de Transporte',
            default => 'Trámite',
        };
    }
}

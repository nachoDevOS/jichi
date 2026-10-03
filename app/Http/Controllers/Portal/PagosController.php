<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Recibo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sus recibos por gestión: el informe de aportes que el Art. 13 del reglamento
 * del SEDAG le reconoce. El pago se hace en SIREB; cada recibo copia su boleta.
 */
class PagosController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $beneficiarioId = $request->user()->beneficiario_id;

        $gestiones = Recibo::query()->where('beneficiario_id', $beneficiarioId)->pluck('created_at')
            ->map(fn ($fecha): int => (int) $fecha->format('Y'))
            ->push((int) now()->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();

        // La gestión llega por la URL: solo una que exista, o la actual.
        $gestion = (int) $request->query('gestion', now()->format('Y'));
        $gestion = $gestiones->contains($gestion) ? $gestion : (int) now()->format('Y');

        $recibos = Recibo::query()
            ->where('beneficiario_id', $beneficiarioId)
            ->whereYear('created_at', $gestion)
            ->with('codigo')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('portal/pagos', [
            'gestion' => $gestion,
            'gestiones' => $gestiones->all(),
            'total_pagado' => (float) $recibos->sum('monto_total'),
            'recibos' => $recibos->map(fn (Recibo $r): array => [
                'numero' => $r->numero_recibo,
                'concepto' => $r->concepto,
                'monto_total' => (float) $r->monto_total,
                'numero_boleta' => $r->numero_boleta,
                'entidad_bancaria' => $r->entidad_bancaria,
                // Un DÍA, el de la boleta: no un instante (ver «Trampas» en CLAUDE.md).
                'fecha_pago' => $r->fecha_pago?->toDateString(),
                'emitido_en' => $r->created_at?->toIso8601String(),
                'descargar' => $r->codigo?->codigo === null
                    ? null
                    : route('portal.recibos.descargar', ['codigo' => $r->codigo->codigo], false),
            ])->all(),
        ]);
    }
}

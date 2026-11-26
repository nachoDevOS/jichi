<?php

namespace App\Support\ApiApp;

use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\Recibo;
use App\Support\ExpedienteBeneficiario;
use App\Support\QrVerificacion;
use App\Support\TramitesDisponibles;
use Illuminate\Support\Collection;

/**
 * ApiApp · COMPARTIDO con el portal web: cambiarlo cambia la app y la web (docs/api/README.md § 4).
 *
 * Lo que muestran Inicio, En curso, Mis papeles y Pagos del portal /mi-cuenta.
 * Lo usan las pantallas web (Portal\*Controller) y la app BeniDigital
 * (ApiApp\InicioController, EnCursoController y PagosController): las dos ven exactamente lo mismo.
 */
class PantallaPortal
{
    /**
     * Lo que vale hoy, lo abierto («Qué hacer ahora»), lo que puede pedir y la deuda.
     *
     * @return array{nombre: string, vigentes: list<array<string, mixed>>, tramites_disponibles: list<array<string, mixed>>, abiertos: list<array<string, mixed>>, deuda: float}
     */
    public static function inicio(Beneficiario $beneficiario): array
    {
        $aprovechamientos = ExpedienteBeneficiario::aprovechamientos($beneficiario)->load('codigo');
        $carnets = ExpedienteBeneficiario::carnets($beneficiario);
        $faenas = ExpedienteBeneficiario::faenas($beneficiario)->load('codigo');
        $guias = ExpedienteBeneficiario::guias($beneficiario)->load('codigo');

        $abierto = fn ($d): bool => $d->estado->estaAbierto();
        $vigentes = collect()
            ->merge($aprovechamientos->filter->estaVigente()->map(ResumenPortal::aprovechamiento(...)))
            ->merge($carnets->filter->estaVigente()->map(ResumenPortal::carnet(...)))
            ->merge($faenas->filter->estaVigente()->map(ResumenPortal::faena(...)))
            ->merge($guias->filter->estaVigente()->map(ResumenPortal::guia(...)));

        return [
            'nombre' => $beneficiario->nombreCompleto,
            // Lo que vence antes, primero: es lo que hay que renovar.
            'vigentes' => $vigentes->sortBy('vence_el')->values()->all(),
            'tramites_disponibles' => TramitesDisponibles::para($beneficiario),
            // Lo abierto, para «Qué hacer ahora»: el detalle completo sigue en «En curso».
            'abiertos' => collect()
                ->merge($aprovechamientos->filter($abierto)->map(ResumenPortal::aprovechamiento(...)))
                ->merge($carnets->filter($abierto)->map(ResumenPortal::carnet(...)))
                ->merge($faenas->filter($abierto)->map(ResumenPortal::faena(...)))
                ->merge($guias->filter($abierto)->map(ResumenPortal::guia(...)))
                ->values()->all(),
            'deuda' => $beneficiario->deudaTotal(),
        ];
    }

    /**
     * Los trámites abiertos: pendientes de pago en SIREB, cada uno con su código.
     *
     * @return list<array<string, mixed>>
     */
    public static function enCurso(Beneficiario $beneficiario): array
    {
        return ResumenPortal::abiertos($beneficiario)->all();
    }

    /**
     * Todo lo que alguna vez se aprobó, de todas las gestiones: vigente, vencido o
     * revocado (`situacion`). Lo abierto va en «En curso».
     *
     * @return list<array<string, mixed>>
     */
    public static function papeles(Beneficiario $beneficiario): array
    {
        return collect()
            ->merge(ExpedienteBeneficiario::aprovechamientos($beneficiario)->load('codigo')->map(ResumenPortal::aprovechamiento(...)))
            ->merge(ExpedienteBeneficiario::carnets($beneficiario)->map(ResumenPortal::carnet(...)))
            ->merge(ExpedienteBeneficiario::faenas($beneficiario)->load('codigo')->map(ResumenPortal::faena(...)))
            ->merge(ExpedienteBeneficiario::guias($beneficiario)->load('codigo')->map(ResumenPortal::guia(...)))
            ->whereNotNull('situacion')
            // Lo que vence (o venció) más tarde, primero: es lo más reciente.
            ->sortByDesc('vence_el')
            ->values()
            ->all();
    }

    /**
     * Sus recibos de una gestión: el informe de aportes del Art. 13 del reglamento.
     * Solo una gestión que exista, o la actual.
     *
     * @return array{gestion: int, gestiones: list<int>, total_pagado: float, recibos: list<array<string, mixed>>}
     */
    public static function pagos(Beneficiario $beneficiario, ?int $pedida): array
    {
        $gestiones = self::gestiones($beneficiario->id);
        $gestion = $pedida !== null && $gestiones->contains($pedida) ? $pedida : (int) now()->format('Y');

        $recibos = Recibo::query()
            ->where('beneficiario_id', $beneficiario->id)
            ->whereYear('created_at', $gestion)
            ->with('codigo')
            ->orderByDesc('id')
            ->get();

        return [
            'gestion' => $gestion,
            'gestiones' => $gestiones->all(),
            'total_pagado' => (float) $recibos->sum('monto_total'),
            'recibos' => $recibos->map(fn (Recibo $r): array => [
                'numero' => $r->numero_recibo,
                // De qué trámite es, con los nombres de ResumenPortal: la app elige el ícono.
                'clase' => self::claseDe($r),
                'concepto' => $r->concepto,
                'monto_total' => (float) $r->monto_total,
                'numero_boleta' => $r->numero_boleta,
                'entidad_bancaria' => $r->entidad_bancaria,
                // Un DÍA, el de la boleta: no un instante (ver «Trampas» en CLAUDE.md).
                'fecha_pago' => $r->fecha_pago?->toDateString(),
                'emitido_en' => $r->created_at?->toIso8601String(),
                // El código y la dirección de su QR de verificación: los mismos del PDF.
                'codigo' => $r->codigo_legible,
                'verificar' => $r->codigo?->codigo === null ? null : QrVerificacion::url($r->codigo->codigo),
                'descargar' => $r->codigo?->codigo === null
                    ? null
                    : route('portal.recibos.descargar', ['codigo' => $r->codigo->codigo], false),
            ])->all(),
        ];
    }

    /** De qué trámite es el recibo; null si es de algo que la app no conoce. */
    private static function claseDe(Recibo $recibo): ?string
    {
        return match ($recibo->recibible_type) {
            (new AprovechamientoPesq)->getMorphClass() => 'aprovechamiento',
            (new Carnet)->getMorphClass() => 'carnet',
            (new PermisoFaena)->getMorphClass() => 'faena',
            (new GuiaMovimiento)->getMorphClass() => 'guia',
            default => null,
        };
    }

    /** @return Collection<int, int> Las gestiones con recibos, y la actual; la más nueva primero. */
    private static function gestiones(int $beneficiarioId): Collection
    {
        return Recibo::query()->where('beneficiario_id', $beneficiarioId)->pluck('created_at')
            ->map(fn ($fecha): int => (int) $fecha->format('Y'))
            ->push((int) now()->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();
    }
}

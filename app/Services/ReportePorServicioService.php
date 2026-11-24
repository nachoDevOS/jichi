<?php

namespace App\Services;

use App\Enums\ConceptoArancel;
use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Enums\EstadoFaena;
use App\Enums\EstadoGuia;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\Recibo;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * El reporte detallado de UN tipo de servicio (autorización, carnet, faena o guía) en un rango de
 * fechas: cada trámite con su monto, su estado y su recibo, y los totales. La fecha es la de
 * solicitud. Ver docs/modulos/REPORTES.md.
 */
class ReportePorServicioService
{
    /** @return class-string<Model> */
    public function modelo(ConceptoArancel $servicio): string
    {
        return match ($servicio) {
            ConceptoArancel::Autorizacion => AprovechamientoPesq::class,
            ConceptoArancel::Carnet => Carnet::class,
            ConceptoArancel::Faena => PermisoFaena::class,
            ConceptoArancel::Guia => GuiaMovimiento::class,
        };
    }

    /** @return class-string<BackedEnum> */
    public function enumEstado(ConceptoArancel $servicio): string
    {
        return match ($servicio) {
            ConceptoArancel::Autorizacion => EstadoAprovechamiento::class,
            ConceptoArancel::Carnet => EstadoCarnet::class,
            ConceptoArancel::Faena => EstadoFaena::class,
            ConceptoArancel::Guia => EstadoGuia::class,
        };
    }

    /** El rango pedido, de 00:00 a 23:59. Sin fechas, el mes en curso; invertido, se da vuelta. */
    public function periodo(?string $desde, ?string $hasta): array
    {
        $inicio = $desde ? Carbon::parse($desde)->startOfDay() : now()->startOfMonth();
        $fin = $hasta ? Carbon::parse($hasta)->endOfDay() : now()->endOfDay();

        return $inicio->lte($fin) ? [$inicio, $fin] : [$fin->copy()->startOfDay(), $inicio->copy()->endOfDay()];
    }

    /** Los trámites del servicio solicitados en el rango, del más viejo al más nuevo. */
    public function consulta(ConceptoArancel $servicio, Carbon $desde, Carbon $hasta, ?string $estado = null): Builder
    {
        $consulta = $this->modelo($servicio)::query();

        return $consulta
            ->whereBetween($consulta->qualifyColumn('fecha_solicitud'), [$desde->toDateString(), $hasta->toDateString()])
            ->when($estado, fn ($q, $e) => $q->where($q->qualifyColumn('estado'), $e))
            // Lo que leen fila() y los accesores del nombre y la cédula: si falta, N+1 o un dato en blanco.
            ->with(match ($servicio) {
                ConceptoArancel::Autorizacion => ['beneficiario', 'recibo'],
                ConceptoArancel::Carnet => ['beneficiario', 'asociacion', 'recibo'],
                ConceptoArancel::Faena, ConceptoArancel::Guia => ['carnet.beneficiario', 'recibo'],
            })
            ->orderBy($consulta->qualifyColumn('fecha_solicitud'))
            ->orderBy($consulta->qualifyColumn('id'));
    }

    /** Cuántos, cuánto se liquidó, cuánto se cobró (recibos) y cuánto falta (pendientes). */
    public function totales(ConceptoArancel $servicio, Builder $consulta): array
    {
        $base = (clone $consulta)->reorder()->setEagerLoads([]);
        $fila = (clone $base)->toBase()->selectRaw('COUNT(*) as cantidad, SUM('.$base->qualifyColumn('monto').') as monto')->first();
        $pendiente = match ($servicio) {
            ConceptoArancel::Autorizacion => EstadoAprovechamiento::Pendiente,
            ConceptoArancel::Carnet => EstadoCarnet::Pendiente,
            ConceptoArancel::Faena => EstadoFaena::Pendiente,
            ConceptoArancel::Guia => EstadoGuia::Pendiente,
        };

        return [
            'cantidad' => (int) ($fila->cantidad ?? 0),
            'monto' => (float) ($fila->monto ?? 0),
            'cobrado' => (float) Recibo::query()
                ->where('recibible_type', (new ($this->modelo($servicio)))->getMorphClass())
                ->whereIn('recibible_id', (clone $base)->select($base->qualifyColumn('id')))
                ->sum('monto_total'),
            'por_cobrar' => (float) (clone $base)->where($base->qualifyColumn('estado'), $pendiente->value)->sum($base->qualifyColumn('monto')),
        ];
    }

    /** @return list<array{clave: string, etiqueta: string, formato: string}> */
    public function columnas(ConceptoArancel $servicio): array
    {
        $propias = match ($servicio) {
            ConceptoArancel::Autorizacion => [['escala', 'Escala', 'texto'], ['kilos', 'Kg autorizados', 'kg']],
            ConceptoArancel::Carnet => [['actividad', 'Actividad', 'texto'], ['asociacion', 'Asociación', 'texto']],
            ConceptoArancel::Faena => [['embarcacion', 'Embarcación', 'texto'], ['kilos', 'Kg', 'kg']],
            ConceptoArancel::Guia => [['ruta', 'Origen → destino', 'texto'], ['kilos', 'Kg', 'kg']],
        };

        $columnas = [
            ['fecha', 'Fecha', 'fecha'], ['numero', 'N°', 'texto'],
            ['titular', $servicio === ConceptoArancel::Guia ? 'Comercializador' : 'Titular', 'texto'], ['ci', 'C.I.', 'texto'],
            ...$propias,
            ['monto', 'Monto (Bs)', 'bs'], ['estado', 'Estado', 'texto'], ['recibo', 'N° recibo', 'texto'],
        ];

        return array_map(fn (array $c): array => ['clave' => $c[0], 'etiqueta' => $c[1], 'formato' => $c[2]], $columnas);
    }

    /** Una fila del reporte, por clave de columna. `_url` es la ficha del trámite. */
    public function fila(ConceptoArancel $servicio, Model $doc): array
    {
        $titular = $doc->titularSireb();

        $propias = match ($servicio) {
            ConceptoArancel::Autorizacion => ['escala' => $doc->escala, 'kilos' => (float) $doc->volumen_total_kg],
            ConceptoArancel::Carnet => ['actividad' => $doc->tipo_actor?->etiqueta(), 'asociacion' => $doc->asociacion?->nombre],
            ConceptoArancel::Faena => ['embarcacion' => $doc->embarcacion, 'kilos' => (float) $doc->kilos_extraidos],
            ConceptoArancel::Guia => ['ruta' => ($doc->origen ?? '—').' → '.($doc->destino ?? '—'), 'kilos' => (float) $doc->peso_total_kg],
        };

        return [
            'fecha' => $doc->fecha_solicitud?->toDateString(),
            'numero' => str_pad((string) $doc->nro, 6, '0', STR_PAD_LEFT),
            'titular' => $titular?->nombreCompleto,
            'ci' => $titular?->documento_identidad,
            ...$propias,
            'monto' => (float) $doc->monto,
            'estado' => $doc->estado->etiqueta(),
            'estado_color' => $doc->estado->color(),
            'recibo' => $doc->recibo?->numero_recibo,
            '_url' => route(match ($servicio) {
                ConceptoArancel::Autorizacion => 'aprovechamientos.show',
                ConceptoArancel::Carnet => 'carnets.show',
                ConceptoArancel::Faena => 'faenas.show',
                ConceptoArancel::Guia => 'guias.show',
            }, $doc),
        ];
    }

    /** Las opciones del filtro de estado de ese servicio. */
    public function estados(ConceptoArancel $servicio): array
    {
        return array_map(fn (BackedEnum $e): array => ['valor' => $e->value, 'etiqueta' => $e->etiqueta()], $this->enumEstado($servicio)::cases());
    }
}

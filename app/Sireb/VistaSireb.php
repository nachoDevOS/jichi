<?php

namespace App\Sireb;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que las pantallas de catálogo necesitan de SIREB, ya armado: las opciones
 * del select de tarifa y el historial de un registro. Lo comparten la escala y
 * los tipos de carnet. Ver docs/modulos/SIREB.md.
 */
class VistaSireb
{
    /** El único estado de servicio que SIREB manda como elegible. */
    public const SERVICIO_ACTIVO = 'activo';

    public function __construct(private SirebService $sireb) {}

    /**
     * Los servicios recortados a lo que usa el select; null si SIREB no responde.
     *
     * @return list<array<string, mixed>>|null
     */
    public function serviciosParaSelect(): ?array
    {
        $servicios = $this->sireb->serviciosSiResponde();

        if ($servicios === null) {
            return null;
        }

        return array_map(fn (array $s): array => [
            'id' => $s['id'],
            'codigo' => $s['codigo'] ?? null,
            'nombre' => $s['nombre'] ?? '',
            // SIREB manda también lo dado de baja: solo `activo` se puede elegir.
            'activo' => ($s['estado'] ?? null) === self::SERVICIO_ACTIVO,
            'tarifas' => array_map(fn (array $t): array => [
                'id' => $t['id'],
                'etiqueta' => $t['etiqueta'] ?? '',
                'monto' => (float) ($t['monto'] ?? 0),
            ], $s['tarifas'] ?? []),
        ], $servicios);
    }

    /**
     * Precio de cada tarifa de servicios ACTIVOS, por id; null si SIREB no
     * responde. Para mostrar de referencia: el que vale lo congela la emisión.
     *
     * @return array<string, float>|null
     */
    public function preciosPorTarifa(): ?array
    {
        $servicios = $this->sireb->serviciosSiResponde();

        if ($servicios === null) {
            return null;
        }

        return collect($servicios)
            ->filter(fn (array $s) => ($s['estado'] ?? null) === self::SERVICIO_ACTIVO)
            ->flatMap(fn (array $s) => $s['tarifas'] ?? [])
            ->mapWithKeys(fn (array $t) => [$t['id'] => (float) $t['monto']])
            ->all();
    }

    /**
     * La tarifa actual y las anteriores de un registro con `sireb_historial`,
     * cada una con qué es HOY en SIREB y quién la cambió.
     *
     * @return array{actual: array<string, mixed>, anteriores: list<array<string, mixed>>, sirebDisponible: bool}
     */
    public function historial(Model $modelo): array
    {
        $servicios = $this->sireb->serviciosSiResponde();
        $tarifas = collect($servicios ?? [])->flatMap(fn (array $s) => $s['tarifas'] ?? [])->keyBy('id');
        $historial = $modelo->sireb_historial ?? [];

        $usuarios = User::query()
            ->whereIn('id', array_filter(array_column($historial, 'cambiado_por')))
            ->pluck('name', 'id');

        // Qué es hoy esa tarifa en SIREB; null si ya no está (o SIREB no responde).
        $describir = fn (?string $tarifaId): array => [
            'tarifa_etiqueta' => $tarifas[$tarifaId]['etiqueta'] ?? null,
            'tarifa_monto' => isset($tarifas[$tarifaId]) ? (float) $tarifas[$tarifaId]['monto'] : null,
        ];

        return [
            'actual' => [
                'servicio_sireb' => $modelo->servicio_sireb,
                'tarifa_sireb' => $modelo->tarifa_sireb,
                // Vale desde el último cambio o, si nunca cambió, desde el alta.
                'desde' => end($historial)['hasta'] ?? $modelo->created_at?->toIso8601String(),
                ...$describir($modelo->tarifa_sireb),
            ],
            // Lo más reciente primero, que es lo que se busca al abrir.
            'anteriores' => array_reverse(array_map(fn (array $h): array => [
                'servicio_sireb' => $h['servicio_sireb'] ?? null,
                'tarifa_sireb' => $h['tarifa_sireb'] ?? null,
                'desde' => $h['desde'] ?? null,
                'hasta' => $h['hasta'] ?? null,
                'cambiado_por' => $usuarios[$h['cambiado_por'] ?? 0] ?? null,
                ...$describir($h['tarifa_sireb'] ?? null),
            ], $historial)),
            'sirebDisponible' => $servicios !== null,
        ];
    }
}

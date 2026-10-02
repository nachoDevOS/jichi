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
     */
    public function serviciosParaSelect(): ?array
    {
        $servicios = $this->sireb->serviciosSiResponde();

        if ($servicios === null) {
            return null;
        }

        // Llegan todas las tarifas (`tarifas=todas`): el select ofrece solo las
        // liquidables, y un servicio sin ninguna no aparece.
        return collect($servicios)
            ->map(fn (array $s): array => [
                'id' => $s['id'],
                'codigo' => $s['codigo'] ?? null,
                'nombre' => $s['nombre'] ?? '',
                'activo' => ($s['estado'] ?? null) === self::SERVICIO_ACTIVO,
                'tarifas' => collect($s['tarifas'] ?? [])
                    ->filter(fn (array $t) => ($t['liquidable'] ?? false) === true)
                    ->map(fn (array $t): array => [
                        'id' => $t['id'],
                        'etiqueta' => $t['etiqueta'] ?? '',
                        'monto' => (float) ($t['monto'] ?? 0),
                    ])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $s) => $s['activo'] && $s['tarifas'] !== [])
            ->values()
            ->all();
    }

    /**
     * Precio de cada tarifa de servicios ACTIVOS, por id; null si SIREB no
     * responde. Para mostrar de referencia: el que vale lo congela la emisión.
     */
    public function preciosPorTarifa(): ?array
    {
        $tarifas = $this->tarifasPorId();

        return $tarifas === null ? null : array_map(
            fn (array $t) => $t['monto'],
            array_filter($tarifas, fn (array $t) => $t['liquidable']),
        );
    }

    /**
     * Cada tarifa por id con el nombre de su servicio y su etiqueta; null si
     * SIREB no responde. `liquidable` dice si hoy se puede cobrar.
     *
     * @return array<string, array{servicio: string, etiqueta: string, estado: ?string, monto: float, liquidable: bool}>|null
     */
    public function tarifasPorId(): ?array
    {
        $servicios = $this->sireb->serviciosSiResponde();

        if ($servicios === null) {
            return null;
        }

        return collect($servicios)
            ->flatMap(fn (array $s) => array_map(fn (array $t) => [
                'id' => $t['id'],
                'servicio' => $s['nombre'] ?? '',
                'etiqueta' => $t['etiqueta'] ?? '',
                // `activo` / `inactivo`, tal como lo manda SIREB.
                'estado' => $t['estado'] ?? null,
                'monto' => (float) $t['monto'],
                // Se cobra solo si el servicio está activo y SIREB la da por liquidable.
                'liquidable' => ($s['estado'] ?? null) === self::SERVICIO_ACTIVO && ($t['liquidable'] ?? false) === true,
            ], $s['tarifas'] ?? []))
            ->keyBy('id')
            ->map(fn (array $t) => array_diff_key($t, ['id' => true]))
            ->all();
    }

    /**
     * Lo que muestra un listado de catálogo sobre la tarifa de una fila.
     * Nombres en null si la tarifa ya no está en SIREB o no responde.
     *
     * @param  array<string, array{servicio: string, etiqueta: string, estado: ?string, monto: float, liquidable: bool}>|null  $tarifas  de tarifasPorId()
     * @return array{sireb_servicio: ?string, sireb_etiqueta: ?string, sireb_estado: ?string, sireb_liquidable: ?bool, precio: ?float}
     */
    public static function describir(?array $tarifas, ?string $tarifaId): array
    {
        $tarifa = $tarifaId === null ? null : ($tarifas[$tarifaId] ?? null);

        return [
            'sireb_servicio' => $tarifa['servicio'] ?? null,
            'sireb_etiqueta' => $tarifa['etiqueta'] ?? null,
            'sireb_estado' => $tarifa['estado'] ?? null,
            'sireb_liquidable' => $tarifa['liquidable'] ?? null,
            // De referencia: el que vale lo congela cada documento al emitirse.
            'precio' => $tarifa['monto'] ?? null,
        ];
    }

    /**
     * La tarifa actual y las anteriores de un registro con `sireb_historial`,
     * cada una con qué es HOY en SIREB y quién la cambió.
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

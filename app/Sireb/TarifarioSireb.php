<?php

namespace App\Sireb;

use App\Enums\ConceptoArancel;
use App\Models\ArancelSireb;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Todo lo que Jichi le pregunta a SIREB sobre tarifas, en un lugar: el servicio
 * de cada trámite (Catálogos › Aranceles), sus tarifas para los formularios y el
 * precio que se congela al emitir. Ver docs/modulos/SIREB.md.
 */
class TarifarioSireb
{
    /** El único estado de servicio que SIREB manda como elegible. */
    public const SERVICIO_ACTIVO = 'activo';

    /** Lo mismo para la tarifa: `activo` / `inactivo`. */
    public const TARIFA_ACTIVA = 'activo';

    /** Las tarifas ya pedidas en esta petición: una guía con el mismo producto dos veces pide una. */
    private array $pedidas = [];

    public function __construct(private SirebService $sireb) {}

    //  El servicio de cada trámite

    /** El servicio configurado para el trámite, o null si nadie lo eligió. */
    public function servicioId(ConceptoArancel $concepto): ?string
    {
        return ArancelSireb::de($concepto)?->servicio_sireb;
    }

    //  Para mostrar: de la caché del catálogo, y abren con SIREB caído

    /**
     * Las tarifas del servicio del trámite: etiqueta, precio y si está activa.
     * null si SIREB no responde, [] sin servicio. El precio es de referencia:
     * el que vale lo pide precio(). El rango va tal cual, solo para mostrar.
     */
    public function tarifas(ConceptoArancel $concepto): ?array
    {
        $servicioId = $this->servicioId($concepto);
        $servicios = $this->sireb->serviciosSiResponde();

        if ($servicios === null) {
            return null;
        }

        $servicio = $servicioId === null ? null : collect($servicios)->firstWhere('id', $servicioId);

        return collect($servicio['tarifas'] ?? [])
            ->map(fn (array $t): array => [
                'id' => $t['id'],
                'etiqueta' => $t['etiqueta'] ?? '',
                'monto' => (float) ($t['monto'] ?? 0),
                'activa' => ($t['estado'] ?? null) === self::TARIFA_ACTIVA,
                // Como lo manda SIREB (hoy, null). No se calcula nada con esto.
                'rango_desde' => $t['rango_desde'] ?? null,
                'rango_hasta' => $t['rango_hasta'] ?? null,
            ])
            ->all();
    }

    //  Para cobrar: sin caché, el precio de ahora

    /**
     * El precio de una tarifa del servicio del trámite, para congelarlo al emitir.
     * Sin la tarifa activa no se emite. `tarifa` es la respuesta de SIREB, de
     * donde la autorización copia la etiqueta.
     */
    public function precio(ConceptoArancel $concepto, ?string $tarifaId): array
    {
        $servicioId = $this->servicioId($concepto);

        if ($servicioId === null) {
            throw new SinPrecioException('no tiene servicio de SIREB elegido en Catálogos › Aranceles.');
        }

        if ($tarifaId === null || $tarifaId === '') {
            throw new SinPrecioException('no se eligió la tarifa de SIREB.');
        }

        try {
            $tarifa = $this->pedidas[$servicioId.'|'.$tarifaId] ??= $this->sireb->tarifa($servicioId, $tarifaId);
        } catch (SirebException) {
            $e = new SinPrecioException('Recaudaciones (SIREB) no responde. Intente de nuevo en unos minutos.');
            $e->sinRespuesta = true;

            throw $e;
        }

        // El 404 no distingue si falta el servicio o la tarifa dentro de él.
        if ($tarifa === null) {
            throw new SinPrecioException('su tarifa ya no está en SIREB.');
        }

        // SIREB manda también lo dado de baja: solo se cobra la tarifa activa.
        if (($tarifa['estado'] ?? null) !== self::TARIFA_ACTIVA) {
            throw new SinPrecioException('su tarifa está dada de baja en SIREB.');
        }

        return ['monto' => (float) $tarifa['monto'], 'tarifa_id' => $tarifa['id'], 'tarifa' => $tarifa];
    }

    //  Para Catálogos › Aranceles

    /**
     * Los servicios por id, con cuántas tarifas activas tienen; null si SIREB
     * no responde.
     *
     * @return array<string, array{id: string, codigo: ?string, nombre: string, activo: bool, tarifas: int}>|null
     */
    public function servicios(): ?array
    {
        $servicios = $this->sireb->serviciosSiResponde();

        return $servicios === null ? null : collect($servicios)
            ->mapWithKeys(fn (array $s): array => [$s['id'] => [
                'id' => $s['id'],
                'codigo' => $s['codigo'] ?? null,
                'nombre' => $s['nombre'] ?? '',
                'activo' => ($s['estado'] ?? null) === self::SERVICIO_ACTIVO,
                'tarifas' => collect($s['tarifas'] ?? [])->where('estado', self::TARIFA_ACTIVA)->count(),
            ]])
            ->all();
    }

    /**
     * El servicio actual y los anteriores de una fila con `sireb_historial`,
     * cada uno con su nombre de HOY en SIREB y quién lo cambió.
     */
    public function historial(Model $modelo): array
    {
        $servicios = $this->servicios();
        $historial = $modelo->sireb_historial ?? [];

        $usuarios = User::query()
            ->whereIn('id', array_filter(array_column($historial, 'cambiado_por')))
            ->pluck('name', 'id');

        // null si ya no está en SIREB (o SIREB no responde).
        $nombre = fn (?string $id): ?string => $servicios[$id]['nombre'] ?? null;

        return [
            'actual' => [
                'servicio_sireb' => $modelo->servicio_sireb,
                'servicio_nombre' => $nombre($modelo->servicio_sireb),
                // Vale desde el último cambio o, si nunca cambió, desde el alta.
                'desde' => end($historial)['hasta'] ?? $modelo->created_at?->toIso8601String(),
            ],
            // Lo más reciente primero, que es lo que se busca al abrir.
            'anteriores' => array_reverse(array_map(fn (array $h): array => [
                'servicio_sireb' => $h['servicio_sireb'] ?? null,
                'servicio_nombre' => $nombre($h['servicio_sireb'] ?? null),
                'desde' => $h['desde'] ?? null,
                'hasta' => $h['hasta'] ?? null,
                'cambiado_por' => $usuarios[$h['cambiado_por'] ?? 0] ?? null,
            ], $historial)),
            'sirebDisponible' => $servicios !== null,
        ];
    }
}

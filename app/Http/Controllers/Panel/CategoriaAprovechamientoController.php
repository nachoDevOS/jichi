<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ModalidadAprovechamiento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarCategoriaAprovechamientoRequest;
use App\Models\CategoriaAprovechamiento;
use App\Models\User;
use App\Sireb\SirebService;
use App\Support\Paginacion;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  La escala oficial de aprovechamiento — paso 2 del flujo del pescador
 */
class CategoriaAprovechamientoController extends Controller
{
    /**
     * Listado y formulario — GET /panel/catalogos/categorias-aprovechamiento
     */
    public function index(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->value() ?: null;
        $modalidad = $request->string('modalidad')->trim()->value() ?: null;
        $porPagina = Paginacion::filas($request);

        /*
         * La escala entera, aparte del listado: los huecos y el número que
         * sigue se calculan sobre TODOS los tramos. Sacados de la página que
         * se está viendo, «Nuevo tramo» propondría un número ya usado y los
         * huecos aparecerían y desaparecerían al cambiar de página.
         */
        $todos = CategoriaAprovechamiento::query()->enOrdenDeEscala()->get();

        $escala = CategoriaAprovechamiento::query()
            ->when($buscar, function ($q) use ($buscar) {
                // Se escapan % y _ porque en LIKE son comodines.
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $buscar).'%';

                $q->where('descripcion_kg', Sql::like($q->getConnection()), $like);
            })
            ->when($modalidad, fn ($q, $m) => $q->where('categorias_aprovechamiento.modalidad', $m))
            // Cuántos cupos se otorgaron bajo cada tramo: es lo que explica por
            // qué no se puede borrar, y de paso muestra cuál se usa de verdad.
            ->withCount('aprovechamientos')
            ->enOrdenDeEscala()
            ->paginate($porPagina)
            ->withQueryString()
            ->through(fn (CategoriaAprovechamiento $c): array => [
                'id' => $c->id,
                'nro_escala' => $c->nro_escala,
                'modalidad' => $c->modalidad->value,
                'descripcion_kg' => $c->descripcion_kg,
                'kilos_min' => (float) $c->kilos_min,
                'kilos_max' => (float) $c->kilos_max,
                'servicio_sireb' => $c->servicio_sireb,
                'tarifa_sireb' => $c->tarifa_sireb,
                'estado' => (bool) $c->estado,
                'aprovechamientos_count' => $c->aprovechamientos_count,
            ]);

        return Inertia::render('panel/catalogos/escala', [
            'escala' => $escala,
            'filtros' => ['buscar' => $buscar, 'modalidad' => $modalidad, 'por_pagina' => $porPagina],
            'opcionesPorPagina' => Paginacion::OPCIONES,

            'huecos' => $this->huecos($todos),

            // Las opciones salen del enum y no escritas en React: si estuvieran
            // en los dos lados, agregar una modalidad en la pantalla y olvidarse
            // del servidor dejaría al operador eligiendo un valor que se rechaza.
            'modalidades' => ModalidadAprovechamiento::opciones(),
        ]);
    }

    /**
     * Historial de SIREB de un tramo — GET /panel/catalogos/categorias-aprovechamiento/{categoria}
     */
    public function show(CategoriaAprovechamiento $categoria, SirebService $sireb): Response
    {
        $servicios = $sireb->serviciosSiResponde();
        $tarifas = collect($servicios ?? [])->flatMap(fn (array $s) => $s['tarifas'] ?? [])->keyBy('id');
        $historial = $categoria->sireb_historial ?? [];

        $usuarios = User::query()
            ->whereIn('id', array_filter(array_column($historial, 'cambiado_por')))
            ->pluck('name', 'id');

        // Qué es hoy esa tarifa en SIREB; null si ya no está (o SIREB no responde).
        $describir = fn (?string $tarifaId): array => [
            'tarifa_etiqueta' => $tarifas[$tarifaId]['etiqueta'] ?? null,
            'tarifa_monto' => isset($tarifas[$tarifaId]) ? (float) $tarifas[$tarifaId]['monto'] : null,
        ];

        return Inertia::render('panel/catalogos/escala-historial', [
            'tramo' => [
                'descripcion_kg' => $categoria->descripcion_kg,
                'kilos_min' => (float) $categoria->kilos_min,
                'kilos_max' => (float) $categoria->kilos_max,
            ],
            'actual' => [
                'servicio_sireb' => $categoria->servicio_sireb,
                'tarifa_sireb' => $categoria->tarifa_sireb,
                // Vale desde el último cambio o, si nunca cambió, desde el alta.
                'desde' => end($historial)['hasta'] ?? $categoria->created_at?->toIso8601String(),
                ...$describir($categoria->tarifa_sireb),
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
        ]);
    }

    /**
     * Formulario de alta — GET /panel/catalogos/categorias-aprovechamiento/crear
     */
    public function create(SirebService $sireb): Response
    {
        return $this->formulario(null, $sireb);
    }

    /**
     * Formulario de edición — GET /panel/catalogos/categorias-aprovechamiento/{categoria}/editar
     */
    public function edit(CategoriaAprovechamiento $categoria, SirebService $sireb): Response
    {
        return $this->formulario($categoria, $sireb);
    }

    /**
     * ALTA — POST /panel/catalogos/categorias-aprovechamiento
     */
    public function store(GuardarCategoriaAprovechamientoRequest $request): RedirectResponse
    {
        // El número lo pone el sistema: el que sigue al más alto, contando los dados de
        // baja, que también ocupan su número (el único del Request los incluye).
        $categoria = CategoriaAprovechamiento::create($request->validated() + [
            'nro_escala' => ((int) CategoriaAprovechamiento::withTrashed()->max('nro_escala')) + 1,
        ]);

        return redirect()
            ->route('categorias-aprovechamiento.index')
            ->with('exito', "Tramo «{$categoria->descripcion_kg}» registrado.");
    }

    /**
     * Edición — PUT /panel/catalogos/categorias-aprovechamiento/{categoria}
     */
    public function update(
        GuardarCategoriaAprovechamientoRequest $request,
        CategoriaAprovechamiento $categoria,
    ): RedirectResponse {
        $categoria->update($request->validated());

        return redirect()
            ->route('categorias-aprovechamiento.index')
            ->with('exito', "Tramo «{$categoria->descripcion_kg}» actualizado.");
    }

    //  Auxiliares

    /** La misma pantalla para alta (sin tramo) y edición. */
    private function formulario(?CategoriaAprovechamiento $categoria, SirebService $sireb): Response
    {
        return Inertia::render('panel/catalogos/escala-formulario', [
            'tramo' => $categoria === null ? null : [
                'id' => $categoria->id,
                'modalidad' => $categoria->modalidad->value,
                'descripcion_kg' => $categoria->descripcion_kg,
                'kilos_min' => (float) $categoria->kilos_min,
                'kilos_max' => (float) $categoria->kilos_max,
                'servicio_sireb' => $categoria->servicio_sireb,
                'tarifa_sireb' => $categoria->tarifa_sireb,
                'estado' => (bool) $categoria->estado,
            ],
            'modalidades' => ModalidadAprovechamiento::opciones(),
            // Para el select de tarifa: null si SIREB no responde, y la pantalla abre igual.
            'serviciosSireb' => $this->serviciosParaSelect($sireb->serviciosSiResponde()),
            // Qué escala ya usa cada tarifa: el select las deshabilita en vez de dejar que el Request las rechace.
            'tarifasUsadas' => CategoriaAprovechamiento::query()->pluck('nro_escala', 'tarifa_sireb'),
        ]);
    }

    /**
     * Los servicios de SIREB recortados a lo que usa el select: sin categorías,
     * rubro ni lo demás que no se muestra.
     *
     * @param  list<array<string, mixed>>|null  $servicios
     * @return list<array<string, mixed>>|null
     */
    private function serviciosParaSelect(?array $servicios): ?array
    {
        if ($servicios === null) {
            return null;
        }

        return array_map(fn (array $s): array => [
            'id' => $s['id'],
            'codigo' => $s['codigo'] ?? null,
            'nombre' => $s['nombre'] ?? '',
            // SIREB manda también lo dado de baja: solo `activo` se puede elegir.
            'activo' => ($s['estado'] ?? null) === 'activo',
            'tarifas' => array_map(fn (array $t): array => [
                'id' => $t['id'],
                'etiqueta' => $t['etiqueta'] ?? '',
                'monto' => (float) ($t['monto'] ?? 0),
            ], $s['tarifas'] ?? []),
        ], $servicios);
    }

    /**
     * Los rangos de kilos que no caen en ningún tramo.
     *
     * @param  Collection<int, CategoriaAprovechamiento>  $escala
     * @return array<int, array{desde: float, hasta: float}>
     */
    private function huecos(Collection $escala): array
    {
        $tramos = $escala->sortBy(fn (CategoriaAprovechamiento $c): float => (float) $c->kilos_min)->values();
        $huecos = [];

        foreach ($tramos as $i => $tramo) {
            if ($i === 0) {
                continue;
            }

            $anterior = (float) $tramos[$i - 1]->kilos_max;
            $actual = (float) $tramo->kilos_min;

            if ($actual > $anterior + 1) {
                $huecos[] = [
                    'desde' => $anterior + 1,
                    'hasta' => $actual - 1,
                ];
            }
        }

        return $huecos;
    }
}

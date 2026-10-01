<?php

namespace App\Http\Controllers\Panel;

use App\Enums\TipoActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarTipoCarnetRequest;
use App\Models\TipoCarnet;
use App\Sireb\VistaSireb;
use App\Support\Paginacion;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  Catálogo de tipos de carnet — cómo se llama cada credencial y su tarifa en SIREB
 */
class TipoCarnetController extends Controller
{
    /**
     * Listado — GET /panel/catalogos/tipos-carnet
     */
    public function index(Request $request, VistaSireb $vista): Response
    {
        $tarifas = $vista->tarifasPorId();
        $buscar = $request->string('buscar')->trim()->value() ?: null;
        $actor = $request->string('actor')->trim()->value() ?: null;
        $porPagina = Paginacion::filas($request);

        $tipos = TipoCarnet::query()
            ->when($buscar, function ($q) use ($buscar) {
                // Se escapan % y _ porque en LIKE son comodines.
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $buscar).'%';

                $q->where('nombre', Sql::like($q->getConnection()), $like);
            })
            ->when($actor, fn ($q, $a) => $q->where('tipos_carnet.tipo_actor', $a))
            // Igual que en asociaciones: el conteo es lo que explica por qué un
            // tipo no se puede borrar, y de paso dice cuál se usa de verdad.
            ->withCount('carnets')
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString()
            ->through(fn (TipoCarnet $t): array => [
                'id' => $t->id,
                'nombre' => $t->nombre,
                'tipo_actor' => $t->tipo_actor->value,
                'tipo_actor_etiqueta' => $t->tipo_actor->etiqueta(),
                'tipo_actor_color' => $t->tipo_actor->color(),
                'servicio_sireb' => $t->servicio_sireb,
                'tarifa_sireb' => $t->tarifa_sireb,
                ...VistaSireb::describir($tarifas, $t->tarifa_sireb),
                'estado' => (bool) $t->estado,
                'carnets_count' => $t->carnets_count,
            ]);

        return Inertia::render('panel/catalogos/tipos-carnet', [
            'tipos' => $tipos,
            'filtros' => ['buscar' => $buscar, 'actor' => $actor, 'por_pagina' => $porPagina],
            'opcionesPorPagina' => Paginacion::OPCIONES,
            'actores' => TipoActor::opciones(),
            'sirebDisponible' => $tarifas !== null,
        ]);
    }

    /**
     * Historial de SIREB — GET /panel/catalogos/tipos-carnet/{tipo_carnet}
     */
    public function show(TipoCarnet $tipo_carnet, VistaSireb $vista): Response
    {
        return Inertia::render('panel/catalogos/tipos-carnet-historial', [
            'tipo' => ['nombre' => $tipo_carnet->nombre],
            ...$vista->historial($tipo_carnet),
        ]);
    }

    /**
     * Formulario de edición — GET /panel/catalogos/tipos-carnet/{tipo_carnet}/editar
     */
    public function edit(TipoCarnet $tipo_carnet, VistaSireb $vista): Response
    {
        return Inertia::render('panel/catalogos/tipos-carnet-formulario', [
            'tipo' => [
                'id' => $tipo_carnet->id,
                'nombre' => $tipo_carnet->nombre,
                'tipo_actor' => $tipo_carnet->tipo_actor->value,
                'servicio_sireb' => $tipo_carnet->servicio_sireb,
                'tarifa_sireb' => $tipo_carnet->tarifa_sireb,
                'estado' => (bool) $tipo_carnet->estado,
            ],
            'actores' => TipoActor::opciones(),
            // Para el select de tarifa: null si SIREB no responde, y la pantalla abre igual.
            'serviciosSireb' => $vista->serviciosParaSelect(),
            // Qué tipo ya usa cada tarifa: el select las deshabilita en vez de dejar que el Request las rechace.
            'tarifasUsadas' => TipoCarnet::query()->whereNotNull('tarifa_sireb')->pluck('nombre', 'tarifa_sireb'),
        ]);
    }

    /**
     * Edición — PUT /panel/catalogos/tipos-carnet/{tipo_carnet}
     */
    public function update(GuardarTipoCarnetRequest $request, TipoCarnet $tipo_carnet): RedirectResponse
    {
        $tipo_carnet->update($request->validated());

        return redirect()
            ->route('tipos-carnet.index')
            ->with('exito', "Tipo de carnet «{$tipo_carnet->nombre}» actualizado.");
    }
}

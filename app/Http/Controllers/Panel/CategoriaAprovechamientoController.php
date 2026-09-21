<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ModalidadAprovechamiento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarCategoriaAprovechamientoRequest;
use App\Models\CategoriaAprovechamiento;
use App\Support\Paginacion;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  LA ESCALA OFICIAL DE APROVECHAMIENTO — paso 2 del flujo del pescador
 */
class CategoriaAprovechamientoController extends Controller
{
    /**
     * LISTADO Y FORMULARIO — GET /panel/catalogos/categorias-aprovechamiento
     */
    public function index(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->value() ?: null;
        $modalidad = $request->string('modalidad')->trim()->value() ?: null;
        $porPagina = Paginacion::filas($request);

        /*
         * LA ESCALA ENTERA, aparte del listado: los huecos y el número que
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
                'modalidad_etiqueta' => $c->modalidad->etiqueta(),
                'modalidad_color' => $c->modalidad->color(),
                'descripcion_kg' => $c->descripcion_kg,
                'kilos_min' => (float) $c->kilos_min,
                'kilos_max' => (float) $c->kilos_max,
                'valor_bs' => (float) $c->valor_bs,
                'estado' => (bool) $c->estado,
                'aprovechamientos_count' => $c->aprovechamientos_count,
            ]);

        return Inertia::render('panel/catalogos/escala', [
            'escala' => $escala,
            'filtros' => ['buscar' => $buscar, 'modalidad' => $modalidad, 'por_pagina' => $porPagina],
            'opcionesPorPagina' => Paginacion::OPCIONES,

            'huecos' => $this->huecos($todos),
            'siguienteNumero' => ((int) $todos->max('nro_escala')) + 1,

            // Las opciones salen del enum y no escritas en React: si estuvieran
            // en los dos lados, agregar una modalidad en la pantalla y olvidarse
            // del servidor dejaría al operador eligiendo un valor que se rechaza.
            'modalidades' => ModalidadAprovechamiento::opciones(),
        ]);
    }

    /**
     * ALTA — POST /panel/catalogos/categorias-aprovechamiento
     */
    public function store(GuardarCategoriaAprovechamientoRequest $request): RedirectResponse
    {
        $categoria = CategoriaAprovechamiento::create($request->validated());

        return redirect()
            ->route('categorias-aprovechamiento.index')
            ->with('exito', "Escala {$categoria->nro_escala} registrada.");
    }

    /**
     * EDICIÓN — PUT /panel/catalogos/categorias-aprovechamiento/{categoria}
     */
    public function update(
        GuardarCategoriaAprovechamientoRequest $request,
        CategoriaAprovechamiento $categoria,
    ): RedirectResponse {
        $categoria->update($request->validated());

        return redirect()
            ->route('categorias-aprovechamiento.index')
            ->with('exito', "Escala {$categoria->nro_escala} actualizada.");
    }

    //  Auxiliares

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

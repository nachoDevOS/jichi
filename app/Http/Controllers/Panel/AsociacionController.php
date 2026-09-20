<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoAsociacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarAsociacionRequest;
use App\Models\Asociacion;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  CATÁLOGO DE ASOCIACIONES — el gremio que certifica al beneficiario
 */
class AsociacionController extends Controller
{
    /**
     * LISTADO Y FORMULARIO — GET /panel/catalogos/asociaciones
     */
    public function index(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->value() ?: null;

        $asociaciones = Asociacion::query()
            ->when($buscar, function ($q) use ($buscar) {
                // Se escapan % y _ porque en LIKE son comodines: buscar «100%»
                // traería el catálogo entero si no se neutralizan.
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $buscar).'%';
                $operador = Sql::like($q->getConnection());

                $q->where(fn ($s) => $s
                    ->where('nombre', $operador, $like)
                    ->orWhere('sigla', $operador, $like));
            })
            /*
             * El conteo de uso NO es decorativo: es lo que explica por qué una
             * asociación no se puede borrar. Sin el número, «solo se puede
             * desactivar» suena a capricho del sistema.
             */
            ->withCount(['carnets', 'guias'])
            ->ordenAlfabetico()
            ->get()
            ->map(fn (Asociacion $a): array => [
                'id' => $a->id,
                'nombre' => $a->nombre,
                'sigla' => $a->sigla,
                'estado' => $a->estado->value,
                'estado_etiqueta' => $a->estado->etiqueta(),
                'estado_color' => $a->estado->color(),
                'carnets_count' => $a->carnets_count,
                'guias_count' => $a->guias_count,
            ])
            ->all();

        return Inertia::render('panel/catalogos/asociaciones', [
            'asociaciones' => $asociaciones,
            'filtros' => ['buscar' => $buscar],
            // Las opciones salen del enum y no escritas en React: si estuvieran
            // en los dos lados, agregar un estado en la pantalla y olvidarse del
            // servidor dejaría al operador eligiendo un valor que se rechaza.
            'estados' => EstadoAsociacion::opciones(),
        ]);
    }

    /**
     * ALTA — POST /panel/catalogos/asociaciones
     */
    public function store(GuardarAsociacionRequest $request): RedirectResponse
    {
        $asociacion = Asociacion::create($request->validated());

        return redirect()
            ->route('asociaciones.index')
            ->with('exito', "Asociación «{$asociacion->nombre}» registrada.");
    }

    /**
     * EDICIÓN — PUT /panel/catalogos/asociaciones/{asociacion}
     */
    public function update(GuardarAsociacionRequest $request, Asociacion $asociacion): RedirectResponse
    {
        $asociacion->update($request->validated());

        return redirect()
            ->route('asociaciones.index')
            ->with('exito', "Asociación «{$asociacion->nombre}» actualizada.");
    }
}

<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarTipoCarnetRequest;
use App\Models\TipoCarnet;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  CATÁLOGO DE TIPOS DE CARNET — cómo se llama cada credencial y cuánto sale
 */
class TipoCarnetController extends Controller
{
    /**
     * LISTADO Y FORMULARIO — GET /panel/catalogos/tipos-carnet
     */
    public function index(): Response
    {
        $tipos = TipoCarnet::query()
            // Igual que en asociaciones: el conteo es lo que explica por qué un
            // tipo no se puede borrar, y de paso dice cuál se usa de verdad.
            ->withCount('carnets')
            ->orderBy('nombre')
            ->get()
            ->map(fn (TipoCarnet $t): array => [
                'id' => $t->id,
                'nombre' => $t->nombre,
                'precio_bs' => (float) $t->precio_bs,
                'estado' => (bool) $t->estado,
                'carnets_count' => $t->carnets_count,
            ])
            ->all();

        return Inertia::render('panel/catalogos/tipos-carnet', [
            'tipos' => $tipos,
        ]);
    }

    /**
     * ALTA — POST /panel/catalogos/tipos-carnet
     */
    public function store(GuardarTipoCarnetRequest $request): RedirectResponse
    {
        $tipo = TipoCarnet::create($request->validated());

        return redirect()
            ->route('tipos-carnet.index')
            ->with('exito', "Tipo de carnet «{$tipo->nombre}» registrado.");
    }

    /**
     * EDICIÓN — PUT /panel/catalogos/tipos-carnet/{tipo_carnet}
     */
    public function update(GuardarTipoCarnetRequest $request, TipoCarnet $tipo_carnet): RedirectResponse
    {
        $tipo_carnet->update($request->validated());

        return redirect()
            ->route('tipos-carnet.index')
            ->with('exito', "Tipo de carnet «{$tipo_carnet->nombre}» actualizado.");
    }
}

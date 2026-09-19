<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarTipoCarnetRequest;
use App\Models\TipoCarnet;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  CATÁLOGO DE TIPOS DE CARNET — cómo se llama cada credencial y cuánto sale
 * ============================================================================
 *
 * ----------------------------------------------------------------------------
 *  ES EL CATÁLOGO, NO LA REGLA
 * ----------------------------------------------------------------------------
 *
 * Qué habilita un carnet —si emite faenas o guías, si lleva cupo en kilos— lo
 * dice `carnets.tipo_actor`, que es un enum de PHP. De ESTE nombre no cuelga
 * ninguna decisión, y por eso se puede editar libremente desde acá: el mismo
 * documento figura como «Carnet de Pescador» o «Pescador Artesanal» según quién
 * lo cargó, y un `match` sobre ese texto se rompería en silencio.
 *
 * ----------------------------------------------------------------------------
 *  CAMBIAR EL PRECIO NO TOCA LO YA COBRADO
 * ----------------------------------------------------------------------------
 *
 * `precio_bs` es el arancel de HOY, para armar un cobro nuevo. Lo que se cobró
 * de verdad vive en `pagos` y no se recalcula nunca, así que un carnet emitido
 * en marzo a 80 Bs sigue diciendo 80 Bs en agosto aunque el arancel haya subido.
 *
 * Lo que SÍ cambia al subir el precio es el saldo pendiente de los carnets que
 * no estén cubiertos: `Carnet::montoACobrar()` lee esta columna. Es lo correcto
 * —lo que se debe se debe a la tarifa vigente— pero conviene saberlo antes de
 * tocar el número.
 *
 * Igual que en asociaciones, NO hay `destroy()`: los carnets emitidos apuntan
 * acá. Un tipo que se deja de usar se pone en `estado = false`.
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

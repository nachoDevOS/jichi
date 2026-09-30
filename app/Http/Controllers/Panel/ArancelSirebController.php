<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarArancelSirebRequest;
use App\Models\ArancelSireb;
use App\Sireb\VistaSireb;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  Aranceles de SIREB — la tarifa de los cobros sin catálogo (hoy, la faena)
 *
 * Sin alta ni baja: hay una fila por `ConceptoArancel` y la pone el seeder.
 */
class ArancelSirebController extends Controller
{
    /**
     * Listado — GET /panel/catalogos/aranceles
     */
    public function index(VistaSireb $vista): Response
    {
        $precios = $vista->preciosPorTarifa();

        return Inertia::render('panel/catalogos/aranceles', [
            'aranceles' => ArancelSireb::query()
                ->orderBy('id')
                ->get()
                ->map(fn (ArancelSireb $a): array => [
                    'id' => $a->id,
                    'concepto' => $a->concepto->value,
                    'etiqueta' => $a->concepto->etiqueta(),
                    'descripcion' => $a->concepto->descripcion(),
                    'servicio_sireb' => $a->servicio_sireb,
                    'tarifa_sireb' => $a->tarifa_sireb,
                    // De referencia: el que vale lo congela cada documento al emitirse.
                    'precio' => $precios[$a->tarifa_sireb] ?? null,
                ])
                ->all(),
            'sirebDisponible' => $precios !== null,
        ]);
    }

    /**
     * Historial de SIREB — GET /panel/catalogos/aranceles/{arancel}
     */
    public function show(ArancelSireb $arancel, VistaSireb $vista): Response
    {
        return Inertia::render('panel/catalogos/aranceles-historial', [
            'arancel' => ['etiqueta' => $arancel->concepto->etiqueta()],
            ...$vista->historial($arancel),
        ]);
    }

    /**
     * Formulario — GET /panel/catalogos/aranceles/{arancel}/editar
     */
    public function edit(ArancelSireb $arancel, VistaSireb $vista): Response
    {
        return Inertia::render('panel/catalogos/aranceles-formulario', [
            'arancel' => [
                'id' => $arancel->id,
                'etiqueta' => $arancel->concepto->etiqueta(),
                'descripcion' => $arancel->concepto->descripcion(),
                'servicio_sireb' => $arancel->servicio_sireb,
                'tarifa_sireb' => $arancel->tarifa_sireb,
            ],
            // Para el select de tarifa: null si SIREB no responde, y la pantalla abre igual.
            'serviciosSireb' => $vista->serviciosParaSelect(),
        ]);
    }

    /**
     * Edición — PUT /panel/catalogos/aranceles/{arancel}
     */
    public function update(GuardarArancelSirebRequest $request, ArancelSireb $arancel): RedirectResponse
    {
        $arancel->update($request->validated());

        return redirect()
            ->route('aranceles.index')
            ->with('exito', "Arancel «{$arancel->concepto->etiqueta()}» actualizado.");
    }
}

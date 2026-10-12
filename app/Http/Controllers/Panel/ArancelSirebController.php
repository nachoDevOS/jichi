<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ConceptoArancel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarArancelSirebRequest;
use App\Models\ArancelSireb;
use App\Sireb\SirebException;
use App\Sireb\SirebService;
use App\Sireb\TarifarioSireb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  Aranceles de SIREB — qué servicio cobra cada trámite (solo el servicio: la
 *  tarifa se elige al emitir)
 *
 * Sin alta ni baja: hay una fila por `ConceptoArancel` y la pone el seeder.
 */
class ArancelSirebController extends Controller
{
    /**
     * Listado — GET /panel/catalogos/aranceles
     */
    public function index(TarifarioSireb $tarifario): Response
    {
        $servicios = $tarifario->servicios();

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
                    // Cómo se llama HOY en SIREB y cuántas tarifas cobra; null si no está o no responde.
                    'servicio' => $servicios[$a->servicio_sireb] ?? null,
                    // Para «Ver tarifas»: etiqueta, precio y si está activa, de la caché del catálogo.
                    'tarifas' => $tarifario->tarifas($a->concepto) ?? [],
                ])
                ->all(),
            'sirebDisponible' => $servicios !== null,
        ]);
    }

    /**
     * Actualizar desde SIREB — POST /panel/catalogos/aranceles/refrescar
     *
     * Vuelve a pedir el catálogo sin esperar a que venza la caché. Lo que se
     * cobra no depende de esto: al emitir el precio se pide sin caché.
     */
    public function refrescar(SirebService $sireb): RedirectResponse
    {
        try {
            $sireb->servicios(refrescar: true);
        } catch (SirebException) {
            return back()->with('aviso', 'Recaudaciones (SIREB) no responde. Intente de nuevo en unos minutos.');
        }

        Cache::forget('portada.especies');

        return back()->with('exito', 'Precios, tramos y productos actualizados desde SIREB.');
    }

    /**
     * Historial de SIREB — GET /panel/catalogos/aranceles/{arancel}
     */
    public function show(ArancelSireb $arancel, TarifarioSireb $tarifario): Response
    {
        return Inertia::render('panel/catalogos/aranceles-historial', [
            'arancel' => ['etiqueta' => $arancel->concepto->etiqueta()],
            ...$tarifario->historial($arancel),
        ]);
    }

    /**
     * Formulario — GET /panel/catalogos/aranceles/{arancel}/editar
     */
    public function edit(ArancelSireb $arancel, TarifarioSireb $tarifario): Response
    {
        $servicios = $tarifario->servicios();

        return Inertia::render('panel/catalogos/aranceles-formulario', [
            'arancel' => [
                'id' => $arancel->id,
                'etiqueta' => $arancel->concepto->etiqueta(),
                'descripcion' => $arancel->concepto->descripcion(),
                'servicio_sireb' => $arancel->servicio_sireb,
            ],
            // Para el select: null si SIREB no responde, y la pantalla abre igual.
            'serviciosSireb' => $servicios === null ? null : array_values(array_filter($servicios, fn (array $s): bool => $s['activo'])),
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

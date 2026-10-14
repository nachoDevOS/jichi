<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\DefinirTarifasRequest;
use App\Http\Requests\Panel\GuardarArancelSirebRequest;
use App\Models\ArancelSireb;
use App\Models\ArancelTarifa;
use App\Services\DefinirTarifasService;
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
 * Los de los trámites (`concepto`) los pone el seeder; la unidad registra los
 * demás. Sin baja.
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
                ->withCount('tarifasDefinidas')
                ->orderBy('id')
                ->get()
                ->map(fn (ArancelSireb $a): array => [
                    'id' => $a->id,
                    'concepto' => $a->concepto?->value,
                    'etiqueta' => $a->nombre,
                    'descripcion' => $a->concepto?->descripcion() ?? 'Registrado en el panel: todavía no lo cobra ningún trámite.',
                    'servicio_sireb' => $a->servicio_sireb,
                    // Cómo se llama HOY en SIREB y cuántas tarifas cobra; null si no está o no responde.
                    'servicio' => $servicios[$a->servicio_sireb] ?? null,
                    // Para «Ver tarifas»: solo las activas; las dadas de baja en SIREB no se muestran.
                    'tarifas' => self::activas($tarifario->tarifasDelServicio($a->servicio_sireb)) ?? [],
                    // «Definir tarifas»: el carnet, la faena y los que registra la unidad.
                    'usa_tarifas_definidas' => $a->usaTarifasDefinidas(),
                    'tarifas_definidas' => $a->tarifas_definidas_count,
                ])
                ->all(),
            'sirebDisponible' => $servicios !== null,
        ]);
    }

    /**
     * Formulario de alta — GET /panel/catalogos/aranceles/crear
     */
    public function create(TarifarioSireb $tarifario): Response
    {
        return Inertia::render('panel/catalogos/aranceles-formulario', [
            'arancel' => null,
            'serviciosSireb' => self::serviciosActivos($tarifario),
        ]);
    }

    /**
     * Alta — POST /panel/catalogos/aranceles
     */
    public function store(GuardarArancelSirebRequest $request): RedirectResponse
    {
        $arancel = ArancelSireb::create($request->validated());

        // Lo siguiente es definir sus tarifas: se va directo ahí.
        return redirect()
            ->route('aranceles.tarifas', $arancel)
            ->with('exito', "Arancel «{$arancel->nombre}» registrado. Ahora defina sus tarifas.");
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

        return back()->with('exito', 'Precios, escalas y productos actualizados desde SIREB.');
    }

    /**
     * Historial de SIREB — GET /panel/catalogos/aranceles/{arancel}
     */
    public function show(ArancelSireb $arancel, TarifarioSireb $tarifario): Response
    {
        return Inertia::render('panel/catalogos/aranceles-historial', [
            'arancel' => ['etiqueta' => $arancel->nombre],
            ...$tarifario->historial($arancel),
        ]);
    }

    /**
     * Formulario — GET /panel/catalogos/aranceles/{arancel}/editar
     */
    public function edit(ArancelSireb $arancel, TarifarioSireb $tarifario): Response
    {
        return Inertia::render('panel/catalogos/aranceles-formulario', [
            'arancel' => [
                'id' => $arancel->id,
                'nombre' => $arancel->nombre,
                'descripcion' => $arancel->concepto?->descripcion(),
                'servicio_sireb' => $arancel->servicio_sireb,
            ],
            'serviciosSireb' => self::serviciosActivos($tarifario),
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
            ->with('exito', "Arancel «{$arancel->nombre}» actualizado.");
    }

    /**
     * Definir tarifas — GET /panel/catalogos/aranceles/{arancel}/tarifas
     */
    public function tarifas(ArancelSireb $arancel, TarifarioSireb $tarifario): Response|RedirectResponse
    {
        if (! $arancel->usaTarifasDefinidas()) {
            return redirect()->route('aranceles.index')
                ->with('aviso', 'Este arancel no lleva tarifas definidas: ventanilla elige entre todas las de su servicio.');
        }

        $definidas = $arancel->tarifasDefinidas()->orderBy('id')->get();
        $fijas = $arancel->clavesFijas();
        $fila = fn (ArancelTarifa $t): array => [
            'id' => $t->id, 'clave' => $t->clave, 'etiqueta' => $t->etiqueta, 'sireb_tarifa_id' => $t->sireb_tarifa_id,
        ];

        return Inertia::render('panel/catalogos/aranceles-tarifas', [
            'arancel' => ['id' => $arancel->id, 'nombre' => $arancel->nombre],
            // Fijas (carnet): una por clave, con la etiqueta inicial si no se definió. Libres: las guardadas.
            'fijas' => $fijas !== null,
            'filas' => $fijas !== null
                ? collect($fijas)->map(fn (string $inicial, string $clave): array => ($t = $definidas->firstWhere('clave', $clave))
                    ? $fila($t)
                    : ['id' => null, 'clave' => $clave, 'etiqueta' => $inicial, 'sireb_tarifa_id' => null])->values()->all()
                : $definidas->map($fila)->all(),
            // Solo las activas; null si SIREB no responde: la pantalla abre y no deja guardar.
            'tarifasSireb' => self::activas($tarifario->tarifasDelServicio($arancel->servicio_sireb)),
        ]);
    }

    /**
     * Guardar tarifas — PUT /panel/catalogos/aranceles/{arancel}/tarifas
     */
    public function guardarTarifas(DefinirTarifasRequest $request, ArancelSireb $arancel, DefinirTarifasService $servicio): RedirectResponse
    {
        $servicio->guardar($arancel, $request->validated()['tarifas'] ?? []);

        return redirect()
            ->route('aranceles.index')
            ->with('exito', "Tarifas de «{$arancel->nombre}» guardadas.");
    }

    /** Las tarifas activas de una lista de SIREB; null sigue siendo «no responde». */
    private static function activas(?array $tarifas): ?array
    {
        return $tarifas === null ? null : array_values(array_filter($tarifas, fn (array $t): bool => $t['activa']));
    }

    /** Para el select: null si SIREB no responde, y la pantalla abre igual. */
    private static function serviciosActivos(TarifarioSireb $tarifario): ?array
    {
        $servicios = $tarifario->servicios();

        return $servicios === null ? null : array_values(array_filter($servicios, fn (array $s): bool => $s['activo']));
    }
}

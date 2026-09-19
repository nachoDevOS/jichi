<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ModalidadAprovechamiento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarCategoriaAprovechamientoRequest;
use App\Models\CategoriaAprovechamiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  LA ESCALA OFICIAL DE APROVECHAMIENTO — paso 2 del flujo del pescador
 * ============================================================================
 *
 * Es la tabla que convierte una decisión administrativa —«a esta persona le
 * corresponde la escala 3»— en los dos números con los que trabaja el sistema:
 * el volumen en kilos y lo que se cobra por él.
 *
 * ----------------------------------------------------------------------------
 *  LA PANTALLA MUESTRA LOS HUECOS, Y ESO ES LO MÁS ÚTIL QUE HACE
 * ----------------------------------------------------------------------------
 *
 * Un hueco entre dos tramos no rompe nada visible: simplemente hay volúmenes
 * que no caen en ninguna escala, `CategoriaAprovechamiento::paraVolumen()`
 * devuelve null y el formulario de cupo no ofrece nada, sin ningún error que lo
 * explique. Es el tipo de falla que se descubre en ventanilla con alguien
 * enfrente.
 *
 * El SOLAPE lo rechaza el Request —es plata mal cobrada y no tiene lectura
 * válida—. El HUECO no se puede rechazar ahí, porque cargar la escala de a un
 * tramo por vez deja huecos transitorios: al guardar el primero todavía no
 * existe el segundo. Así que se detecta acá, mirando la escala ENTERA, y se
 * muestra como aviso.
 *
 * Igual que los otros dos catálogos, NO hay `destroy()`: los aprovechamientos
 * otorgados apuntan acá para dejar constancia de bajo qué tramo se autorizaron.
 * Una escala derogada se pone en `estado = false`.
 */
class CategoriaAprovechamientoController extends Controller
{
    /**
     * LISTADO Y FORMULARIO — GET /panel/catalogos/categorias-aprovechamiento
     */
    public function index(): Response
    {
        $escala = CategoriaAprovechamiento::query()
            // Cuántos cupos se otorgaron bajo cada tramo: es lo que explica por
            // qué no se puede borrar, y de paso muestra cuál se usa de verdad.
            ->withCount('aprovechamientos')
            ->enOrdenDeEscala()
            ->get();

        return Inertia::render('panel/catalogos/escala', [
            'escala' => $escala
                ->map(fn (CategoriaAprovechamiento $c): array => [
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
                ])
                ->all(),

            'huecos' => $this->huecos($escala),

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

    // ------------------------------------------------------------------
    //  Auxiliares
    // ------------------------------------------------------------------

    /**
     * Los rangos de kilos que no caen en ningún tramo.
     *
     * ------------------------------------------------------------------------
     *  SE RECORRE ORDENADO POR KILOS, NO POR NÚMERO DE ESCALA
     * ------------------------------------------------------------------------
     *
     * Los dos órdenes casi siempre coinciden, pero no tienen por qué: nada
     * impide cargar la escala 7 con el rango más bajo. Recorriendo por
     * `nro_escala` un catálogo así daría huecos y solapes inventados, y el
     * aviso perdería toda credibilidad.
     *
     * El criterio de contigüidad es que cada tramo empiece donde termina el
     * anterior MÁS UNO: el texto oficial dice «1 Kg Hasta 100 Kg» y el
     * siguiente arranca en 101, no en 100,01. Por eso la comparación usa 1 y no
     * un épsilon.
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

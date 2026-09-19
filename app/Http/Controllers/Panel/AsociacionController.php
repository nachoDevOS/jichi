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
 * ============================================================================
 *  CATÁLOGO DE ASOCIACIONES — el gremio que certifica al beneficiario
 * ============================================================================
 *
 * ----------------------------------------------------------------------------
 *  NO HAY `destroy()`, Y NO ES UN OLVIDO
 * ----------------------------------------------------------------------------
 *
 * Los carnets y las guías ya emitidas apuntan acá. Borrar una asociación los
 * dejaría huérfanos —o, con el RESTRICT de la clave foránea, fallaría con un
 * error que el operador no puede interpretar—.
 *
 * Para sacarla de circulación se la pone en `inactivo`: desaparece de los
 * desplegables de alta y los documentos históricos la siguen mostrando. Es lo
 * correcto: la persona pertenecía a ella cuando se le emitió el carnet.
 *
 * ----------------------------------------------------------------------------
 *  UNA SOLA PANTALLA, CON EL FORMULARIO AL LADO DE LA TABLA
 * ----------------------------------------------------------------------------
 *
 * Los tres catálogos tienen `index` + `store` + `update` y ninguna pantalla de
 * alta o edición aparte. El motivo es el tamaño: son listas de pocas filas que
 * se cargan una vez cuando sale la resolución. Navegar a otra pantalla para
 * agregar una fila y volver hace perder de vista la lista, que es justamente
 * contra lo que se compara al cargarla.
 *
 * Es lo contrario de Beneficiarios, que sí tiene pantallas aparte: ahí el
 * formulario tiene veinte campos y una foto, y no entra al costado de nada.
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

<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoRubro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarRubroRequest;
use App\Models\Rubro;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El catálogo de actividades que un carnet puede habilitar.
 *
 * Tabla chica y de escritura rarísima: se toca cuando cambia una ordenanza. Por
 * eso el listado no tiene paginación ni buscador —entra entero en una pantalla—
 * y no hay método destroy(): un rubro no se borra nunca.
 *
 * ¿POR QUÉ NO SE BORRA? Porque los carnets y trámites históricos apuntan a él.
 * Borrarlo dejaría filas huérfanas y, peor, haría desaparecer del reverso de un
 * carnet una actividad que en su momento estuvo autorizada. Se pasa a
 * `inactivo`: deja de ofrecerse en el formulario de solicitud, pero los carnets
 * que ya lo tienen lo siguen mostrando.
 */
class RubroController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('panel/rubros/index', [
            'rubros' => Rubro::query()
                // Cuántos carnets dependen de cada rubro. Es el dato que le dice
                // al administrador qué tan grave sería desactivarlo.
                ->withCount('habilitaciones')
                ->withCount('tramites')
                ->orderBy('nombre')
                ->get()
                ->map(fn (Rubro $r): array => [
                    'id' => $r->id,
                    'nombre' => $r->nombre,
                    'descripcion' => $r->descripcion,
                    'costo' => (float) $r->costo,
                    'estado' => $r->estado->value,
                    'estado_etiqueta' => $r->estado->etiqueta(),
                    'estado_color' => $r->estado->color(),
                    'habilitaciones_count' => $r->habilitaciones_count,
                    'tramites_count' => $r->tramites_count,
                ])
                ->all(),
            'estados' => EstadoRubro::opciones(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('panel/rubros/crear', [
            'estados' => EstadoRubro::opciones(),
        ]);
    }

    public function store(GuardarRubroRequest $request): RedirectResponse
    {
        $rubro = Rubro::create($request->validated());

        return redirect()
            ->route('rubros.index')
            ->with('exito', "Rubro «{$rubro->nombre}» creado.");
    }

    public function edit(Rubro $rubro): Response
    {
        return Inertia::render('panel/rubros/editar', [
            'rubro' => [
                'id' => $rubro->id,
                'nombre' => $rubro->nombre,
                'descripcion' => $rubro->descripcion,
                'costo' => (float) $rubro->costo,
                'estado' => $rubro->estado->value,
            ],
            'estados' => EstadoRubro::opciones(),
            // Se le avisa al administrador que cambiar la tarifa NO toca los
            // expedientes ya abiertos. Sin el aviso, la primera reacción al ver
            // que un trámite viejo sigue con el precio anterior es pensar que el
            // sistema falló.
            'tramitesAbiertos' => $rubro->tramites()->pendientes()->count(),
        ]);
    }

    public function update(GuardarRubroRequest $request, Rubro $rubro): RedirectResponse
    {
        $rubro->update($request->validated());

        return redirect()
            ->route('rubros.index')
            ->with('exito', "Rubro «{$rubro->nombre}» actualizado. Los trámites ya registrados conservan su monto original.");
    }
}

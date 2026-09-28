<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarProductoHidrobiologicoRequest;
use App\Models\ProductoHidrobiologico;
use App\Support\Paginacion;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  CATÁLOGO DE PRODUCTOS HIDROBIOLÓGICOS — el cuadro D de la guía
 *
 * Nombre, tasa por kilo que se cobra en la guía y si se puede elegir. Sin baja: un
 * producto usado en una guía se pone fuera de uso, no se borra.
 */
class ProductoHidrobiologicoController extends Controller
{
    /**
     * LISTADO Y FORMULARIO — GET /panel/catalogos/productos
     */
    public function index(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->value() ?: null;
        $porPagina = Paginacion::filas($request);

        $productos = ProductoHidrobiologico::query()
            ->when($buscar, function ($q) use ($buscar) {
                // Se escapan % y _ porque en LIKE son comodines.
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $buscar).'%';

                $q->where('nombre', Sql::like($q->getConnection()), $like);
            })
            ->withCount('detalles')
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString()
            ->through(fn (ProductoHidrobiologico $p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'precio_kg' => (float) $p->precio_kg,
                'estado' => (bool) $p->estado,
                'detalles_count' => $p->detalles_count,
            ]);

        return Inertia::render('panel/catalogos/productos', [
            'productos' => $productos,
            'filtros' => ['buscar' => $buscar, 'por_pagina' => $porPagina],
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * ALTA — POST /panel/catalogos/productos
     */
    public function store(GuardarProductoHidrobiologicoRequest $request): RedirectResponse
    {
        $producto = ProductoHidrobiologico::create($request->validated());

        return redirect()
            ->route('productos.index')
            ->with('exito', "Producto «{$producto->nombre}» registrado.");
    }

    /**
     * EDICIÓN — PUT /panel/catalogos/productos/{producto}
     */
    public function update(GuardarProductoHidrobiologicoRequest $request, ProductoHidrobiologico $producto): RedirectResponse
    {
        $producto->update($request->validated());

        return redirect()
            ->route('productos.index')
            ->with('exito', "Producto «{$producto->nombre}» actualizado.");
    }
}

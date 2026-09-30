<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarProductoHidrobiologicoRequest;
use App\Models\ProductoHidrobiologico;
use App\Sireb\VistaSireb;
use App\Support\Paginacion;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  Catálogo de productos hidrobiológicos — el cuadro D de la guía
 *
 * Nombre, tarifa por kilo en SIREB y si se puede elegir. Sin baja: un producto
 * usado en una guía se pone fuera de uso, no se borra.
 */
class ProductoHidrobiologicoController extends Controller
{
    /**
     * Listado — GET /panel/catalogos/productos
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
                'servicio_sireb' => $p->servicio_sireb,
                'tarifa_sireb' => $p->tarifa_sireb,
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
     * Historial de SIREB — GET /panel/catalogos/productos/{producto}
     */
    public function show(ProductoHidrobiologico $producto, VistaSireb $vista): Response
    {
        return Inertia::render('panel/catalogos/productos-historial', [
            'producto' => ['nombre' => $producto->nombre],
            ...$vista->historial($producto),
        ]);
    }

    /**
     * Formulario de alta — GET /panel/catalogos/productos/crear
     */
    public function create(VistaSireb $vista): Response
    {
        return $this->formulario(null, $vista);
    }

    /**
     * Formulario de edición — GET /panel/catalogos/productos/{producto}/editar
     */
    public function edit(ProductoHidrobiologico $producto, VistaSireb $vista): Response
    {
        return $this->formulario($producto, $vista);
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
     * Edición — PUT /panel/catalogos/productos/{producto}
     */
    public function update(GuardarProductoHidrobiologicoRequest $request, ProductoHidrobiologico $producto): RedirectResponse
    {
        $producto->update($request->validated());

        return redirect()
            ->route('productos.index')
            ->with('exito', "Producto «{$producto->nombre}» actualizado.");
    }

    /** La misma pantalla para alta (sin producto) y edición. */
    private function formulario(?ProductoHidrobiologico $producto, VistaSireb $vista): Response
    {
        return Inertia::render('panel/catalogos/productos-formulario', [
            'producto' => $producto === null ? null : [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'servicio_sireb' => $producto->servicio_sireb,
                'tarifa_sireb' => $producto->tarifa_sireb,
                'estado' => (bool) $producto->estado,
            ],
            // Para el select de tarifa: null si SIREB no responde, y la pantalla abre igual.
            'serviciosSireb' => $vista->serviciosParaSelect(),
            // Se pueden compartir: el select solo avisa qué productos la usan.
            'tarifasUsadas' => ProductoHidrobiologico::query()
                ->whereNotNull('tarifa_sireb')
                ->orderBy('nombre')
                ->get(['nombre', 'tarifa_sireb'])
                ->groupBy('tarifa_sireb')
                ->map(fn ($grupo) => $grupo->pluck('nombre')->join(', ')),
        ]);
    }
}

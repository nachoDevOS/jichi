<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Sireb\RegistroSireb;
use App\Support\Paginacion;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 *  Registro SIREB — lo que Jichi le pidió a SIREB (y a Ibare por el token) y lo
 *  que contestó, día por día. Lee los archivos de log; no hay tabla.
 */
class RegistroSirebController extends Controller
{
    public function __construct(private readonly RegistroSireb $registro) {}

    /**
     * Lista — GET /panel/seguridad/sireb
     */
    public function index(Request $request): Response
    {
        $dias = $this->registro->dias();
        $pedido = (string) $request->query('dia');
        $dia = collect($dias)->contains('dia', $pedido) ? $pedido : ($dias[0]['dia'] ?? null);

        $filtros = [
            'dia' => $dia,
            'resultado' => array_key_exists((string) $request->query('resultado'), RegistroSireb::RESULTADOS) ? $request->query('resultado') : null,
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $entradas = collect($dia ? $this->registro->entradas($dia) : []);
        // Los contadores son del día entero: no cambian al filtrar.
        $resumen = collect(RegistroSireb::RESULTADOS)->map(fn (array $r, string $clave): int => $entradas->where('resultado', $clave)->count());

        $filtradas = $entradas
            ->when($filtros['resultado'], fn ($c, string $r) => $c->where('resultado', $r))
            ->when($filtros['buscar'], fn ($c, string $t) => $c->filter(fn (array $e): bool => mb_stripos($e['texto'], $t) !== false))
            ->values();

        $pagina = LengthAwarePaginator::resolveCurrentPage();
        $paginado = (new LengthAwarePaginator(
            $filtradas->forPage($pagina, $filtros['por_pagina'])->map(fn (array $e): array => collect($e)->except('texto')->all())->values(),
            $filtradas->count(),
            $filtros['por_pagina'],
            $pagina,
            ['path' => $request->url()],
        ))->withQueryString();

        return Inertia::render('panel/seguridad/registro-sireb', [
            'entradas' => $paginado,
            'dias' => $dias,
            'resumen' => $resumen,
            'filtros' => $filtros,
            'opcionesPorPagina' => Paginacion::OPCIONES,
            'resultados' => collect(RegistroSireb::RESULTADOS)
                ->map(fn (array $r, string $value): array => ['value' => $value, 'label' => $r[0], 'color' => $r[1]])->values(),
        ]);
    }

    /**
     * El archivo del día tal cual — GET /panel/seguridad/sireb/{dia}/descargar
     */
    public function descargar(string $dia): BinaryFileResponse
    {
        $ruta = $this->registro->archivo($dia);
        abort_if($ruta === null, 404);

        return response()->download($ruta, "sireb-{$dia}.log", ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}

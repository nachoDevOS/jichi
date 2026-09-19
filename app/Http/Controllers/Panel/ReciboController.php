<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\Pago;
use App\Models\Recibo;
use App\Support\Paginacion;
use App\Support\Sql;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  RECIBOS — los comprobantes entregados
 * ============================================================================
 *
 * ----------------------------------------------------------------------------
 *  NO HAY `store`, NI `update`, NI `destroy`
 * ----------------------------------------------------------------------------
 *
 * Un recibo NACE de un cobro: lo emite `CobrarService` junto con sus abonos, en
 * la misma transacción. Un endpoint para crear uno suelto permitiría un
 * comprobante numerado sin ningún pago detrás — un papel oficial que dice que
 * entró plata que no entró.
 *
 * Y no se borra: `numero_recibo` es un correlativo que Contabilidad audita.
 * Borrar una fila deja un hueco en la serie que nadie puede explicar.
 *
 * ----------------------------------------------------------------------------
 *  `monto_total` ESTÁ CONGELADO, Y LA PANTALLA MUESTRA SI DEJÓ DE CUADRAR
 * ----------------------------------------------------------------------------
 *
 * La columna es lo que se IMPRIMIÓ; `Recibo::montoCalculado()` es lo que HAY
 * hoy en el detalle. Si alguien corrigió un abono después de entregar el papel,
 * los dos números se separan — y eso es justamente lo que un arqueo tiene que
 * poder detectar, no algo que convenga tapar recalculando al leer.
 */
class ReciboController extends Controller
{
    /**
     * LISTADO — GET /panel/recibos
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
            'por_pagina' => Paginacion::filas($request),
        ];

        $recibos = Recibo::query()
            ->withCount('pagos')
            // El total de lo que HAY, para contrastarlo con lo impreso sin una
            // consulta agregada por fila.
            ->withSum('pagos', 'monto_parcial')
            ->when($filtros['buscar'], function ($q, $termino) {
                $operador = Sql::like($q->getConnection());
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $termino).'%';

                $q->where(fn ($s) => $s
                    ->where('numero_recibo', $operador, mb_strtoupper($like))
                    ->orWhere('nombre_factura', $operador, $like)
                    ->orWhere('nit_ci_factura', $operador, $like));
            })
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('created_at', '<=', $h))
            ->latest('created_at')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through(fn (Recibo $r): array => [
                'id' => $r->id,
                'numero_recibo' => $r->numero_recibo,
                'nombre_factura' => $r->nombre_factura,
                'nit_ci_factura' => $r->nit_ci_factura,
                'concepto' => $r->concepto,
                'monto_total' => (float) $r->monto_total,
                'pagos_count' => $r->pagos_count,
                /*
                 * `cuadra` compara lo IMPRESO con lo que hay hoy. Llega
                 * resuelto del servidor porque es una comparación con
                 * tolerancia —un céntimo, por el redondeo— y escrita en React
                 * sería una segunda copia de esa tolerancia.
                 */
                'cuadra' => abs((float) ($r->pagos_sum_monto_parcial ?? 0) - (float) $r->monto_total) < 0.01,
                'monto_actual' => (float) ($r->pagos_sum_monto_parcial ?? 0),
                'emitido_en' => $r->created_at?->toIso8601String(),
            ]);

        return Inertia::render('panel/recibos/index', [
            'recibos' => $recibos,
            'filtros' => $filtros,
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * FICHA — GET /panel/recibos/{recibo}
     */
    public function show(Recibo $recibo): Response
    {
        /*
         * Igual que en el listado de caja: `pagable` es polimórfica y NO se
         * precarga con `with('pagos.pagable.beneficiario')` — eso se ignora en
         * silencio y el N+1 sigue ahí. Va con morphWith.
         */
        $recibo->load(['pagos' => fn ($q) => $q->with([
            'pagable' => fn ($m) => $m->morphWith([
                Carnet::class => ['beneficiario', 'tipoCarnet'],
                AprovechamientoPesq::class => ['beneficiario', 'categoria'],
                GuiaMovimiento::class => ['comercializador'],
            ]),
        ])]);

        return Inertia::render('panel/recibos/ver', [
            'recibo' => [
                'id' => $recibo->id,
                'numero_recibo' => $recibo->numero_recibo,
                'nombre_factura' => $recibo->nombre_factura,
                'nit_ci_factura' => $recibo->nit_ci_factura,
                'concepto' => $recibo->concepto,
                'monto_total' => (float) $recibo->monto_total,
                'monto_actual' => $recibo->montoCalculado(),
                'cuadra' => $recibo->cuadra(),
                'emitido_en' => $recibo->created_at?->toIso8601String(),

                'pagos' => $recibo->pagos
                    ->map(fn (Pago $p): array => [
                        'id' => $p->id,
                        'concepto' => $p->concepto_detalle,
                        'detalle' => $this->detalleDe($p),
                        'monto_parcial' => (float) $p->monto_parcial,
                        // La boleta del banco, para poder abrirla desde el
                        // recibo sin ir a buscarla al archivo físico.
                        'nro_transaccion' => $p->nro_transaccion,
                        'comprobante_url' => $p->comprobante_url,
                        'fecha_deposito' => $p->fecha_deposito?->toDateString(),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Qué trámite concreto pagó este abono.
     *
     * El `match` va sobre la CLASE y no sobre el texto de `pagable_type`: es el
     * mismo dato, pero así el analizador avisa cuando se agrega un cobrable y
     * este método se olvida.
     */
    private function detalleDe(Pago $pago): ?string
    {
        $x = $pago->pagable;

        return match (true) {
            $x instanceof Carnet => $x->codigo_legible.' · '.($x->beneficiario?->nombreCompleto ?? '—'),
            $x instanceof AprovechamientoPesq => 'Escala '.($x->categoria?->nro_escala ?? '—').' · '.($x->beneficiario?->nombreCompleto ?? '—'),
            $x instanceof GuiaMovimiento => $x->codigo_guia.' · '.$x->ruta,
            default => null,
        };
    }
}

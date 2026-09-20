<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ConceptoRecibo;
use App\Http\Controllers\Controller;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\Configuracion;
use App\Models\GuiaMovimiento;
use App\Models\Pago;
use App\Models\Recibo;
use App\Support\Paginacion;
use App\Support\ReciboImpreso;
use App\Support\Sql;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as RespuestaHttp;

/**
 *  RECIBOS — los comprobantes entregados
 */
class ReciboController extends Controller
{
    /**
     * Cuántos renglones tiene el cuadro de importes como mínimo.
     */
    private const RENGLONES_MINIMOS = 3;

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
            /*
             * CON LAS COLUMNAS DEL NOMBRE Y LAS DE LA CÉDULA: los dos accesores
             * las leen todas, y una que falte vuelve null sin ningún error.
             */
            ->with('beneficiario:id,ci,complemento,expedido,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado')
            ->withCount('pagos')
            // El total de lo que HAY, para contrastarlo con lo impreso sin una
            // consulta agregada por fila.
            ->withSum('pagos', 'monto_parcial')
            ->when($filtros['buscar'], function ($q, $termino) {
                $operador = Sql::like($q->getConnection());
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $termino).'%';

                // El nombre y la cédula ya no están copiados acá: se buscan
                // sobre el beneficiario, con su propio scope.
                $q->where(fn ($s) => $s
                    ->where('recibos.numero_recibo', $operador, mb_strtoupper($like))
                    ->orWhereHas('beneficiario', fn ($b) => $b->buscar($termino)));
            })
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('created_at', '<=', $h))
            ->latest('created_at')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through(fn (Recibo $r): array => [
                'id' => $r->id,
                'numero_recibo' => $r->numero_recibo,
                'beneficiario_id' => $r->beneficiario_id,
                'beneficiario' => $r->beneficiario?->nombreCompleto,
                'documento' => $r->beneficiario?->documento_identidad,
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
        $recibo->load(['beneficiario', 'pagos' => fn ($q) => $q->with([
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
                'beneficiario_id' => $recibo->beneficiario_id,
                'beneficiario' => $recibo->beneficiario?->nombreCompleto,
                'documento' => $recibo->beneficiario?->documento_identidad,
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

    /**
     *  IMPRIMIR — GET /panel/recibos/{recibo}/imprimir
     */
    public function imprimir(Recibo $recibo): RespuestaHttp
    {
        /*
         * Los pagos van con `morphWith` y NO con `with('pagable.beneficiario')`:
         * Eloquent no sabe qué es `pagable` hasta que lee la fila, así que lo
         * segundo se IGNORA en silencio y cada renglón dispararía su consulta.
         */
        $recibo->load(['beneficiario', 'pagos' => fn ($q) => $q->with([
            'pagable' => fn ($m) => $m->morphWith([
                Carnet::class => ['tipoCarnet'],
                AprovechamientoPesq::class => ['categoria'],
                GuiaMovimiento::class => [],
            ]),
        ])]);

        $impreso = ReciboImpreso::desde(
            $recibo,
            // El lugar de emisión es configurable: el día que se abra una
            // segunda ventanilla no hay que tocar código.
            (string) Configuracion::obtener('documentos.lugar_emision', 'Trinidad - Beni'),
        );

        $pdf = Pdf::loadView('documentos.recibo-oficial', [
            'recibo' => $impreso,
            'fecha' => $impreso->fechaEnCasilleros(),

            /*
             * YA NO SE MANDA `esDeposito`, y no es un olvido.
             */
            'renglones' => array_map(
                fn (array $linea): array => [
                    'descripcion' => $linea['descripcion'],
                    'monto' => number_format($linea['monto'], 2, ',', '.'),
                ],
                $impreso->lineas(),
            ),
            'total' => number_format($impreso->monto(), 2, ',', '.'),

            // Renglones en blanco para que el cuadro conserve su alto aunque
            // haya un solo cobro.
            'blancos' => max(0, self::RENGLONES_MINIMOS - count($impreso->lineas())),

            // Las seis casillas del papel, en el orden impreso. Salen del enum y
            // no escritas en la plantilla: agregar una mañana es tocar un lugar.
            'casillas' => ConceptoRecibo::cases(),

            /*
             * LAS IMÁGENES SON COPIAS A MEDIDA Y VAN EMBEBIDAS EN BASE64.
             */
            'escudo' => $this->imagenEmbebida('image/recibo-escudo.png'),
            'selloSedag' => $this->imagenEmbebida('image/recibo-sello.png'),
        ])
            // MEDIA CARTA APAISADA: 612 x 396 puntos = 8,5" x 5,5".
            ->setPaper([0, 0, 612, 396])
            /*
             * Sin subsetting, DomPDF mete las dos tipografías COMPLETAS en cada
             * PDF —unas 380 KB cada una— aunque el recibo use ochenta caracteres
             * contados. Medido en su momento: 930 KB, de los cuales 734 eran las
             * fuentes.
             */
            ->setOption('enable_font_subsetting', true);

        // `stream` y no `download`: se abre en el visor del navegador, que es
        // desde donde el operador aprieta imprimir.
        return $pdf->stream("recibo-{$impreso->numeroImpreso()}.pdf");
    }

    /**
     * Una imagen del disco, lista para incrustar.
     *
     * Sin el archivo el recibo sale igual, solo que sin escudo: es preferible a
     * un 500 que deje a ventanilla sin poder entregar nada.
     */
    private function imagenEmbebida(string $rutaRelativa): string
    {
        $ruta = public_path($rutaRelativa);

        return is_file($ruta)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($ruta))
            : '';
    }
}

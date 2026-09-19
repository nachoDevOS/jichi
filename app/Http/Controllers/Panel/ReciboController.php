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
     * Cuántos renglones tiene el cuadro de importes como mínimo.
     *
     * El talonario de papel trae tres rayas impresas: con un solo cobro, el
     * cuadro quedaría alto y vacío, y con menos de tres el recibo dejaría de
     * parecerse al papel que la gente conoce.
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

    /**
     * ========================================================================
     *  IMPRIMIR — GET /panel/recibos/{recibo}/imprimir
     * ========================================================================
     *
     * Dibuja el talonario verde del SEDAG en media carta apaisada. La maqueta
     * está en `views/documentos/recibo-oficial.blade.php` y se adapta con
     * App\Support\ReciboImpreso, que expone lo que ese Blade pide sin obligar a
     * reescribirlo: es una maqueta de coordenadas fijas, medida contra el papel.
     */
    public function imprimir(Recibo $recibo): RespuestaHttp
    {
        /*
         * Los pagos van con `morphWith` y NO con `with('pagable.beneficiario')`:
         * Eloquent no sabe qué es `pagable` hasta que lee la fila, así que lo
         * segundo se IGNORA en silencio y cada renglón dispararía su consulta.
         */
        $recibo->load(['pagos' => fn ($q) => $q->with([
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
             * SIEMPRE MARCA «DEPÓSITO». En esta unidad no se cobra en efectivo
             * ni por QR: todo pago es un depósito bancario, así que la casilla
             * de efectivo del papel queda vacía por construcción.
             */
            'esDeposito' => true,

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
             *
             * No son `icon.png` ni `sedag.png`: esos miden más de 2000 px de
             * lado y embebidos hacían un PDF de 5,4 MB por recibo. Las copias de
             * `recibo-*` están al tamaño en que se dibujan y pesan 59 KB juntas,
             * con el sello ya PRE-ATENUADO en el archivo —`opacity` es de lo
             * menos confiable que tiene DomPDF—.
             *
             * Y en base64 porque DomPDF no es un navegador: una ruta se resuelve
             * contra el disco con las restricciones de `chroot` y en producción
             * termina en un recuadro vacío.
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
             *
             * Se activa acá y no en `config/dompdf.php` a propósito: ese archivo
             * lo publica el paquete y conviene dejarlo tal cual para poder
             * compararlo cuando se actualice.
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

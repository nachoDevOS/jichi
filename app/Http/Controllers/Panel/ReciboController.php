<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ConceptoRecibo;
use App\Http\Controllers\Controller;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\Configuracion;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\Recibo;
use App\Support\Paginacion;
use App\Support\QrVerificacion;
use App\Support\ReciboImpreso;
use App\Support\Sql;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as RespuestaHttp;

/**
 *  RECIBOS — el comprobante de cada documento pagado en SIREB
 */
class ReciboController extends Controller
{
    /**
     * Cuántos renglones tiene el cuadro de importes como mínimo.
     */
    private const RENGLONES_MINIMOS = 3;

    /**
     * Listado — GET /panel/recibos
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
             * Con las columnas del nombre y las de la cédula: los dos accesores
             * las leen todas, y una que falte vuelve null sin ningún error.
             */
            ->with('beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado')
            ->when($filtros['buscar'], function ($q, $termino) {
                $operador = Sql::like($q->getConnection());
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $termino).'%';

                // El nombre y la cédula ya no están copiados acá: se buscan
                // sobre el beneficiario, con su propio scope. El número es solo
                // dígitos desde que dejó el prefijo: no hay nada que mayusculizar.
                $q->where(fn ($s) => $s
                    ->where('recibos.numero_recibo', $operador, $like)
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
                'numero_boleta' => $r->numero_boleta,
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
        $recibo->load(['beneficiario', 'recibible']);

        return Inertia::render('panel/recibos/ver', [
            'recibo' => [
                'id' => $recibo->id,
                'numero_recibo' => $recibo->numero_recibo,
                'beneficiario_id' => $recibo->beneficiario_id,
                'beneficiario' => $recibo->beneficiario?->nombreCompleto,
                'documento' => $recibo->beneficiario?->documento_identidad,
                'concepto' => $recibo->concepto,
                'monto_total' => (float) $recibo->monto_total,
                'emitido_en' => $recibo->created_at?->toIso8601String(),

                // La boleta tal como la validó SIREB.
                'numero_boleta' => $recibo->numero_boleta,
                'entidad_bancaria' => $recibo->entidad_bancaria,
                'fecha_pago' => $recibo->fecha_pago?->toDateString(),

                // El documento pagado, con el enlace a su ficha.
                'documento_pagado' => $this->documentoDe($recibo->recibible),
            ],
        ]);
    }

    /** Qué documento pagó este recibo y dónde se abre. @return array{nombre: string, url: string}|null */
    private function documentoDe(mixed $x): ?array
    {
        return match (true) {
            $x instanceof AprovechamientoPesq => ['nombre' => 'Autorización '.$x->numeroLegible(), 'url' => route('aprovechamientos.show', $x)],
            $x instanceof Carnet => ['nombre' => 'Carnet '.$x->codigo_legible, 'url' => route('carnets.show', $x)],
            $x instanceof PermisoFaena => ['nombre' => $x->etiqueta, 'url' => route('faenas.show', $x)],
            $x instanceof GuiaMovimiento => ['nombre' => $x->etiqueta, 'url' => route('guias.show', $x)],
            default => null,
        };
    }

    /**
     *  Imprimir — GET /panel/recibos/{recibo}/imprimir
     */
    public function imprimir(Recibo $recibo): RespuestaHttp
    {
        $recibo->load(['codigo', 'beneficiario', 'recibible']);

        $impreso = ReciboImpreso::desde(
            $recibo,
            // El lugar de emisión es configurable: el día que se abra una
            // segunda ventanilla no hay que tocar código.
            (string) Configuracion::obtener('documentos.lugar_emision', 'Trinidad - Beni'),
        );

        $pdf = Pdf::loadView('documentos.recibo-oficial', [
            'recibo' => $impreso,
            'fecha' => $impreso->fechaEnCasilleros(),

            // El QR y el código, para verificarlo desde el papel.
            'verificacion' => QrVerificacion::de($recibo),

            /*
             * Ya no se manda `esDeposito`, y no es un olvido.
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
             * Las imágenes son copias a medida y van embebidas en BASE64.
             */
            'escudo' => $this->imagenEmbebida('image/recibo-escudo.png'),
            'selloSedag' => $this->imagenEmbebida('image/recibo-sello.png'),
        ])
            // Media carta apaisada: 612 x 396 puntos = 8,5" x 5,5".
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

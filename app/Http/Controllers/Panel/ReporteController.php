<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ConceptoArancel;
use App\Enums\OrigenTramite;
use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Services\ReportePorServicioService;
use App\Support\ExportadorCsv;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as RespuestaHttp;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * REPORTES — el detalle de un tipo de servicio en un rango de fechas, en pantalla, Excel y PDF.
 * Sin permiso propio, a pedido del responsable. Ver docs/modulos/REPORTES.md.
 */
class ReporteController extends Controller
{
    private const POR_PAGINA = 50;

    public function __construct(private readonly ReportePorServicioService $reportes) {}

    /** GET /panel/reportes */
    public function index(Request $request): Response
    {
        [$servicio, $desde, $hasta, $estado, $origen] = $this->filtros($request);
        $consulta = $this->reportes->consulta($servicio, $desde, $hasta, $estado, $origen);

        return Inertia::render('panel/reportes/index', [
            'servicios' => array_map(fn (ConceptoArancel $c): array => ['valor' => $c->value, 'etiqueta' => $c->etiqueta()], ConceptoArancel::cases()),
            'estados' => $this->reportes->estados($servicio),
            'origenes' => OrigenTramite::opciones(),
            'filtros' => ['servicio' => $servicio->value, 'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'estado' => $estado, 'origen' => $origen?->value],
            'columnas' => $this->reportes->columnas($servicio),
            'totales' => $this->reportes->totales($servicio, $consulta),
            'filas' => $consulta->paginate(self::POR_PAGINA)->withQueryString()
                ->through(fn ($doc): array => $this->reportes->fila($servicio, $doc)),
        ]);
    }

    /** GET /panel/reportes/excel: todas las filas, con los mismos filtros. */
    public function excel(Request $request): StreamedResponse
    {
        [$servicio, $desde, $hasta, $estado, $origen] = $this->filtros($request);
        $columnas = $this->reportes->columnas($servicio);

        return ExportadorCsv::descargar(
            $this->nombre($servicio, $desde, $hasta),
            array_column($columnas, 'etiqueta'),
            // lazy() y no lazyById(): este pagina por id y la consulta ordena por fecha; se saltearía filas.
            $this->reportes->consulta($servicio, $desde, $hasta, $estado, $origen)->lazy(500)
                ->map(fn ($doc): array => $this->renglon($this->reportes->fila($servicio, $doc), $columnas, csv: true)),
        );
    }

    /** GET /panel/reportes/pdf: el mismo reporte en papel, A4 apaisado, con membrete y totales. */
    public function pdf(Request $request): RespuestaHttp
    {
        [$servicio, $desde, $hasta, $estado, $origen] = $this->filtros($request);
        $consulta = $this->reportes->consulta($servicio, $desde, $hasta, $estado, $origen);
        $columnas = $this->reportes->columnas($servicio);

        $pdf = Pdf::loadView('documentos.reporte-servicio', [
            'servicio' => $servicio->etiqueta(),
            'institucion' => (string) Configuracion::obtener('municipio.nombre', 'Gobierno Autónomo Departamental del Beni'),
            'escudo' => $this->imagenEmbebida('image/recibo-escudo.png'),
            'rango' => 'Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y'),
            'estado' => $estado ? collect($this->reportes->estados($servicio))->firstWhere('valor', $estado)['etiqueta'] ?? null : null,
            'origen' => $origen?->etiqueta(),
            'generado' => now()->format('d/m/Y H:i').($request->user() ? ' · '.$request->user()->name : ''),
            'columnas' => $columnas,
            'filas' => $consulta->get()->map(fn ($doc): array => $this->renglon($this->reportes->fila($servicio, $doc), $columnas))->all(),
            'totales' => $this->reportes->totales($servicio, $consulta),
        ])->setPaper('a4', 'landscape')->setOption('enable_font_subsetting', true);

        // «Página X de Y»: DomPDF lo escribe después de armar el documento.
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $dompdf->getCanvas()->page_text(745, 572, 'Página {PAGE_NUM} de {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.4, 0.45, 0.5]);

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->nombre($servicio, $desde, $hasta).'.pdf"',
        ]);
    }

    /** @return array{0: ConceptoArancel, 1: Carbon, 2: Carbon, 3: ?string, 4: ?OrigenTramite} */
    private function filtros(Request $request): array
    {
        $datos = $request->validate([
            'servicio' => ['nullable', Rule::enum(ConceptoArancel::class)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'estado' => ['nullable', 'string', 'max:20'],
            'origen' => ['nullable', Rule::enum(OrigenTramite::class)],
        ]);
        $servicio = ConceptoArancel::tryFrom($datos['servicio'] ?? '') ?? ConceptoArancel::Autorizacion;
        [$desde, $hasta] = $this->reportes->periodo($datos['desde'] ?? null, $datos['hasta'] ?? null);

        // Un estado que no es de ese servicio se ignora: no llega texto libre a la consulta.
        $estado = in_array($datos['estado'] ?? null, array_column($this->reportes->estados($servicio), 'valor'), true) ? $datos['estado'] : null;

        return [$servicio, $desde, $hasta, $estado, OrigenTramite::tryFrom($datos['origen'] ?? '')];
    }

    private function nombre(ConceptoArancel $servicio, Carbon $desde, Carbon $hasta): string
    {
        return "reporte-{$servicio->value}_{$desde->toDateString()}_{$hasta->toDateString()}";
    }

    /** Las celdas de una fila, en el orden de las columnas. */
    private function renglon(array $fila, array $columnas, bool $csv = false): array
    {
        return array_map(fn (array $c) => $this->celda($fila[$c['clave']] ?? null, $c['formato'], $csv), $columnas);
    }

    /** Una celda como se lee en papel o en Excel. */
    private function celda(mixed $valor, string $formato, bool $csv = false): ?string
    {
        return match (true) {
            $valor === null || $valor === '' => $csv ? null : '—',
            in_array($formato, ['bs', 'kg'], true) => $csv ? ExportadorCsv::numero($valor) : number_format((float) $valor, 2, ',', '.'),
            $formato === 'fecha' => Carbon::parse($valor)->format('d/m/Y'),
            default => (string) $valor,
        };
    }

    /** Sin el archivo, el PDF sale igual, sin escudo. */
    private function imagenEmbebida(string $rutaRelativa): string
    {
        $ruta = public_path($rutaRelativa);

        return is_file($ruta) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($ruta)) : '';
    }
}

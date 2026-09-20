<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AprovechamientoPesq;
use App\Models\Configuracion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * AUTORIZACIÓN DE PESCA — el papel que se lleva el pescador.
 *
 * Calca el talonario del SEDAG y sale recién con el cupo APROBADO: hasta la
 * firma no hay nada que autorizar. Controlador aparte, como el del carnet:
 * dibuja un documento y devuelve bytes, no pantallas de Inertia.
 */
class AutorizacionPescaController extends Controller
{
    /**
     * LA TABLA DE TAMAÑOS MÍNIMOS, tal como está impresa en el papel.
     *
     * Sale del reglamento y no de la base: es texto preimpreso del talonario, y
     * si una resolución lo cambia se toca acá. Nombre común, científico, tamaño.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const ESPECIES = [
        ['Surubí', 'Pseudoplatystoma fasciatum', '73 cm.:(VEDA 53 cm.)'],
        ['Pacú', 'Colosama macropomun', '62 cm.'],
        ['Tambaqui', 'Piaractus brachypomus', '53cm.'],
        ['Chuncuina', 'Pseudoplatystoma tigrinum', '99 cm. (VEDA 74 cm.)'],
        ['Tucunaré', 'Cichia monoculus', '23.5 cm'],
        ['General', 'Phractocephalus hemiliopterus', '84,5 cm.'],
        ['Corvina', 'Plagiosción squamosissimus', '28 cm.'],
        ['Dorado (escama)', 'Pellona flavipinnis', '52 cm.'],
        ['Sábalo', 'Prochilodus nigricans', '27 cm.'],
        ['Yatorana', 'Bryconspp', '35 cm.'],
        ['Blanquillo', 'Callopysus macropterus', '44,5 cm.'],
        ['Giro', 'Oxidoras Niger', '62,5 cm.'],
        ['Muturo', 'Zungaro zungaro', '3 Kg.'],
    ];

    /** Las reglas de redes del pie del formulario, literales. */
    private const REDES = [
        '<strong>Pescado Grande.-</strong> Como ser: Pacú, Surubi, Chuncuina, Tambaqui y Paraiba de 20 cm. de rombo',
        '<strong>Pescado Chico.-</strong> Como ser: Sábalo, Dorado, Tcunaré, etc. de 14 cm. de rombo',
        '<strong>Tamaño mallas.-</strong> No debe exceder a los 100 mts. Longitud y de 4 mts Altitud',
        'Estas mallas están prohibidas en cuerpos de aguas cerradas como ser lagos y lagunas.',
        'Las mallas por debajo de 14 cm. de rombo están prohibidas y son sujeto a decomisos.',
    ];

    /**
     * IMPRIMIR — GET /panel/aprovechamientos/{aprovechamiento}/autorizacion
     *
     * Solo con el cupo APROBADO. Un vencido o agotado sí se reimprime: puede
     * hacer falta reponer el papel de una gestión cerrada.
     */
    public function imprimir(AprovechamientoPesq $aprovechamiento): Response|RedirectResponse
    {
        // Se vuelve a comprobar acá aunque la pantalla esconda el botón:
        // esconderlo en React es comodidad, la dirección se escribe a mano.
        if (! $aprovechamiento->yaFueAprobado()) {
            return back()->with(
                'error',
                'La autorización sale recién con el aprovechamiento aprobado.',
            );
        }

        $aprovechamiento->loadMissing(['beneficiario', 'categoria']);

        $pdf = Pdf::loadView('documentos.autorizacion-pesca', [
            ...$this->datos($aprovechamiento),
            'especies' => self::ESPECIES,
            'redes' => self::REDES,

            // En base64 y no como ruta: DomPDF resolvería `/image/...` contra el
            // disco con las restricciones de `chroot` y en producción termina en
            // un recuadro vacío.
            'escudo' => $this->imagenEmbebida('image/recibo-escudo.png'),
            // Copia a medida del logo: el original mide 2048 px y pesa 1 MB, y
            // embebido hacía un PDF de 3 MB para dibujar 56 pt.
            'logo' => $this->imagenEmbebida('image/autorizacion-logo.png'),
            'selloSedag' => $this->imagenEmbebida('image/recibo-sello.png'),
        ])
            // CARTA VERTICAL: 612 x 792 puntos = 8,5" x 11".
            ->setPaper([0, 0, 612, 792])
            // Sin esto DomPDF mete las dos tipografías completas —unas 380 KB
            // cada una— en cada documento.
            ->setOption('enable_font_subsetting', true);

        return $pdf->stream("autorizacion-{$this->numeroDe($aprovechamiento)}.pdf");
    }

    /**
     * Lo que va en cada renglón del papel.
     *
     * @return array<string, mixed>
     */
    private function datos(AprovechamientoPesq $cupo): array
    {
        $b = $cupo->beneficiario;

        /*
         * LA FECHA DEL PIE SALE DE `fecha_emision` Y NO DE HOY: una reimpresión
         * del mes que viene tiene que decir lo mismo que el papel entregado.
         */
        $fecha = $cupo->fecha_emision ?? $cupo->created_at;

        return [
            'numero' => $this->numeroDe($cupo),
            'beneficiario' => $b?->nombreCompleto ?? '—',
            'domicilio' => $this->domicilioDe($cupo),
            'documento' => trim(($b?->ci ?? '—').' '.($b?->complemento ?? '').' '.($b?->expedido ?? '')),
            'embarcacion' => $cupo->tipo_embarcacion ?: 'No declarada',
            'volumen' => number_format((float) $cupo->volumen_total_kg, 2, ',', '.'),
            'monto' => number_format($cupo->montoACobrar(), 2, ',', '.'),
            'vigencia' => sprintf(
                'Del %s al %s',
                $cupo->fecha_emision?->format('d/m/Y') ?? '—',
                $cupo->fecha_vencimiento?->format('d/m/Y') ?? '—',
            ),
            // Cuándo terminó de pagarse: es el dato que el papel pregunta.
            'cancelacion' => $this->cancelacionDe($cupo),
            'lugar' => $this->lugar(),
            'fecha' => [
                'dia' => $fecha?->format('d') ?? '',
                'mes' => $fecha ? ucfirst($fecha->translatedFormat('F')) : '',
                'anio' => $fecha?->format('y') ?? '',
            ],
        ];
    }

    /**
     * El número del documento.
     *
     * Es el id del cupo con ceros a la izquierda, y no un correlativo propio: el
     * talonario de papel ya trae el suyo impreso, y un contador aparte se
     * gastaría en cada reimpresión o pediría una columna más. Así una
     * reimpresión sale siempre con el mismo número.
     */
    private function numeroDe(AprovechamientoPesq $cupo): string
    {
        return str_pad((string) $cupo->id, 6, '0', STR_PAD_LEFT);
    }

    /** Dirección, ciudad y provincia en un renglón, sin separadores vacíos. */
    private function domicilioDe(AprovechamientoPesq $cupo): string
    {
        $b = $cupo->beneficiario;

        $partes = array_filter([$b?->direccion, $b?->ciudad, $b?->provincia]);

        return $partes === [] ? 'Sin domicilio en la ficha' : implode(' · ', $partes);
    }

    /** La fecha del último depósito: cuándo quedó cubierto el monto. */
    private function cancelacionDe(AprovechamientoPesq $cupo): string
    {
        $ultimo = $cupo->pagos()->reorder()->orderByDesc('fecha_deposito')->first();

        return $ultimo?->fecha_deposito?->format('d/m/Y') ?? 'Al contado';
    }

    private function lugar(): string
    {
        return (string) Configuracion::obtener('documentos.lugar_emision', 'Trinidad - Beni');
    }

    /**
     * Una imagen del disco, lista para incrustar.
     *
     * Sin el archivo el documento sale igual, solo que sin logo: es preferible a
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

<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Carnet;
use App\Models\Configuracion;
use App\Services\DocumentoEmitidoService;
use App\Support\Archivos;
use App\Support\QrVerificacion;
use App\Support\TextoVertical;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as ArchivoPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 *  Impresión del carnet — la «cédula de pescador»
 */
class CarnetImpresionController extends Controller
{
    /**
     * El tamaño del plástico — CR80, la medida de cualquier tarjeta.
     */
    private const ANCHO = 243.0;

    private const ALTO = 153.0;

    /**
     * Imprimir — GET /panel/carnets/{carnet}/imprimir
     */
    public function imprimir(Carnet $carnet, bool $descarga = false): Response|RedirectResponse
    {
        // Ojo con pedir columnas sueltas: `aprovechamiento` va ENTERO porque
        // `cupoImpreso()` lee `escala`. La que falte vuelve null sin
        // error, y el renglón CUPO sale vacío en un carnet válido.
        $carnet->load(['codigo', 'beneficiario', 'asociacion', 'aprovechamiento']);

        // Sin firmar, revocado o sin efecto no se imprime: el plástico diría que
        // habilita algo que el sistema no autorizó o ya no autoriza.
        if ($motivo = $carnet->motivoSinImpresion()) {
            return back()->with('error', $motivo);
        }

        // `stream` y no `download`: se abre en el visor del navegador, que es
        // desde donde el operador aprieta imprimir.
        // El PDF guardado al emitirse: se entrega tal como salió (ver DocumentoEmitidoService).
        return app(DocumentoEmitidoService::class)->respuesta($carnet, $descarga);
    }

    /**
     * El PDF armado, SIN controles de estado: los hace `imprimir()`. El portal lo
     * pide con `$marcaAgua` para la vista previa «NO VÁLIDO» de un trámite abierto.
     */
    public function documento(Carnet $carnet, ?string $marcaAgua = null): ArchivoPdf
    {
        $carnet->loadMissing(['codigo', 'beneficiario', 'asociacion', 'aprovechamiento']);
        $beneficiario = $carnet->beneficiario;

        // El QR va en las dos caras: se resuelve UNA vez, el PNG no es gratis.
        $verificacion = QrVerificacion::de($carnet);

        $pdf = Pdf::loadView('documentos.carnet-pescador', [
            ...$this->datos($carnet),

            // Las imágenes van embebidas: una ruta se resuelve contra el disco y en producción sale vacía.
            // Escudo y texto del logo por separado: en horizontal van lado a lado, como en la credencial.
            'escudo' => $this->imagenEmbebida('image/carnet-escudo-solo.png'),
            'beni' => $this->imagenEmbebida('image/carnet-beni.png'),
            'beniBlanco' => $this->imagenEmbebida('image/carnet-beni-blanco.png'),
            // DomPDF no gira texto: la palabra de la banda va dibujada con GD.
            'banda' => [
                'imagen' => TextoVertical::dataUri('GOBERNACIÓN', self::CUERPO_BANDA, '#ffffff', negrita: true),
                'medida' => TextoVertical::medida('GOBERNACIÓN', self::CUERPO_BANDA, negrita: true),
            ],
            'foto' => $this->fotoRedonda($beneficiario?->foto),
            'qr' => $verificacion['qr'] ?? '',
            'reverso' => $this->reverso($carnet, $verificacion),
            'marcaAgua' => $marcaAgua,
        ])
            ->setPaper([0, 0, self::ANCHO, self::ALTO])

            // Solo los glifos que se dibujan: sin esto DomPDF embebe las dos
            // DejaVu completas, ~380 KB cada una, en cada carnet.
            ->setOption('enable_font_subsetting', true);

        return $pdf;
    }

    /**
     *  El reglamento del dorso — calcado del plástico de papel
     *
     * Va como constante y no en la base porque es texto del reglamento, no un
     * dato que la unidad edite; mismo criterio que la tabla de tamaños mínimos
     * de AutorizacionPescaController.
     */
    private const TITULO_REVERSO = [
        'DIRECCIÓN DE SERVICIO DEPARTAMENTAL',
        'AGROPECUARIO GANADERO SEDAG-BENI',
    ];

    private const REGLAS_REVERSO = [
        'Está prohibido mallas no autorizadas',
        'Está prohibido utilizar explosivos para pescar',
        'Respetar las áreas protegidas de reserva y desove',
        'Respetar los instructivos emanados SEDAG - BENI',
        'Respetar las VEDAS dictadas por el DDAG - BENI',
        'Portar toda la documentación de pesca emitida por el SEDAG - BENI',
        'Toda infracción será sancionada de acuerdo al reglamento',
    ];

    /**
     *  Lo que va en la carilla de atrás
     *
     * El recuadro blanco de la firma sale SIEMPRE, con nombre o sin él: la
     * firma y el sello se ponen a mano sobre el plástico ya impreso, igual que
     * en la credencial de papel.
     *
     * @return array<string, mixed>
     */
    private function reverso(Carnet $carnet, array $verificacion): array
    {
        // LOS DOS: el QR es lo rápido y el código escrito es lo que funciona con
        // el plástico rayado o poca luz.
        return [
            'titulo' => self::TITULO_REVERSO,
            'reglas' => self::REGLAS_REVERSO,

            'qr' => $verificacion['qr'] ?? '',
            'codigo' => $verificacion['codigo'] ?? '',

            // De `configuraciones` y no de una constante: es el Gobernador y
            // cambia con cada gestión. Vacío imprime el recuadro sin nombre.
            'firmante' => [
                'nombre' => trim((string) Configuracion::obtener('carnet.firmante_nombre', '')),

                // Con valor por defecto para que el dorso salga completo
                // aunque todavía no se haya corrido ConfiguracionSeeder.
                'cargo' => trim((string) Configuracion::obtener(
                    'carnet.firmante_cargo',
                    'GOBERNADOR DEL DEPARTAMENTO DEL BENI',
                )),
            ],
        ];
    }

    /*
     *  Las medidas del frente, en puntos (23/10/2026): banda verde a la izquierda,
     *  columna de la foto y columna de los datos. Calzan con la plantilla.
     */
    /** El cuerpo de «GOBERNACIÓN» en la banda. */
    private const CUERPO_BANDA = 10.5;

    /** El ancho útil de la columna de los datos y de media columna (ciudad | provincia). */
    private const ANCHO_DATOS = 137.0;

    private const ANCHO_MEDIO = 64.0;

    /** El ancho útil bajo la foto, para la cédula. */
    private const ANCHO_CEDULA = 62.0;

    /** Los cuerpos: el nombre manda; los datos, debajo. */
    private const CUERPO_NOMBRE = 8.0;

    /** Dos líneas avanzan 2 × 1,53 × cuerpo y la caja tiene 18 pt: más de 5,8 y la segunda se corta. */
    private const CUERPO_NOMBRE_DOS_LINEAS = 5.8;

    private const CUERPO_VALOR = 5.4;

    private const CUERPO_CEDULA = 5.6;

    /** Un valor en dos líneas: avanzan 2 × 1,53 × cuerpo y la caja tiene 13 pt; con 4,6 la segunda se cortaba. */
    private const CUERPO_DOS_LINEAS = 4.2;

    /** A cuántos píxeles de lado se dibuja la foto redonda antes de embeberla. */
    private const PIXELES_FOTO = 360;

    /**
     * Respaldo si no se pueden leer las métricas de la fuente: el peor caso de la
     * DejaVu negrita, puras mayúsculas (0,665). Con 0,62 se cortaban apellidos.
     */
    private const ANCHO_POR_CARACTER = 0.67;

    /** Hasta donde se puede achicar un texto que no entra, en fraccion de su cuerpo. */
    private const ENCOGIDO_MAXIMO = 0.7;

    /**
     * Los datos del frente, ya resueltos: la plantilla solo los dibuja.
     *
     * @return array<string, mixed>
     */
    private function datos(Carnet $carnet): array
    {
        $beneficiario = $carnet->beneficiario;
        $documento = trim((string) $beneficiario?->documento_identidad);
        $kilos = $carnet->cupoImpreso();

        return [
            // La actividad sale del enum, nunca del nombre del tipo de carnet.
            'titulo' => mb_strtoupper('Cédula de '.$carnet->tipo_actor->etiqueta()),

            'nombre' => $this->texto(
                mb_strtoupper((string) $beneficiario?->nombreCompleto),
                'SIN NOMBRE EN LA FICHA',
                self::ANCHO_DATOS,
                self::CUERPO_NOMBRE,
                self::CUERPO_NOMBRE_DOS_LINEAS,
            ),

            'documento' => $this->texto($documento !== '' ? 'C.I.: '.$documento : null, 'C.I.: —', self::ANCHO_CEDULA, self::CUERPO_CEDULA),

            'asociacion' => $this->texto($carnet->asociacion?->nombre, 'Sin asociación declarada', self::ANCHO_DATOS, self::CUERPO_VALOR),
            'ciudad' => $this->texto($beneficiario?->ciudad, 'Sin cargar', self::ANCHO_MEDIO, self::CUERPO_VALOR),
            'provincia' => $this->texto($beneficiario?->provincia, 'Sin cargar', self::ANCHO_MEDIO, self::CUERPO_VALOR),
            'direccion' => $this->texto($beneficiario?->direccion, 'Sin cargar en la ficha', self::ANCHO_DATOS, self::CUERPO_VALOR),

            /*
             * La franja de abajo: lo que no cambia después de impreso. El cupo solo
             * si la actividad se autoriza por volumen (lo decide el enum).
             */
            'franja' => array_values(array_filter([
                ['rotulo' => 'REGISTRO', 'valor' => 'N° '.($carnet->registro_legible ?? '—')],
                $kilos !== null
                    ? ['rotulo' => 'CUPO', 'valor' => rtrim(rtrim(number_format($kilos, 2, ',', '.'), '0'), ',').' KG']
                    : null,
                ['rotulo' => 'VÁLIDO HASTA', 'valor' => $carnet->fecha_vencimiento?->format('d/m/Y') ?? '—'],
            ])),
        ];
    }

    /**
     *  Un texto que no entra se achica; no se corta
     *
     * @return array{valor: string, molde: string, cuerpo: float, lineas: int}
     */
    private function texto(?string $valor, string $molde, float $disponible, float $cuerpo, ?float $cuerpoDosLineas = null): array
    {
        $texto = trim((string) $valor);
        $dibujado = $texto !== '' ? $texto : $molde;

        $porPunto = max(0.01, $this->anchoEnUnPunto($dibujado));
        $minimo = $cuerpo * self::ENCOGIDO_MAXIMO;

        if ($porPunto * $cuerpo <= $disponible) {
            return ['valor' => $texto, 'molde' => $molde, 'cuerpo' => $cuerpo, 'lineas' => 1];
        }

        $paraUnaLinea = $disponible / $porPunto;

        if ($paraUnaLinea >= $minimo) {
            return ['valor' => $texto, 'molde' => $molde, 'cuerpo' => round($paraUnaLinea, 1), 'lineas' => 1];
        }

        // En dos líneas, y si tampoco entra, más chico: las palabras no se parten, por eso el 0,85.
        $dosLineas = $cuerpoDosLineas ?? self::CUERPO_DOS_LINEAS;
        $paraDosLineas = $disponible * 2 * 0.85 / $porPunto;

        return [
            'valor' => $texto,
            'molde' => $molde,
            'cuerpo' => round(max($dosLineas * self::ENCOGIDO_MAXIMO, min($dosLineas, $paraDosLineas)), 1),
            'lineas' => 2,
        ];
    }

    /**
     * Lo que mide el texto a 1 pt en la DejaVu Sans negrita de las tiras, con las
     * métricas reales de DomPDF: un promedio por carácter falla con las mayúsculas.
     */
    private function anchoEnUnPunto(string $texto): float
    {
        static $metricas = null, $fuente = null;

        try {
            $metricas ??= Pdf::loadHTML('')->getDomPDF()->getFontMetrics();
            $fuente ??= $metricas->getFont('DejaVu Sans', 'bold');

            return (float) $metricas->getTextWidth($texto, $fuente, 1);
        } catch (\Throwable) {
            return mb_strlen($texto) * self::ANCHO_POR_CARACTER;
        }
    }

    /**
     *  La foto del titular, redonda: recortada a un cuadrado (a un tercio de arriba,
     *  donde está la cara) y con el círculo hecho en GD, porque DomPDF no recorta
     *  imágenes con border-radius. Sin foto, la silueta por defecto. Se reduce a
     *  PIXELES_FOTO: entera, la del teléfono pesaba cientos de KB por carnet.
     */
    private function fotoRedonda(?string $ruta): string
    {
        $bytes = Archivos::contenido($ruta) ?? (string) @file_get_contents(public_path('image/user-default.png'));
        $origen = $bytes !== '' ? @imagecreatefromstring($bytes) : false;

        if ($origen === false) {
            return '';
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $lado = min($ancho, $alto);
        // Apaisada: se centra. Vertical: el corte va a un tercio, no a la mitad.
        $x = (int) (($ancho - $lado) / 2);
        $y = (int) (($alto - $lado) / 3);

        $px = self::PIXELES_FOTO;
        $cuadrado = imagecreatetruecolor($px, $px);
        imagecopyresampled($cuadrado, $origen, 0, 0, $x, $y, $px, $px, $lado, $lado);
        imagedestroy($origen);

        // El círculo: lo de afuera queda transparente.
        $redonda = imagecreatetruecolor($px, $px);
        imagealphablending($redonda, false);
        imagesavealpha($redonda, true);
        imagefilledrectangle($redonda, 0, 0, $px, $px, imagecolorallocatealpha($redonda, 255, 255, 255, 127));
        $radio = $px / 2;

        for ($fila = 0; $fila < $px; $fila++) {
            for ($col = 0; $col < $px; $col++) {
                if (($col - $radio + 0.5) ** 2 + ($fila - $radio + 0.5) ** 2 <= $radio ** 2) {
                    imagesetpixel($redonda, $col, $fila, imagecolorat($cuadrado, $col, $fila));
                }
            }
        }
        imagedestroy($cuadrado);

        ob_start();
        imagepng($redonda, null, 9);
        imagedestroy($redonda);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    /**
     * Una imagen de `public/` como data URI.
     */
    private function imagenEmbebida(string $rutaRelativa): string
    {
        $ruta = public_path($rutaRelativa);

        if (! is_file($ruta)) {
            // Sin la imagen el carnet sale igual, solo que sin fondo. Es
            // preferible a un error 500 que deje a ventanilla sin imprimir.
            return '';
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($ruta));
    }
}

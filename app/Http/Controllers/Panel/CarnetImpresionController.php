<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoCarnet;
use App\Http\Controllers\Controller;
use App\Models\Carnet;
use App\Models\Configuracion;
use App\Support\Archivos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 *  IMPRESIÓN DEL CARNET — la «cédula de pescador»
 */
class CarnetImpresionController extends Controller
{
    /**
     * EL TAMAÑO DEL PLÁSTICO — CR80, la medida de cualquier tarjeta.
     */
    private const ANCHO = 243.0;

    private const ALTO = 153.0;

    /**
     * IMPRIMIR — GET /panel/carnets/{carnet}/imprimir
     */
    public function imprimir(Carnet $carnet): Response|RedirectResponse
    {
        /*
         * OJO CON PEDIR COLUMNAS SUELTAS EN EL with(): `aprovechamiento` va
         * ENTERO porque `Carnet::cupoImpreso()` lee `volumen_total_kg`, y el
         * beneficiario va completo porque se usan el nombre en cinco partes, el
         * domicilio y la foto. Una columna que un método consulta y no está en
         * el select vuelve null, y el método contesta cualquier cosa sin ningún
         * error: el renglón CUPO saldría vacío en un carnet perfectamente
         * válido.
         */
        $carnet->load(['beneficiario', 'asociacion', 'aprovechamiento']);

        /*
         * UN CARNET SIN FIRMAR NO SE IMPRIME. El plástico es el documento que
         * la persona se lleva: si sale antes de la aprobación, queda en la
         * calle una credencial que el sistema todavía no autorizó —y que puede
         * terminar rechazada—.
         */
        if (! $carnet->yaFueAprobado()) {
            return back()->with(
                'error',
                'El carnet está '.mb_strtolower($carnet->estado->etiqueta()).': se imprime recién '.
                'cuando esté aprobado.',
            );
        }

        /*
         * UN CARNET REVOCADO NO SE IMPRIME.
         */
        if ($carnet->estado === EstadoCarnet::Revocado) {
            return back()->with('error', 'El carnet está revocado: no se puede imprimir.');
        }

        return $this->pdf($carnet);
    }

    private function pdf(Carnet $carnet): Response
    {
        $beneficiario = $carnet->beneficiario;

        $pdf = Pdf::loadView('documentos.carnet-pescador', [
            'carnet' => $carnet,

            ...$this->datos($carnet),

            /*
             *  LAS IMÁGENES VAN EMBEBIDAS, Y EL FONDO VIENE HORNEADO
             */
            'fondo' => $this->imagenEmbebida('image/carnet-fondo.png'),

            /*
             * El escudo del encabezado, recortado del lockup de la
             * Gobernación: acá va SOLO el escudo porque el texto —GOBERNACIÓN,
             * BENI— lo escribe la plantilla, como en el plástico de papel.
             */
            'escudo' => $this->imagenEmbebida('image/carnet-escudo.png'),

            /*
             * LA FOTO DEL TITULAR, si la ficha tiene una cargada.
             */
            'foto' => $this->fotoEmbebida($beneficiario?->foto),

            /*
             * LA CARILLA DE ATRÁS. Ver reverso().
             */
            'reverso' => $this->reverso(),
        ])
            ->setPaper([0, 0, self::ANCHO, self::ALTO])

            /*
             * Solo los glifos que el documento dibuja. Sin esto DomPDF embebe
             * las dos DejaVu Sans completas —unas 380 KB cada una— dentro de
             * cada carnet. Ver el mismo comentario en ReciboController.
             */
            ->setOption('enable_font_subsetting', true);

        // `stream` y no `download`: se abre en el visor del navegador, que es
        // desde donde el operador aprieta imprimir. Un archivo descargado
        // obligaría a buscarlo en la carpeta de descargas y abrirlo aparte.
        return $pdf->stream("carnet-{$carnet->codigo_carnet}.pdf");
    }

    /**
     *  EL REGLAMENTO DEL DORSO — calcado del plástico de papel
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
     *  LO QUE VA EN LA CARILLA DE ATRÁS
     *
     * El recuadro blanco de la firma sale SIEMPRE, con nombre o sin él: la
     * firma y el sello se ponen a mano sobre el plástico ya impreso, igual que
     * en la credencial de papel.
     *
     * @return array<string, mixed>
     */
    private function reverso(): array
    {
        return [
            'titulo' => self::TITULO_REVERSO,
            'reglas' => self::REGLAS_REVERSO,

            /*
             * Quién firma sale de `configuraciones` y no de una constante: es
             * el Gobernador, y cambia con cada gestión. Vacío imprime el
             * recuadro sin nombre, que es preferible a estampar el de quien ya
             * no está en el cargo.
             */
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

    /**
     *  LAS MEDIDAS DE LA COLUMNA DE DATOS
     *
     * En puntos, y calzadas con las coordenadas del Blade. Ver las cuentas del
     * encabezado de esa plantilla.
     */
    private const ANCHO_DATOS = 176.0;

    /**
     * EL ANCHO ÚTIL DE LA TIRA DEL VALOR, en puntos.
     */
    private const ANCHO_VALOR = 121.0;

    /**
     * EL ANCHO UTIL DE CADA MITAD DEL RENGLON PARTIDO, en puntos.
     */
    private const ANCHO_VALOR_ANGOSTO = 38.0;

    /**
     * EL RENGLON PARTIDO EN TRES — REGISTRO + GESTION + CUPO.
     */
    private const ANCHO_TRIPLE_REGISTRO = 20.0;

    private const ANCHO_TRIPLE_GESTION = 15.0;

    private const ANCHO_TRIPLE_CUPO = 31.5;

    /**
     * Cuantos caracteres entran en el TITULO a cuerpo pleno.
     */
    private const CARACTERES_TITULO = 30;

    /**
     * EL ANCHO UTIL DE LA TIRA DE LA CEDULA, y su cuerpo, en puntos.
     */
    private const ANCHO_CEDULA = 43.6;

    private const CUERPO_CEDULA = 5.0;

    /** Las medidas del recuadro de la foto, en puntos. */
    private const ANCHO_FOTO = 46.0;

    private const ALTO_FOTO = 46.0;

    /**
     * A cuantos pixeles de lado se reduce la foto antes de embeberla.
     */
    private const PIXELES_FOTO = 300;

    /**
     *  LA JERARQUIA TIPOGRAFICA
     */
    /*
     * 5,2 pt y no 6: es el tamaño al que venía saliendo la ASOCIACIÓN, que es
     * el renglón más largo y el único que el encogido de `texto()` bajaba.
     * Con 6 fijo, el nombre salía notoriamente más grande que el gremio y la
     * tira se leía despareja; igualado, los seis renglones pesan lo mismo.
     */
    private const CUERPO_VALOR = 5.2;

    /**
     * El cuerpo y el alto de una tira que pasó a DOS líneas.
     */
    private const CUERPO_DOS_LINEAS = 4.5;

    private const ALTO_DOS_LINEAS = 12.6;

    private const ALTO_UNA_LINEA = 9.5;

    /**
     * Cuanto ocupa cada caracter, en fraccion del cuerpo.
     */
    private const ANCHO_POR_CARACTER = 0.55;

    /**
     * Hasta donde se puede achicar un texto que no entra, en fraccion de su
     * cuerpo normal.
     */
    private const ENCOGIDO_MAXIMO = 0.7;

    /**
     * LOS DATOS DE LA TARJETA, ya resueltos aca.
     *
     * @return array<string, mixed>
     */
    private function datos(Carnet $carnet): array
    {
        $beneficiario = $carnet->beneficiario;
        $sinCargar = 'Sin cargar en la ficha';
        $anchoValor = self::ANCHO_VALOR;

        /*
         *  LOS SEIS RENGLONES DEL PLASTICO
         */
        $campos = [
            $this->campo('NOMBRE', $beneficiario?->nombreCompleto, 'Sin nombre en la ficha', $anchoValor),

            $this->campo('ASOCIACIÓN', $carnet->asociacion?->nombre, 'Sin asociación declarada', $anchoValor),

            /*
                 * CIUDAD Y PROVINCIA VAN CADA UNA EN SU RENGLON, a pedido.
                 */
            $this->campo('CIUDAD', $beneficiario?->ciudad, $sinCargar, $anchoValor),
            $this->campo('PROVINCIA', $beneficiario?->provincia, $sinCargar, $anchoValor),

            $this->campo('DIRECCIÓN', $beneficiario?->direccion, $sinCargar, $anchoValor),

            /*
                 * EL CÓDIGO CIERRA LA LISTA, como en el plastico, Y LLEVA EL
                 * CUPO AL LADO cuando la actividad se autoriza por volumen.
                 */
            $this->renglonRegistro($carnet),
        ];

        return [
            /*
             * EL TITULO DE LA TARJETA, con la actividad adentro.
             */
            'titulo' => $this->titulo($carnet),

            /*
             * LA CEDULA PASA POR EL MISMO CALCULO QUE LOS RENGLONES.
             */
            'documento' => $this->texto(
                ($documento = trim((string) $beneficiario?->documento_identidad)) !== ''
                    ? 'C.I. '.$documento
                    : null,
                'C.I. —',
                self::ANCHO_CEDULA,
                self::CUERPO_CEDULA,
            ),

            'campos' => $campos,
        ];
    }

    /**
     *  EL TITULO DE LA TARJETA Y EL CUERPO EN EL QUE ENTRA
     *
     * @return array{texto: string, clase: string}
     */
    private function titulo(Carnet $carnet): array
    {
        /*
         * LA ACTIVIDAD SALE DEL ENUM Y NO DEL NOMBRE DEL TIPO DE CARNET.
         */
        $texto = mb_strtoupper('Cédula de '.$carnet->tipo_actor->etiqueta());

        return [
            'texto' => $texto,
            'clase' => mb_strlen($texto) > self::CARACTERES_TITULO ? 'largo' : '',
        ];
    }

    /**
     *  EL CUPO, EN LA COLUMNA DE LA FOTO — o NADA, si la actividad no lleva
     */
    private function cupo(Carnet $carnet): ?array
    {
        /*
         * LO DECIDE EL ENUM, NUNCA EL NOMBRE DEL TIPO DE CARNET. La pesca se
         * autoriza por volumen —tantos kilos, contrastables contra una guía de
         * transporte—; la comercialización no.
         */
        $kilos = $carnet->cupoImpreso();

        if ($kilos === null) {
            return null;
        }

        return $this->texto(
            rtrim(rtrim(number_format($kilos, 2, ',', '.'), '0'), ',').' KG',
            '— KG',
            self::ANCHO_VALOR_ANGOSTO,
            self::CUERPO_VALOR,
        );
    }

    /**
     *  EL ULTIMO RENGLON — REGISTRO, GESTION Y, SI CORRESPONDE, EL CUPO
     *
     * @return array<string, mixed>
     */
    private function renglonRegistro(Carnet $carnet): array
    {
        $cupo = $this->cupo($carnet);

        /*
         *  LA GESTIÓN YA NO SE IMPRIME, Y NO ES UN OLVIDO
         */
        /*
         * EL RÓTULO ES «REGISTRO» Y EL VALOR SU NÚMERO ANUAL —«00001»—, no el
         * código de 16 caracteres: en el plástico entra un número que se puede
         * dictar y buscar en el libro, y el código largo llenaba la tira sin
         * que nadie lo pueda leer de un vistazo. El código sigue existiendo y
         * es el que usa la verificación pública.
         */
        if ($cupo === null) {
            return $this->campo('REGISTRO', $carnet->registro_legible, '—', self::ANCHO_VALOR);
        }

        /*
         * Con cupo, el renglón se parte en dos pares. El CUPO no lleva rótulo
         * propio —«800 KG» se lee solo, la unidad hace de etiqueta— pero acá sí
         * lo lleva, y corto: el rótulo del SEGUNDO par tiene una caja de 32 pt y
         * a 6,1 pt en negrita cada carácter mide ~3,7, así que «CUPO» entra con
         * holgura y «APROVECHAMIENTO» se desbordaría en silencio.
         */
        return $this->campo('REGISTRO', $carnet->registro_legible, '—', self::ANCHO_VALOR_ANGOSTO) + [
            'segundo' => ['rotulo' => 'CUPO', 'alto' => self::ALTO_UNA_LINEA] + $cupo,
        ];
    }

    /**
     * Un campo con rotulo: el texto y el cuerpo en el que entra.
     *
     * @return array{rotulo: string, valor: string, molde: string, cuerpo: float, lineas: int}
     */
    private function campo(string $rotulo, ?string $valor, string $molde, float $disponible): array
    {
        $texto = $this->texto($valor, $molde, $disponible, self::CUERPO_VALOR);

        /*
         * EL ALTO DE LA TIRA LO MANDA EL CONTROLADOR, y no es un detalle: con un
         * alto fijo de un renglón, el valor que pasó a dos líneas se dibujaba
         * igual y la segunda quedaba cortada por la mitad — se veía peor que si
         * nunca hubiera entrado.
         */
        $alto = $texto['lineas'] > 1 ? self::ALTO_DOS_LINEAS : self::ALTO_UNA_LINEA;

        return ['rotulo' => $rotulo, 'alto' => $alto] + $texto;
    }

    /**
     *  UN TEXTO QUE NO ENTRA SE ACHICA; NO SE CORTA
     *
     * @return array{valor: string, molde: string, cuerpo: float, lineas: int}
     */
    private function texto(?string $valor, string $molde, float $disponible, float $cuerpo): array
    {
        $texto = trim((string) $valor);
        $dibujado = $texto !== '' ? $texto : $molde;

        $porPunto = max(0.01, mb_strlen($dibujado) * self::ANCHO_POR_CARACTER);
        $minimo = $cuerpo * self::ENCOGIDO_MAXIMO;

        if ($porPunto * $cuerpo <= $disponible) {
            return ['valor' => $texto, 'molde' => $molde, 'cuerpo' => $cuerpo, 'lineas' => 1];
        }

        $paraUnaLinea = $disponible / $porPunto;

        if ($paraUnaLinea >= $minimo) {
            return ['valor' => $texto, 'molde' => $molde, 'cuerpo' => round($paraUnaLinea, 1), 'lineas' => 1];
        }

        return ['valor' => $texto, 'molde' => $molde, 'cuerpo' => self::CUERPO_DOS_LINEAS, 'lineas' => 2];
    }

    /**
     *  LA FOTO DEL TITULAR, RECORTADA A UN CUADRADO SIN DEFORMARSE
     *
     * @return array{datos: string, estilo: string}|null
     */
    private function fotoEmbebida(?string $ruta): ?array
    {
        $bytes = Archivos::contenido($ruta);

        if ($bytes === null) {
            return null;
        }

        /*
         * El tipo sale de los BYTES y no de la extensión del nombre: el archivo
         * se guardó con un nombre al azar —ver StorageController— y la extensión
         * que mandó el navegador no es prueba de nada. Si lo guardado no resulta
         * ser una imagen, el carnet sale sin foto en vez de con un recuadro roto.
         */
        $medidas = getimagesizefromstring($bytes);
        $tipo = (string) ($medidas['mime'] ?? '');

        if (! str_starts_with($tipo, 'image/')) {
            return null;
        }

        $ancho = (int) ($medidas[0] ?? 0);
        $alto = (int) ($medidas[1] ?? 0);

        // Se reduce ANTES de embeber. Si algo falla —un formato que gd no
        // abre— se sigue con la original: un carnet pesado es mejor que ninguno.
        [$bytes, $tipo, $ancho, $alto] = $this->reducir($bytes, $tipo, $ancho, $alto);

        $anchoCaja = self::ANCHO_FOTO;
        $altoCaja = self::ALTO_FOTO;

        // Se compara la proporcion de la imagen contra la del recuadro: la que
        // sobra por su lado es la que se recorta.
        if ($ancho > 0 && $alto > 0 && ($ancho / $alto) > ($anchoCaja / $altoCaja)) {
            // Mas apaisada que el recuadro: se ajusta por el alto y se
            // recorta a los costados, por igual de los dos lados.
            $dibujado = $altoCaja * $ancho / $alto;
            $estilo = sprintf(
                'width: %.2fpt; height: %.2fpt; margin-left: %.2fpt;',
                $dibujado, $altoCaja, -($dibujado - $anchoCaja) / 2,
            );
        } elseif ($ancho > 0 && $alto > 0) {
            /*
             * Vertical, que es el caso normal de una foto de carnet: se ajusta
             * por el ancho y se recorta arriba y abajo. El corte NO va a la
             * mitad sino a un tercio —`/ 3` en vez de `/ 2`— porque en un
             * retrato la cara está en el tercio superior: partiendo al medio se
             * come la frente y sobra torso.
             */
            $dibujado = $anchoCaja * $alto / $ancho;
            $estilo = sprintf(
                'width: %.2fpt; height: %.2fpt; margin-top: %.2fpt;',
                $anchoCaja, $dibujado, -($dibujado - $altoCaja) / 3,
            );
        } else {
            // Sin medidas legibles: se dibuja al tamano del recuadro.
            $estilo = sprintf('width: %.2fpt; height: %.2fpt;', $anchoCaja, $altoCaja);
        }

        return [
            'datos' => "data:{$tipo};base64,".base64_encode($bytes),
            'estilo' => $estilo,
        ];
    }

    /**
     * Reduce la foto al tamaño en que se va a dibujar.
     *
     * @return array{0: string, 1: string, 2: int, 3: int}
     */
    private function reducir(string $bytes, string $tipo, int $ancho, int $alto): array
    {
        $mayor = max($ancho, $alto);

        if ($mayor <= self::PIXELES_FOTO || $ancho < 1 || $alto < 1) {
            return [$bytes, $tipo, $ancho, $alto];
        }

        $origen = @imagecreatefromstring($bytes);

        if ($origen === false) {
            return [$bytes, $tipo, $ancho, $alto];
        }

        $escala = self::PIXELES_FOTO / $mayor;
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

        // Se conserva la transparencia por si la foto vino en PNG con fondo
        // recortado: sin esto el fondo sale negro.
        imagealphablending($destino, false);
        imagesavealpha($destino, true);

        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        ob_start();
        imagepng($destino, null, 9);
        $reducida = (string) ob_get_clean();

        imagedestroy($origen);
        imagedestroy($destino);

        return [$reducida, 'image/png', $nuevoAncho, $nuevoAlto];
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

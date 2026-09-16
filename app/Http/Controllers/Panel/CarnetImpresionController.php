<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoCarnet;
use App\Http\Controllers\Controller;
use App\Models\Carnet;
use App\Support\Archivos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * ============================================================================
 *  IMPRESIÓN DEL CARNET — la «cédula de pescador»
 * ============================================================================
 *
 * Arma el PDF del plástico y lo manda al navegador. Es el documento que la
 * persona se lleva al final del circuito, y es un CALCO de la cédula que la
 * unidad venía mandando a imprimir: el mismo verde, el mismo encabezado, los
 * mismos seis renglones y el mismo sello de agua. Quien la recibe —y sobre todo
 * el inspector que la revisa en el río— la reconoce por su forma; una versión
 * «mejorada» se lee como si fuera otro documento.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ES UN CONTROLADOR APARTE Y NO UN MÉTODO DE CarnetController
 * ----------------------------------------------------------------------------
 *
 * Mismo criterio que ReciboController: aquel administra filas y devuelve
 * pantallas de Inertia, este dibuja un documento y devuelve bytes. Son dos
 * oficios distintos —uno cambia datos, el otro los maqueta— y mezclarlos dejaba
 * un controlador donde la mitad de los `use` son de impresión.
 *
 * ----------------------------------------------------------------------------
 *  NO ESCRIBE NADA: NI EL PDF, NI EL ESTADO DEL TRÁMITE
 * ----------------------------------------------------------------------------
 *
 * El PDF no se guarda en disco: se deduce entero de la fila del carnet, así que
 * el de mañana sale idéntico al de hoy. Guardarlo sería un archivo más que
 * limpiar —y con el disco en s3, uno que no se puede borrar—. Por eso esto
 * tampoco pasa por StorageController.
 *
 * Y NO marca el trámite como impreso. Eso sigue siendo un acto aparte
 * —`PATCH /tramites/{tramite}/generar`— porque son dos cosas distintas: ver el
 * documento en pantalla no es haberlo sacado en la impresora de credenciales.
 * Si esta ruta marcara, alcanzaría con que alguien abriera la vista previa —o
 * con que el navegador precargara el enlace— para que el expediente quedara
 * declarando un plástico que nunca existió.
 *
 * ----------------------------------------------------------------------------
 *  LO QUE EL PLÁSTICO NO DICE: NI LOS RUBROS NI EL CUPO EN KILOS
 * ----------------------------------------------------------------------------
 *
 * Y es la decisión de fondo del módulo. El carnet es UNO por persona y por
 * gestión, y sobre él se van habilitando rubros: quien hoy es pescador puede
 * sumar comercializador en octubre con una adición, sin que el plástico cambie
 * —mismo registro, misma firma—. Impresos, la lista de rubros y el cupo
 * quedarían viejos el día de esa adición, y el documento diría MENOS de lo que
 * la persona está autorizada a hacer, que es peor que no decir nada.
 *
 * A qué autoriza se consulta ESCANEANDO EL QR, que responde con los rubros
 * habilitados hoy y sin los suspendidos. El cupo se consulta en la ficha del
 * carnet, que es donde la unidad lo contrasta contra las guías — es lo que ya
 * decía la migración de `carnet_rubro`: «No se imprime».
 *
 * Por eso mismo puede ser GET, igual que el recibo.
 */
class CarnetImpresionController extends Controller
{
    /**
     * EL TAMAÑO DEL PLÁSTICO — CR80, la medida de cualquier tarjeta.
     *
     * 85,6 x 54 mm = 242,6 x 153,1 puntos, apaisado. Es lo que mide una cédula
     * de identidad, una tarjeta de crédito y el carnet que la unidad venía
     * mandando a imprimir, así que entra en las impresoras de credenciales y en
     * las fundas que ya se compran.
     *
     * Vive acá y no en la plantilla porque es una decisión de IMPRESIÓN, no de
     * diseño. Ojo: las coordenadas del Blade están calculadas para estos
     * 243 x 153 puntos y hay que revisarlas si esto cambia.
     */
    private const ANCHO = 243.0;

    private const ALTO = 153.0;

    /**
     * IMPRIMIR — GET /panel/carnets/{carnet}/imprimir
     */
    public function imprimir(Carnet $carnet): Response|RedirectResponse
    {
        $carnet->load([
            'beneficiario',
            'habilitaciones.rubro:id,nombre',
        ]);

        /*
         * La regla de si hay algo que imprimir vive en el modelo, y es la misma
         * que le contesta a la pantalla en `puede_imprimirse`. Se vuelve a
         * preguntar acá porque esconder el botón en React es comodidad, no
         * seguridad: la dirección se puede escribir a mano.
         */
        if (! $carnet->puedeImprimirse()) {
            return back()->with('error', $carnet->estado === EstadoCarnet::Anulado
                ? 'El carnet está anulado: no se puede imprimir.'
                : 'El carnet todavía no tiene ningún rubro habilitado. La habilitación nace al aprobar el trámite.');
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
             * ====================================================================
             *  LAS IMÁGENES VAN EMBEBIDAS, Y EL FONDO VIENE HORNEADO
             * ====================================================================
             *
             * En base64 y no como ruta por lo mismo que en el recibo: DomPDF
             * resolvería `/image/...` contra el disco con las restricciones de
             * `chroot` y en producción termina en un recuadro vacío.
             *
             * `carnet-fondo.png` trae el degradado verde Y el sello del SEDAG ya
             * atenuado adentro, en un solo archivo. Son las dos cosas que DomPDF
             * no hace bien: no entiende `linear-gradient` —dibujaría un
             * rectángulo liso— y su `opacity` es tan poco confiable que el sello
             * puede salir a pleno color tapando los datos. Horneadas en el PNG
             * no pueden fallar.
             */
            'fondo' => $this->imagenEmbebida('image/carnet-fondo.png'),

            /*
             * El escudo del encabezado, recortado del lockup de la
             * Gobernación: acá va SOLO el escudo porque el texto —GOBERNACIÓN,
             * BENI— lo escribe la plantilla, como en el plástico de papel.
             *
             * Es una copia reducida por lo de siempre: el original mide 2362 px
             * de lado y pesa 1,8 MB, y embebido en cada carnet el PDF salía
             * inmanejable para una ventanilla que imprime decenas por día.
             */
            'escudo' => $this->imagenEmbebida('image/carnet-escudo.png'),

            /*
             * LA FOTO DEL TITULAR, si la ficha tiene una cargada.
             *
             * Va por Archivos::contenido() y no por su URL porque el servidor
             * tendría que salir a buscarse a sí mismo por HTTP para dibujarla
             * —con su timeout— y eso falla en cualquier despliegue donde el
             * bucket no sea público.
             *
             * Sin foto el carnet SALE IGUAL, con el recuadro vacío: es
             * exactamente lo que hacía la unidad con la cédula de papel cuando
             * la persona traía la foto después. Un error 500 acá dejaría a
             * ventanilla sin poder imprimir nada.
             */
            'foto' => $this->fotoEmbebida($beneficiario?->foto),
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
        return $pdf->stream("carnet-{$carnet->registro()}.pdf");
    }

    /**
     * ========================================================================
     *  LOS SIETE RENGLONES DE LA TARJETA
     * ========================================================================
     *
     * Son los mismos, en el mismo orden, que dibuja la vista previa del panel
     * (`components/panel/tramites/vista-previa-carnet.tsx`). Si se agrega uno
     * acá hay que agregarlo allá, o la vista previa pasa a prometer una tarjeta
     * distinta de la que sale impresa.
     *
     * Cada fila es una LISTA de celdas porque una de ellas —Gestión y Vence—
     * lleva dos: en una CR80 no entran ocho líneas sueltas, y son los dos datos
     * que se leen juntos.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ EL ANCHO DEL RÓTULO SE CALCULA ACÁ
     * ------------------------------------------------------------------------
     *
     * En la pantalla el rótulo mide lo que mide su palabra («CIUDAD:» ocupa
     * menos que «ASOCIACIÓN:») porque es un `flex` con `shrink-0`. DomPDF no
     * tiene flex, y la forma de pedirle lo mismo a una tabla —`width: 1%` más
     * `white-space: nowrap`— la respeta según la versión.
     *
     * Así que se mide acá: 2,4 pt por carácter a 4,3 pt de cuerpo en negrita,
     * más el hueco de los dos puntos. Es una aproximación, y alcanza: si sobra
     * un punto la caja del valor arranca un pelo más a la derecha, no se rompe
     * nada.
     *
     * ------------------------------------------------------------------------
     *  LOS MOLDES
     * ------------------------------------------------------------------------
     *
     * Un renglón sin dato NO sale en blanco: sale con su texto atenuado
     * diciendo qué falta. Es la misma decisión de la vista previa — una caja
     * vacía en una credencial se lee como un error del sistema, y «Sin cargar
     * en la ficha» le dice al operador exactamente dónde ir a completarlo.
     *
     * @return array<int, array<int, array{rotulo: string, valor: string, molde: string, ancho_rotulo: float, ancho_valor?: float}>>
     */
    private function filas(Carnet $carnet): array
    {
        $beneficiario = $carnet->beneficiario;
        $sinCargar = 'Sin cargar en la ficha';

        return [
            [$this->celda('NOMBRE', $beneficiario?->nombreCompleto, 'Sin nombre en la ficha')],
            [$this->celda('ASOCIACIÓN', $carnet->asociacion, 'Sin asociación declarada')],
            [$this->celda('CIUDAD', $beneficiario?->ciudad, $sinCargar)],
            [$this->celda('PROVINCIA', $beneficiario?->provincia, $sinCargar)],
            [$this->celda('DIRECCIÓN', $beneficiario?->direccion, $sinCargar)],

            /*
             * Gestión y Vence comparten renglón —es la única fila con dos
             * celdas—, y el reparto del ancho es el mismo de la vista previa: la
             * fecha necesita más lugar que el año.
             */
            [
                $this->celda('GESTIÓN', (string) $carnet->gestion, '—', 30),
                $this->celda('VENCE', $carnet->fecha_vencimiento?->format('d/m/Y'), '—', 46),
            ],

            [$this->celda('REGISTRO', $carnet->registro(), '000000')],
        ];
    }

    /**
     * ========================================================================
     *  LAS MEDIDAS DE LA COLUMNA DE DATOS
     * ========================================================================
     *
     * En puntos, y calzadas con las coordenadas del Blade. Ver las cuentas del
     * encabezado de esa plantilla.
     */
    private const ANCHO_DATOS = 176.0;

    /**
     * EL ANCHO ÚTIL DE LA TIRA DEL VALOR, en puntos.
     *
     * Es el ancho DECLARADO de la tira en el Blade (126) menos su relleno
     * horizontal (2 + 2). Bajaron al corregir el
     * desborde: la tira ocupa 130 de la columna, pero 126 son de caja y 4 de
     * relleno. Ver el comentario de `.campo .valor` en la plantilla.
     * Tiene que ser ese y no el de la columna entera: calculado sobre la columna
     * —176 menos el rótulo— el sistema creía que entraban treinta caracteres más
     * de los que entran, y los nombres largos salían cortados en vez de
     * achicados, que es justo lo que este cálculo viene a evitar.
     */
    private const ANCHO_VALOR = 121.0;

    /**
     * EL ANCHO UTIL DE CADA MITAD DEL RENGLON PARTIDO, en puntos.
     *
     * El de REGISTRO + GESTION, que es el unico. Misma cuenta que la tira
     * entera: el ancho declarado en el Blade (42) menos su relleno (2 + 2).
     *
     * Los dos valores que caen ahi son cortos y fijos -seis digitos y cuatro-,
     * asi que nunca llegan a encogerse; la medida va igual porque el calculo de
     * texto() la pide, y el dia que ese renglon lleve otra cosa tiene que
     * medirse contra su mitad y no contra la tira completa.
     */
    private const ANCHO_VALOR_ANGOSTO = 38.0;

    /**
     * EL ANCHO UTIL DE LA TIRA DE LA CEDULA, y su cuerpo, en puntos.
     *
     * 43,6 es lo declarado en el Blade, que ya viene de restarle el relleno
     * (2 + 2) a los 47,6 pt que mide el recuadro de la foto con su borde: la
     * tira y la foto cierran contra la misma vertical.
     *
     * El cuerpo es mas grande que el de los renglones -5 contra 4,6- porque la
     * cedula no tiene rotulo que la anuncie: se lee sola.
     */
    private const ANCHO_CEDULA = 43.6;

    private const CUERPO_CEDULA = 5.0;

    /** Las medidas del recuadro de la foto, en puntos. */
    private const ANCHO_FOTO = 46.0;

    private const ALTO_FOTO = 46.0;

    /**
     * A cuantos pixeles de lado se reduce la foto antes de embeberla.
     *
     * El recuadro mide unos 17 mm. A 300 dpi -lo que resuelve una impresora de
     * credenciales- eso son 200 px; 300 deja margen para el recorte y para
     * imprimir mas fino.
     *
     * SIN ESTO EL PDF SE VA DE LAS MANOS: la foto se guarda tal como la subio
     * ventanilla, que es lo que salio de un telefono -dos, tres, cinco
     * megapixeles-. Embebida entera hacia un carnet de 442 KB para dibujar un
     * cuadradito de 17 mm, y la unidad imprime decenas por dia. Es el mismo
     * problema que ya habian dado los PNG del panel en el recibo.
     */
    private const PIXELES_FOTO = 300;

    /**
     * ========================================================================
     *  LA JERARQUIA TIPOGRAFICA
     * ========================================================================
     *
     * No es decoracion: es lo que separa una CREDENCIAL de la impresion de un
     * formulario.
     *
     * En un documento de identidad lo primero que se lee es a QUIEN identifica.
     * Por eso el nombre va al doble de cuerpo que el resto y arriba de todo, con
     * la cedula abajo; los otros datos son secundarios -se leen recien cuando
     * hacen falta- asi que van mas chicos y con el rotulo en un tono apagado.
     *
     * La primera version los ponia a todos del mismo tamano, cada uno en su caja
     * blanca. Se leia como una planilla a medio llenar, y por un motivo
     * concreto: una caja vacia a la derecha de un dato corto es exactamente lo
     * que parece.
     */
    private const CUERPO_VALOR = 6.0;

    /**
     * El cuerpo y el alto de una tira que pasó a DOS líneas.
     *
     * Son fijos y no calculados, y es a propósito: el renglón siguiente está
     * plantado 14 pt más abajo, así que la tira tiene un techo. Con 4,5 pt entran
     * dos líneas holgadas en 12,6 — y a ese cuerpo, en dos líneas, entra un
     * nombre de unos 75 caracteres, más largo que cualquiera del padrón.
     *
     * Calculado «el que haga falta» salían tiras de 15 pt que pisaban el renglón
     * de abajo, o que el `overflow: hidden` cortaba por la mitad — que se ve peor
     * que si el texto nunca hubiera entrado.
     */
    private const CUERPO_DOS_LINEAS = 4.5;

    private const ALTO_DOS_LINEAS = 12.6;

    private const ALTO_UNA_LINEA = 9.5;

    /**
     * Cuanto ocupa cada caracter, en fraccion del cuerpo.
     *
     * ESTE NUMERO VA ATADO AL GRUESO DE LA LETRA DE LA TIRA, y hay que moverlo
     * si ese grueso cambia. Medido sobre las DejaVu Sans que embebe DomPDF, con
     * los textos que salen de verdad en un carnet -nombres, direcciones, los
     * moldes-: la REGULAR promedia 0,539 em por caracter y la NEGRITA 0,605.
     *
     * La tira paso de negrita verde a regular negra -como una cedula de
     * identidad-, asi que esto bajo de 0,62 a 0,55. Dejado en 0,62 no rompia
     * nada, pero sobreestimaba: creia que el texto ocupaba un 13% mas de lo que
     * ocupa, y achicaba nombres que entraban enteros. En un documento que se
     * lee en un control, un nombre mas chico de lo necesario es una perdida.
     *
     * El 0,55 conserva el mismo margen que tenia el 0,62 sobre su promedio
     * -alrededor de un 2%-, y el margen importa: el peor caso medido es 0,67, o
     * sea que un texto de puras mayusculas ocupa bastante mas que el promedio.
     * Quedarse corto seria peor que pasarse, porque lo que no entra lo recorta
     * el `overflow: hidden` de la tira y ahi se pierden apellidos.
     */
    private const ANCHO_POR_CARACTER = 0.55;

    /**
     * Hasta donde se puede achicar un texto que no entra, en fraccion de su
     * cuerpo normal.
     *
     * Por debajo del 70% deja de leerse en una tarjeta de 85 mm que alguien mira
     * en un control, y un apellido que no se puede leer es lo mismo que no
     * imprimirlo: ahi el texto pasa a dos lineas en vez de seguir encogiendo.
     */
    private const ENCOGIDO_MAXIMO = 0.7;

    /**
     * LOS DATOS DE LA TARJETA, ya resueltos aca.
     *
     * La plantilla no decide nada: recibe cada texto con el cuerpo en el que se
     * va a dibujar. Son los mismos campos, en el mismo orden, que muestra la
     * vista previa del panel -- si se agrega uno aca hay que agregarlo alla.
     *
     * @return array<string, mixed>
     */
    private function datos(Carnet $carnet): array
    {
        $beneficiario = $carnet->beneficiario;
        $sinCargar = 'Sin cargar en la ficha';
        $anchoValor = self::ANCHO_VALOR;

        return [
            /*
             * LA CEDULA PASA POR EL MISMO CALCULO QUE LOS RENGLONES.
             *
             * Vive en una tira angosta -la de la foto- y el numero cambia de
             * largo segun la expedicion y el complemento, asi que una cedula
             * larga tiene que achicarse igual que un nombre largo. Recortada
             * seria peor que en cualquier otro campo: un numero de documento al
             * que le falta el final no identifica a nadie.
             */
            'documento' => $this->texto(
                ($documento = trim((string) $beneficiario?->documento_identidad)) !== ''
                    ? 'C.I. '.$documento
                    : null,
                'C.I. —',
                self::ANCHO_CEDULA,
                self::CUERPO_CEDULA,
            ),

            /*
             * LOS SEIS RENGLONES DEL PLASTICO, en el mismo orden que la cedula
             * de papel: nombre, asociacion, domicilio y el numero de registro.
             */
            'campos' => [
                $this->campo('NOMBRE', $beneficiario?->nombreCompleto, 'Sin nombre en la ficha', $anchoValor),
                $this->campo('ASOCIACIÓN', $carnet->asociacion, 'Sin asociación declarada', $anchoValor),
                $this->campo('CIUDAD', $beneficiario?->ciudad, $sinCargar, $anchoValor),
                $this->campo('PROVINCIA', $beneficiario?->provincia, $sinCargar, $anchoValor),
                $this->campo('DIRECCIÓN', $beneficiario?->direccion, $sinCargar, $anchoValor),

                /*
                 * EL REGISTRO CIERRA LA LISTA, como en el plastico, Y LLEVA LA
                 * GESTION AL LADO: es el unico renglon con dos pares.
                 *
                 * Van juntos porque el numero solo no alcanza. El registro se
                 * reinicia con cada gestion -es el id del carnet, y los carnets
                 * son por ano-, asi que el 000002 de 2026 y el de 2027 son dos
                 * credenciales distintas con el mismo numero impreso. Quien lee
                 * el plastico en un control necesita los dos datos a la vez.
                 *
                 * Comparten renglon y no ocupan uno propio porque en una CR80 no
                 * entran siete lineas sueltas sin apretar todo lo demas; el
                 * registro son seis digitos fijos y le sobra media tira.
                 *
                 * NO VA LA FECHA DE VENCIMIENTO, y se saco a pedido: todos los
                 * carnets de una gestion vencen el mismo dia -el 31 de
                 * diciembre, ver Carnet::vencimientoDeGestion()- asi que la
                 * gestion ya lo dice. Y si la pregunta es si HOY vale, la fecha
                 * impresa nunca fue la respuesta: un carnet puede estar anulado
                 * con su fecha intacta. Eso se consulta en el panel.
                 */
                $this->campo('REGISTRO', $carnet->registro(), '000000', self::ANCHO_VALOR_ANGOSTO) + [
                    'segundo' => $this->campo('GESTIÓN', (string) $carnet->gestion, '—', self::ANCHO_VALOR_ANGOSTO),
                ],
            ],
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
     * ========================================================================
     *  UN TEXTO QUE NO ENTRA SE ACHICA; NO SE CORTA
     * ========================================================================
     *
     * Cortar con puntos suspensivos esta bien en una pantalla, donde el dato
     * completo esta a un clic. En el PLASTICO no: "Maria Esperanza del Carmen
     * Justiniano Vaca Guzman de Suarez Vilinga" cortado en "Maria Esperanza del
     * Carmen" pierde los apellidos, que son justamente lo que identifica a la
     * persona en un control.
     *
     * Asi que se calcula con que cuerpo entra, y solo cuando encogerlo lo
     * volveria ilegible se pasa a dos lineas. Dos y no tres: una tercera
     * invadiria el renglon de abajo.
     *
     * EL ANCHO UTIL DE DOS LINEAS NO ES EL DOBLE, y ese fue el error de la
     * primera version: las palabras no se parten, asi que la primera linea corta
     * donde termina la ultima palabra que entra y deja un sobrante de mas o
     * menos el 15%.
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
     * ========================================================================
     *  LA FOTO DEL TITULAR, RECORTADA A UN CUADRADO SIN DEFORMARSE
     * ========================================================================
     *
     * Devuelve los datos de la imagen y el estilo con el que la plantilla la
     * dibuja. NULL si no hay foto, o si no se pudo leer.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ HACE FALTA CALCULAR UN ESTILO
     * ------------------------------------------------------------------------
     *
     * DomPDF NO TIENE `object-fit`. Poniéndole `width` y `height` a la imagen,
     * un retrato vertical metido en el recuadro cuadrado sale APLASTADO: la cara
     * más ancha de lo que es. En un documento de identidad eso no puede pasar —
     * la foto es justamente lo que se compara contra la persona.
     *
     * Así que se hace a mano lo que haría `object-fit: cover`: la imagen se
     * dibuja a su proporción REAL, desbordando el recuadro por el lado que
     * sobra, y corrida con un margen negativo de la mitad de esa diferencia para
     * que quede centrada. El `overflow: hidden` del recuadro recorta lo que
     * asoma.
     *
     * Se recorta y no se encoge porque un retrato que entra completo deja dos
     * franjas blancas al costado y la cara sale más chica todavía; recortado, la
     * cara ocupa el cuadrado entero, que es lo que hace una foto de carnet.
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
     * Devuelve los bytes, el tipo y las medidas resultantes — o los de entrada,
     * tal cual, si no hizo falta reducir o si gd no pudo con el archivo.
     *
     * Sale siempre en PNG cuando se reduce: es sin pérdida, y una foto de
     * carnet de 300 px pesa lo mismo en los dos formatos.
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

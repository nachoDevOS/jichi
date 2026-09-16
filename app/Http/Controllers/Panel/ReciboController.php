<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ConceptoRecibo;
use App\Enums\FormaPago;
use App\Http\Controllers\Controller;
use App\Models\Recibo;
use App\Models\Tramite;
use App\Services\ReciboTramiteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * ============================================================================
 *  IMPRESIÓN DEL RECIBO OFICIAL
 * ============================================================================
 *
 * Arma el PDF del talonario verde y lo devuelve para imprimir. No decide nada:
 * el recibo ya existe —lo emitió ReciboTramiteService cuando el expediente pasó
 * a EN REVISIÓN— y acá solo se dibuja.
 *
 * ----------------------------------------------------------------------------
 *  EL PDF NO SE GUARDA
 * ----------------------------------------------------------------------------
 *
 * Se arma en memoria y se manda al navegador. Guardarlo en disco no agregaría
 * nada: los datos del recibo están congelados en su fila —ver la migración de
 * `recibos`— así que el PDF de mañana sale idéntico al de hoy. Y sí traería el
 * problema de siempre: un archivo más que limpiar cuando el expediente se
 * borra, y que con el disco en s3 no se puede borrar.
 *
 * Por eso este controlador tampoco pasa por StorageController: no escribe nada.
 *
 * ----------------------------------------------------------------------------
 *  REIMPRIMIR DA EL MISMO NÚMERO
 * ----------------------------------------------------------------------------
 *
 * Siempre. El papel ya se entregó, y un segundo recibo con otro número por el
 * mismo pago dejaría a Contabilidad con dos comprobantes que no puede cuadrar.
 * Esa garantía la da el `unique` de `tramite_id` y la idempotencia de
 * ReciboTramiteService::emitir(), no este controlador.
 */
class ReciboController extends Controller
{
    /**
     * Cuántos renglones muestra como mínimo el cuadro «IMPORTE A PAGAR Bs.».
     *
     * El talonario de papel trae el cuadro con su alto fijo, tenga uno o tres
     * cobros anotados. Si el recibo digital lo encogiera cuando hay un solo
     * depósito —el caso más común— cada recibo saldría de un tamaño distinto y
     * la pila archivada se vería despareja.
     *
     * Con más cobros el cuadro CRECE hacia abajo, que es lo que se pidió: el
     * mínimo es un piso, no un techo.
     */
    private const RENGLONES_MINIMOS = 3;

    public function __construct(private readonly ReciboTramiteService $recibos) {}

    /**
     * IMPRIMIR — GET /panel/tramites/{tramite}/recibo
     *
     * Es GET y no PATCH, al revés que los pasos del circuito: acá no se escribe
     * nada. El recibo ya está emitido; esto solo lo dibuja. Que el navegador
     * precargue este enlace no cambia ningún dato.
     */
    public function imprimir(Tramite $tramite): Response|RedirectResponse
    {
        /*
         * Si el expediente ya pasó por revisión y todavía no tiene recibo, se
         * emite acá mismo.
         *
         * Es el caso de los expedientes que cruzaron ese paso ANTES de que
         * existiera este módulo: son reales, la plata se cobró y la gente espera
         * su comprobante. Se fechan con su `fecha_revision` —el día en que se
         * cobró de verdad— y no con la de hoy. Ver
         * ReciboTramiteService::emitirAtrasado().
         */
        $recibo = $this->recibos->emitirAtrasado($tramite);

        if ($recibo === null) {
            // Solo queda un caso: un expediente que nunca llegó a revisión. Ahí
            // todavía no hay nada cobrado que respaldar.
            return back()->with(
                'error',
                'Este trámite todavía no tiene recibo: se emite al tomar el expediente para revisión.',
            );
        }

        return $this->pdf($recibo);
    }

    /**
     * Arma el PDF y lo manda al navegador para imprimir.
     */
    private function pdf(Recibo $recibo): Response
    {
        $pdf = Pdf::loadView('documentos.recibo-oficial', [
            'recibo' => $recibo,
            'fecha' => $recibo->fechaEnCasilleros(),
            'esDeposito' => $recibo->forma_pago === FormaPago::Deposito,

            // Los renglones del cuadro de importes y el total al pie.
            'renglones' => $this->renglones($recibo),
            'total' => $this->importeFormateado((float) $recibo->monto),

            // Cuántos renglones en blanco agregar para que el cuadro conserve su
            // alto aunque haya un solo cobro. Ver RENGLONES_MINIMOS.
            'blancos' => max(0, self::RENGLONES_MINIMOS - count($recibo->lineas())),

            // Las seis casillas del papel, en el orden impreso. Salen del enum y
            // no escritas en la plantilla: agregar una mañana es tocar un lugar.
            'casillas' => ConceptoRecibo::cases(),

            /*
             * ====================================================================
             *  LAS IMÁGENES SON COPIAS A MEDIDA, Y VAN EMBEBIDAS
             * ====================================================================
             *
             * DOS COSAS, Y LAS DOS IMPORTAN.
             *
             * 1. NO SON `icon.png` NI `sedag.png`, que son los originales que usa
             *    el panel: esos miden 2362 y 2048 píxeles de lado y pesan 2,8 MB
             *    entre los dos. Embebidos, CADA recibo salía de 5,4 MB — y
             *    ventanilla imprime decenas por día. Las copias de `recibo-*`
             *    están al tamaño en que se dibujan y pesan 59 KB juntas.
             *
             *    El sello además viene PRE-ATENUADO en el archivo, con el gris
             *    ya horneado. Podría hacerse con `opacity` en la hoja de
             *    estilos, pero es de lo menos confiable que tiene DomPDF: según
             *    la versión lo ignora y el sello sale a pleno color, tapando el
             *    texto del recibo. En el archivo no puede fallar.
             *
             * 2. VAN EN BASE64 Y NO COMO RUTA. DomPDF corre del lado del
             *    servidor y no tiene navegador: una ruta `/image/...` la
             *    resolvería contra el disco con las restricciones de `chroot`, y
             *    en producción —con el proyecto detrás de otro documento raíz—
             *    termina en un recuadro vacío. Embebidas no dependen de nada.
             */
            'escudo' => $this->imagenEmbebida('image/recibo-escudo.png'),
            'selloSedag' => $this->imagenEmbebida('image/recibo-sello.png'),
        ])
            /*
             * MEDIA CARTA APAISADA: 612 x 396 puntos = 8,5" x 5,5".
             *
             * Es el tamaño del talonario de papel, y está acá y no en la
             * plantilla porque es una decisión de impresión, no de diseño. Así
             * el día que la unidad mande a hacer el talonario en otro formato se
             * cambia un número y la plantilla no se entera.
             */
            ->setPaper([0, 0, 612, 396])

            /*
             * ====================================================================
             *  SOLO LAS LETRAS QUE SE USAN — 734 KB de diferencia
             * ====================================================================
             *
             * Sin esto, DomPDF mete DENTRO de cada PDF las dos tipografías
             * COMPLETAS —DejaVu Sans normal y negrita, unas 380 KB cada una—
             * aunque el recibo use ochenta caracteres contados. Medido: el PDF
             * pesaba 930 KB y 734 KB eran las fuentes; las imágenes, 53 KB.
             *
             * Con el subsetting activo se embeben solo los glifos que el
             * documento realmente dibuja. Sigue viéndose igual y sigue
             * imprimiéndose igual, porque los acentos y la «ñ» que hacen falta
             * están entre esos glifos.
             *
             * SE ACTIVA ACÁ Y NO EN config/dompdf.php a propósito. Ese archivo lo
             * publica el paquete y la regla del proyecto es dejarlo tal cual
             * viene: modificarlo hace mucho más difícil compararlo contra la
             * versión nueva cuando el paquete se actualice. Puesto acá, además,
             * queda al lado del documento al que afecta.
             */
            ->setOption('enable_font_subsetting', true);

        // `stream` y no `download`: se abre en el visor del navegador, que es
        // desde donde el operador aprieta imprimir. Un archivo descargado
        // obligaría a buscarlo en la carpeta de descargas y abrirlo aparte.
        return $pdf->stream("recibo-{$recibo->numeroImpreso()}.pdf");
    }

    /**
     * Los renglones del cuadro, cada uno con su importe ya formateado.
     *
     * @return array<int, array{descripcion: string, monto: string}>
     */
    private function renglones(Recibo $recibo): array
    {
        return array_map(fn (array $linea): array => [
            'descripcion' => $linea['descripcion'],
            'monto' => $this->importeFormateado($linea['monto']),
        ], $recibo->lineas());
    }

    /**
     * ========================================================================
     *  EL IMPORTE, EN UNA SOLA COLUMNA
     * ========================================================================
     *
     *      120     ->  '120,00'
     *     1250.5   ->  '1.250,50'
     *
     * Punto para los miles y coma para los decimales, que es como se escribe un
     * monto en Bolivia: 1.250,50 y no 1,250.50.
     *
     * ------------------------------------------------------------------------
     *  LA CIFRA NO SE PARTE — SE PROBARON LAS DOS FORMAS ANTERIORES Y FALLARON
     * ------------------------------------------------------------------------
     *
     * 1. UN DÍGITO POR CASILLERO —`[1][2][0][00]`— se leía **12000**: el espacio
     *    entre celdas rompe el número y la coma decimal desaparece.
     *
     * 2. BOLIVIANOS Y CENTAVOS EN COLUMNAS SEPARADAS —`120 | 00`— se leía mejor,
     *    pero seguía obligando al ojo a juntar dos cifras para entender una.
     *
     * Con el monto completo en una sola celda —`120,00`— no hay nada que juntar.
     * Es un comprobante: la única propiedad que importa es que el número se lea
     * de una y sin ambigüedad.
     */
    private function importeFormateado(float $monto): string
    {
        return number_format($monto, 2, ',', '.');
    }

    /**
     * Una imagen de `public/` como data URI, para que DomPDF la dibuje sin
     * depender de rutas ni de red.
     */
    private function imagenEmbebida(string $rutaRelativa): string
    {
        $ruta = public_path($rutaRelativa);

        if (! is_file($ruta)) {
            // Sin la imagen el recibo sale igual, solo que sin escudo. Es
            // preferible a un error 500 que deje a ventanilla sin poder
            // entregar nada.
            return '';
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($ruta));
    }
}

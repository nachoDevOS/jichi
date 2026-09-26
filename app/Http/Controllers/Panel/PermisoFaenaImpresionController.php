<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\PermisoFaena;
use App\Support\QrVerificacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * PERMISO POR FAENA — el papel que se lleva el pescador.
 *
 * Calca el talonario del SEDAG y sale recién con la faena APROBADA: hasta la
 * firma no hay nada que autorizar. Controlador aparte, como el del carnet y el
 * de la autorización: dibuja un documento y devuelve bytes, no pantallas.
 */
class PermisoFaenaImpresionController extends Controller
{
    /** El pie de firma, literal del talonario. */
    private const RESPONSABLE = 'RESP. PMFRPCRMBIG';

    /**
     * IMPRIMIR — GET /panel/faenas/{faena}/imprimir
     *
     * Una faena vencida o completada SÍ se reimprime: puede hacer falta
     * reponer el papel de una salida ya cerrada.
     */
    public function imprimir(PermisoFaena $faena): Response|RedirectResponse
    {
        // Se vuelve a comprobar acá aunque la pantalla esconda el botón:
        // esconderlo en React es comodidad, la dirección se escribe a mano.
        if (! $faena->yaFueAprobada()) {
            return back()->with('error', 'El permiso sale recién con la faena aprobada.');
        }

        $faena->loadMissing(['codigo', 'carnet.beneficiario']);

        $pdf = Pdf::loadView('documentos.permiso-faena', [
            ...$this->datos($faena),
            'responsable' => self::RESPONSABLE,

            // En base64 y no como ruta: DomPDF resolvería `/image/...` contra
            // el disco con las restricciones de `chroot` y en producción
            // termina en un recuadro vacío.
            'escudo' => $this->imagenEmbebida('image/recibo-escudo.png'),
            // Los peces del talonario en vez del logo del SEDAG: ese ya está
            // en el sello de agua, y repetirlo dejaba el escudo compitiendo
            // con dos versiones del mismo emblema. Silueta plana, dos tintas.
            'peces' => $this->imagenEmbebida('image/faena-peces.png'),
            // Sello propio y no el del recibo: acá se dibuja al doble de
            // tamaño, y el de 200 px salía pixelado y demasiado cargado
            // encima de los renglones. Ver el comentario de `.sello`.
            'selloSedag' => $this->imagenEmbebida('image/faena-sello.png'),

            // El QR y el código, para verificarlo desde el papel.
            'verificacion' => QrVerificacion::de($faena),
        ])
            // CARTA VERTICAL: 612 x 792 puntos = 8,5" x 11".
            ->setPaper([0, 0, 612, 792])
            // Sin esto DomPDF mete las dos tipografías completas —unas 380 KB
            // cada una— en cada documento.
            ->setOption('enable_font_subsetting', true);

        return $pdf->stream("permiso-faena-{$faena->numero_legible}.pdf");
    }

    /**
     * Lo que va en cada renglón del papel.
     *
     * @return array<string, mixed>
     */
    private function datos(PermisoFaena $faena): array
    {
        /*
         * LA FECHA DEL PIE SALE DE `fecha_salida` —el día de la firma— Y NO DE HOY: una
         * reimpresión del mes que viene tiene que decir lo mismo que el papel
         * que el pescador ya tiene en la mano.
         */
        $fecha = $faena->fecha_salida ?? $faena->created_at;

        return [
            'numero' => $faena->numero_legible,
            'recibo' => $faena->recibos()->value('numero_recibo') ?: '',

            // El monto es la COPIA CONGELADA de la fila, no la tarifa de hoy.
            'monto' => number_format($faena->montoACobrar(), 2, ',', '.'),

            // El titular del carnet es el propietario por defecto: el renglón
            // del papel se llena solo cuando la embarcación es de otro.
            'embarcacion' => $faena->embarcacion ?: '',
            'propietario' => $faena->propietario ?: $faena->carnet?->beneficiario?->nombreCompleto ?: '',
            'comandante' => $faena->comandante_barco ?: '',
            'matricula' => $faena->matricula_naval ?: '',
            'kardex' => $faena->nro_kardex ?: '',
            'regionDesde' => $faena->region_desde ?: '',
            'regionHasta' => $faena->region_hasta ?: '',

            'fechaSalida' => $faena->fecha_salida?->format('d/m/Y') ?? '',
            'fechaDesembarque' => $faena->fecha_desembarque?->format('d/m/Y') ?? '',
            'kilos' => number_format((float) $faena->kilos_extraidos, 2, ',', '.'),

            'lugar' => $this->lugar(),
            'fecha' => [
                'dia' => $fecha?->format('d') ?? '',
                'mes' => $fecha ? ucfirst($fecha->translatedFormat('F')) : '',
                'anio' => $fecha?->format('y') ?? '',
            ],
        ];
    }

    private function lugar(): string
    {
        // El papel dice «Trinidad,» a secas: el departamento lo agrega el
        // encabezado, y repetirlo desborda el renglón del pie.
        $lugar = (string) Configuracion::obtener('documentos.lugar_emision', 'Trinidad - Beni');

        return trim(explode('-', $lugar)[0]);
    }

    /**
     * Una imagen del disco, lista para incrustar.
     *
     * Sin el archivo el documento sale igual, solo que sin logo: es preferible
     * a un 500 que deje a ventanilla sin poder entregar nada.
     */
    private function imagenEmbebida(string $rutaRelativa): string
    {
        $ruta = public_path($rutaRelativa);

        return is_file($ruta)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($ruta))
            : '';
    }
}

<?php

namespace App\Http\Controllers\Panel;

use App\Enums\CondicionProducto;
use App\Http\Controllers\Controller;
use App\Models\GuiaDetalle;
use App\Models\GuiaMovimiento;
use App\Support\QrVerificacion;
use App\Support\TextoVertical;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * GUÍA ÚNICA DE TRANSPORTE DE PRODUCTOS ICTÍCOLAS — el papel del camión.
 *
 * Calca el talonario del SEDAG y sale recién con la guía APROBADA: hasta la
 * firma no hay nada que ampare un traslado. Controlador aparte, como el del
 * carnet y el del permiso de faena: dibuja un documento y devuelve bytes.
 */
class GuiaImpresionController extends Controller
{
    /**
     * Los renglones del papel que se llenan con datos del propio documento.
     * La cantidad fija sale del talonario: cinco, y las que sobran van en
     * blanco para que la hoja impresa mida siempre lo mismo.
     */
    private const RENGLONES_MINIMOS = 5;

    /** El cuerpo de los rótulos girados del cuadro D, en puntos. */
    private const CUERPO_ROTULO = 6.0;

    /**
     * IMPRIMIR — GET /panel/guias/{guia}/imprimir
     *
     * Una guía cerrada o vencida SÍ se reimprime: puede hacer falta reponer el
     * papel de un traslado ya cumplido.
     */
    public function imprimir(GuiaMovimiento $guia): Response|RedirectResponse
    {
        // Se vuelve a comprobar acá aunque la pantalla esconda el botón:
        // esconderlo en React es comodidad, la dirección se escribe a mano.
        if (! $guia->yaFueAprobada()) {
            return back()->with('error', 'La guía sale recién con el traslado aprobado.');
        }

        $guia->loadMissing(['codigo', 'carnet.beneficiario', 'detalles']);

        $pdf = Pdf::loadView('documentos.guia-transporte', [
            ...$this->datos($guia),

            // En base64 y no como ruta: DomPDF resolvería `/image/...` contra
            // el disco con las restricciones de `chroot` y en producción
            // termina en un recuadro vacío.
            'escudo' => $this->imagenEmbebida('image/recibo-escudo.png'),
            'peces' => $this->imagenEmbebida('image/faena-peces.png'),
            'selloSedag' => $this->imagenEmbebida('image/faena-sello.png'),

            // El QR y el código, para verificarlo desde el papel.
            'verificacion' => QrVerificacion::de($guia),
        ])
            // CARTA VERTICAL: 612 x 792 puntos = 8,5" x 11".
            ->setPaper([0, 0, 612, 792])
            // Sin esto DomPDF mete las dos tipografías completas —unas 380 KB
            // cada una— en cada documento.
            ->setOption('enable_font_subsetting', true);

        return $pdf->stream("guia-transporte-{$guia->numero_legible}.pdf");
    }

    /**
     * Lo que va en cada renglón del papel.
     *
     * @return array<string, mixed>
     */
    private function datos(GuiaMovimiento $guia): array
    {
        /*
         * LA FECHA DEL ENCABEZADO SALE DE `fecha_emision` Y NO DE HOY: una
         * reimpresión del mes que viene tiene que decir lo mismo que el papel
         * que el comerciante ya tiene en la mano.
         */
        $fecha = $guia->fecha_emision ?? $guia->created_at;
        $beneficiario = $guia->carnet?->beneficiario;

        return [
            'numero' => $guia->numero_legible,
            'recibo' => $guia->recibos()->first()?->numero_recibo ?: '',

            'fecha' => [
                'dia' => $fecha?->format('d') ?? '',
                'mes' => $fecha?->format('m') ?? '',
                'anio' => $fecha?->format('Y') ?? '',
            ],

            //  BLOQUE A — el interesado
            'comerciante' => $beneficiario?->nombreCompleto ?: '',
            'documento' => $beneficiario?->documento_identidad ?: '',

            //  BLOQUE B — la ubicación
            'origen' => [
                'lugar' => $guia->origen ?: '',
                'departamento' => $guia->origen_departamento ?: '',
                'provincia' => $guia->origen_provincia ?: '',
                'distrito' => $guia->origen_distrito ?: '',
            ],
            'destino' => [
                'lugar' => $guia->destino ?: '',
                'departamento' => $guia->destino_departamento ?: '',
                'provincia' => $guia->destino_provincia ?: '',
                'distrito' => $guia->destino_distrito ?: '',
            ],

            //  BLOQUE C — el medio y el vehículo
            'medio' => $guia->medio_transporte?->value,
            'tipoTransporte' => $guia->tipo_transporte?->value,
            'transporte' => [
                'nombre' => $guia->transporte_nombre ?: '',
                'placa' => $guia->transporte_placa ?: '',
                'capacidad' => $guia->transporte_capacidad_kg !== null
                    ? $this->numero((float) $guia->transporte_capacidad_kg)
                    : '',
            ],

            //  BLOQUE D — el cuadro de productos
            'columnas' => CondicionProducto::cases(),
            'rotulos' => $this->rotulosGirados(),
            'renglones' => $this->renglones($guia),
            'totales' => [
                'kilos' => $this->numero((float) $guia->peso_total_kg),
                'importe' => $this->numero((float) $guia->detalles->sum('importe_total')),
            ],

            'observaciones' => $guia->observaciones ?: '',
        ];
    }

    /**
     * Las filas del cuadro D, completadas hasta el mínimo del talonario.
     *
     * Los renglones vacíos van igual: sin ellos, una guía de una sola especie
     * deja el cuadro de una línea y el papel impreso no coincide con la hoja
     * preimpresa que el archivo del SEDAG espera.
     *
     * @return array<int, array<string, mixed>>
     */
    private function renglones(GuiaMovimiento $guia): array
    {
        $filas = $guia->detalles
            ->map(fn (GuiaDetalle $d): array => [
                'especie' => $d->especie,
                'condicion' => $d->condicion->value,
                'cantidad' => $this->numero((float) $d->cantidad_kg),
                'precio' => $this->numero((float) $d->precio_kg),
                'importe' => $this->numero((float) $d->importe_total),
            ])
            ->values()
            ->all();

        $vacia = ['especie' => '', 'condicion' => null, 'cantidad' => '', 'precio' => '', 'importe' => ''];

        while (count($filas) < self::RENGLONES_MINIMOS) {
            $filas[] = $vacia;
        }

        return $filas;
    }

    /**
     * Los rótulos del cuadro D, girados 90° y dibujados como PNG.
     *
     * DomPDF no rota texto, así que se dibujan con GD. Se arman una sola vez y
     * se indexan por su texto: «Entero» sale dos veces en el cuadro —bajo
     * fresco y bajo congelado— y es la misma imagen.
     *
     * @return array<string, array{src: string, ancho: float, alto: float}>
     */
    private function rotulosGirados(): array
    {
        $textos = ['Entero', 'Eviscerado', 'Fileteado', 'Seco', 'Sal Preso', 'Vivos', 'A Granel', 'Otros'];

        return collect($textos)
            ->mapWithKeys(function (string $texto): array {
                [$ancho, $alto] = TextoVertical::medida($texto, self::CUERPO_ROTULO);

                return [$texto => [
                    'src' => TextoVertical::dataUri($texto, self::CUERPO_ROTULO),
                    'ancho' => round($ancho, 2),
                    'alto' => round($alto, 2),
                ]];
            })
            ->all();
    }

    /** Un número como lo escribe el mostrador: 1.250,50. Cero entra vacío. */
    private function numero(float $valor): string
    {
        return $valor == 0.0 ? '' : number_format($valor, 2, ',', '.');
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

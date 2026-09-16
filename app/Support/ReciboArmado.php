<?php

namespace App\Support;

use App\Enums\ConceptoRecibo;
use App\Enums\FormaPago;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * ============================================================================
 *  EL RECIBO OFICIAL, ARMADO AL VUELO
 * ============================================================================
 *
 * El talonario verde del SEDAG, listo para que la plantilla lo dibuje.
 *
 * ----------------------------------------------------------------------------
 *  NO HAY TABLA `recibos`, Y ESO ES UNA DECISIÓN DEL PROYECTO
 * ----------------------------------------------------------------------------
 *
 * La hubo. Guardaba una copia CONGELADA de cada comprobante —nombre, cédula,
 * concepto, monto— con su propio número correlativo, y sobrevivía al borrado
 * del trámite.
 *
 * Se retiró a pedido del responsable: toda esa información ya está en
 * `beneficiarios`, `carnets`, `rubros`, `tramites` y `pagos`, y duplicarla era
 * mantener el mismo dato en dos lugares. Este objeto la vuelve a juntar cada
 * vez que alguien imprime.
 *
 * ----------------------------------------------------------------------------
 *  QUÉ SE PIERDE CON ESO — hay que saberlo antes de tocar acá
 * ----------------------------------------------------------------------------
 *
 * Un recibo generado al vuelo NO es inmutable, y eso tiene tres consecuencias
 * concretas que no son errores sino el precio de la decisión:
 *
 *   1. CORREGIR LA FICHA CAMBIA LOS RECIBOS YA ENTREGADOS. Si mañana se arregla
 *      una tilde del apellido o un dígito de la cédula, la reimpresión sale
 *      distinta al papel que la persona tiene en el bolsillo.
 *
 *   2. BORRAR EL TRÁMITE SE LLEVA EL RECIBO. `SolicitudCarnetService::eliminar()`
 *      borra de verdad, sin `deleted_at`. Antes el recibo quedaba huérfano pero
 *      legible; ahora desaparece. Solo se pueden borrar expedientes PENDIENTES
 *      o EN REVISIÓN, así que el caso es acotado — pero un expediente en
 *      revisión YA entregó su comprobante.
 *
 *   3. EL NÚMERO ES EL DEL TRÁMITE, no una serie propia. Ver `numeroImpreso()`.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ES UN OBJETO DE SOLO LECTURA Y NO UN ARREGLO
 * ----------------------------------------------------------------------------
 *
 * Porque la plantilla Blade pide `$recibo->beneficiario_nombre` y
 * `$recibo->montoEnLetras()`, igual que cuando era un modelo. Con un arreglo
 * habría que reescribir la plantilla entera y perder el autocompletado; con
 * esto, el Blade no se entera de que el recibo dejó de vivir en la base.
 *
 * `readonly` es la parte importante: una vez armado no se toca. Quien necesite
 * otro recibo arma otro.
 */
readonly class ReciboArmado
{
    /** Cuántos dígitos tiene el número impreso: 0016. */
    public const DIGITOS = 4;

    /**
     * @param  array<int, array{descripcion: string, monto: float}>  $detalle  Los cobros, uno por renglón.
     */
    public function __construct(
        public int $numero,
        public int $gestion,
        public string $beneficiario_nombre,
        public ?string $beneficiario_ci,
        public string $concepto,
        public ConceptoRecibo $descripcion,
        public float $monto,
        public FormaPago $forma_pago,
        public ?string $nro_deposito,
        public string $lugar,
        public ?Carbon $fecha_emision,
        public array $detalle,
    ) {}

    /**
     * ========================================================================
     *  EL NÚMERO IMPRESO — el id del trámite, con ceros
     * ========================================================================
     *
     * Antes salía de `CorrelativoService`, con su propia serie por gestión:
     * 0001, 0002, 0003… sin huecos. Al retirarse la tabla `recibos` esa serie
     * se retiró con ella, porque un correlativo es justamente un dato que no se
     * puede derivar: hay que guardarlo en algún lado.
     *
     * Hoy el número ES el del expediente. Sigue siendo único, estable y
     * reimprimible —el id no cambia nunca—, que es lo que un comprobante
     * necesita para poder buscarse.
     *
     * **LA SERIE TIENE HUECOS, y hay que saberlo.** No todo trámite emite
     * recibo: nace al pasar a EN REVISIÓN, así que los expedientes pendientes y
     * los eliminados se saltean. Un talonario que va 0012, 0015, 0016, 0019 es
     * correcto acá, pero NO es lo que Contabilidad espera de un talonario. Si
     * algún día lo exigen, hay que volver a guardar el número —ver
     * `CorrelativoService`, que quedó escrito y sin usar justamente para eso—.
     */
    public function numeroImpreso(): string
    {
        return str_pad((string) $this->numero, self::DIGITOS, '0', STR_PAD_LEFT);
    }

    /**
     * ========================================================================
     *  EL MONTO EN LETRAS — el renglón «La suma de:»
     * ========================================================================
     *
     * Sale así: «OCHENTA 00/100 BOLIVIANOS».
     *
     * El papel trae preimpreso un «-00/100» al final del renglón, que es la
     * forma clásica de cerrar un importe escrito a mano para que nadie pueda
     * agregarle centavos después. Se reproduce igual, con los centavos reales.
     *
     * Number::spell() usa la extensión intl, que ya es requisito del proyecto.
     * Escribir a mano un conversor de número a palabras en castellano son
     * doscientas líneas de casos especiales —«veintiuno», «quinientos»,
     * «un millón»— que ya están resueltas y probadas ahí.
     */
    public function montoEnLetras(): string
    {
        $entero = (int) floor($this->monto);
        $centavos = (int) round(($this->monto - $entero) * 100);

        return mb_strtoupper(sprintf(
            '%s %02d/100 BOLIVIANOS',
            Number::spell($entero, locale: 'es'),
            $centavos,
        ));
    }

    /**
     * Los tres recuadros DIA | MES | AÑO del encabezado.
     *
     * El papel los trae separados, cada uno en su casillero, y así se
     * reproducen. El año va con cuatro dígitos porque el casillero del talonario
     * es ancho y porque un recibo archivado sin siglo se vuelve ambiguo.
     *
     * @return array{dia: string, mes: string, anio: string}
     */
    public function fechaEnCasilleros(): array
    {
        return [
            'dia' => $this->fecha_emision?->format('d') ?? '',
            'mes' => $this->fecha_emision?->format('m') ?? '',
            'anio' => $this->fecha_emision?->format('Y') ?? '',
        ];
    }

    /**
     * ¿Va marcada esta casilla de DESCRIPCIÓN?
     *
     * La plantilla dibuja las seis siempre —el recibo tiene que salir igual al
     * papel— y pregunta acá cuál lleva la cruz.
     */
    public function marca(ConceptoRecibo $casilla): bool
    {
        return $this->descripcion === $casilla;
    }

    /**
     * Los renglones del cuadro «IMPORTE A PAGAR Bs.».
     *
     * Uno por cobro, en el orden en que entraron, y el TOTAL al pie lo pone la
     * plantilla sumándolos. Un pescador que pagó en dos depósitos ve los dos
     * escritos, igual que en el talonario de papel.
     *
     * @return array<int, array{descripcion: string, monto: float}>
     */
    public function lineas(): array
    {
        return $this->detalle;
    }
}

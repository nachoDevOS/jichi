<?php

namespace App\Services;

use App\Enums\EstadoAprovechamiento;
use App\Enums\MetodoPago;
use App\Exceptions\CobroInvalidoException;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\Pago;
use App\Models\Recibo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 *  CAJA — cobrar uno o varios trámites bajo UN recibo
 * ============================================================================
 *
 *     recibo REC-2026-0016 (180 Bs)  ──< pago  80 Bs  → carnet
 *                                    ──< pago 100 Bs  → aprovechamiento
 *
 * Es el tercer circuito del sistema, y atraviesa a los otros dos: se cobran la
 * credencial, el cupo de pesca y la guía de traslado, y los tres se pagan
 * igual.
 *
 * ----------------------------------------------------------------------------
 *  DOS REGLAS QUE SE VEN EN CADA LÍNEA DEL FORMULARIO
 * ----------------------------------------------------------------------------
 *
 *   1. SE PUEDE PAGAR EN CUOTAS. Un carnet de 80 Bs admite dos abonos de 40,
 *      cada uno con su recibo y su fecha. Lo que se debe no está en ninguna
 *      columna: es el precio menos la suma de los abonos, calculada al leer.
 *
 *   2. NO SE COBRA MÁS DE LO QUE SE DEBE. `saldoPendiente()` se corta en cero,
 *      así que un excedente no se acredita a nadie: queda escrito, suma en la
 *      recaudación del día y desaparece. Por eso se rechaza.
 *
 * ----------------------------------------------------------------------------
 *  UN RECIBO, VARIOS TRÁMITES — Y POR ESO LOS PAGOS SON POLIMÓRFICOS
 * ----------------------------------------------------------------------------
 *
 * La persona entrega la plata UNA vez y se lleva UN papel, aunque adentro esté
 * pagando el carnet y el cupo. Una tabla de pagos por cada cosa cobrable
 * obligaría a repetir este circuito tres veces, y peor: el número de recibo
 * dejaría de ser único global, así que el mismo papel podría amparar un carnet
 * y una guía sin que nada lo impida.
 */
class CobrarService
{
    /**
     * La serie del correlativo de caja.
     *
     * Vive acá y no escrita en cada llamada porque `CorrelativoService` entrega
     * números POR SERIE: dos cadenas distintas son dos contadores distintos, y
     * un error de tipeo abriría una serie paralela que nadie pidió, con su
     * propio 0001.
     */
    public const SERIE = 'REC';

    /**
     * Qué se puede cobrar, y cómo lo nombra el formulario.
     *
     * ------------------------------------------------------------------------
     *  ES UNA LISTA BLANCA, Y ESO NO ES DECORACIÓN
     * ------------------------------------------------------------------------
     *
     * `pagos.pagable_type` guarda un nombre de clase. Si el formulario lo
     * mandara directo, cualquiera podría escribir otro en el navegador y el
     * sistema crearía filas apuntando a tablas que no tienen nada que ver.
     *
     * Con esta tabla el formulario manda una palabra corta —`carnet`, `cupo`,
     * `guia`— y el servidor decide a qué clase corresponde.
     *
     * @var array<string, class-string<Model>>
     */
    public const COBRABLES = [
        'carnet' => Carnet::class,
        'cupo' => AprovechamientoPesq::class,
        'guia' => GuiaMovimiento::class,
    ];

    public function __construct(private readonly CorrelativoService $correlativos) {}

    /**
     * Emite un recibo y sus abonos.
     *
     * @param  array<int, array{tipo: string, id: int, monto: float}>  $lineas
     */
    public function cobrar(
        array $lineas,
        MetodoPago $metodo,
        string $nitCi,
        string $nombreFactura,
        ?string $concepto = null,
    ): Recibo {
        if ($lineas === []) {
            throw CobroInvalidoException::sinLineas();
        }

        return DB::transaction(function () use ($lineas, $metodo, $nitCi, $nombreFactura, $concepto): Recibo {
            $resueltas = [];

            foreach ($lineas as $linea) {
                $resueltas[] = $this->resolver($linea);
            }

            /*
             * EL NÚMERO SE RESERVA DENTRO DE LA MISMA TRANSACCIÓN.
             *
             * `CorrelativoService` bloquea la fila del contador con
             * SELECT ... FOR UPDATE, así que dos ventanillas cobrando al mismo
             * tiempo nunca reciben el mismo número. Y si el cobro falla más
             * abajo, el rollback devuelve también el contador — sin eso, cada
             * intento fallido quemaría un número y la serie saldría con huecos
             * que nadie puede explicar.
             */
            $recibo = Recibo::create([
                'numero_recibo' => $this->correlativos->siguiente(self::SERIE),
                'concepto' => $concepto ?: $this->conceptoAutomatico($resueltas),
                'nit_ci_factura' => $nitCi,
                'nombre_factura' => $nombreFactura,
            ]);

            foreach ($resueltas as ['tramite' => $tramite, 'monto' => $monto]) {
                Pago::create([
                    'recibo_id' => $recibo->id,
                    'pagable_type' => $tramite->getMorphClass(),
                    'pagable_id' => $tramite->getKey(),
                    'monto_parcial' => $monto,
                    'metodo_pago' => $metodo,
                ]);
            }

            /*
             * ================================================================
             *  COBRAR UN CUPO ES LO QUE LO ACTIVA
             * ================================================================
             *
             * Un aprovechamiento nace PENDIENTE y la concesión pagada ES la
             * autorización: hasta acá no emitía faenas. Se hace después de
             * escribir los pagos —dentro de la misma transacción— porque el
             * saldo se calcula sumándolos, y antes de escribirlos todavía diría
             * que debe todo.
             */
            foreach ($resueltas as ['tramite' => $tramite]) {
                $this->activarSiQuedoPagado($tramite);
            }

            /*
             * El total se CONGELA acá, con los abonos que acaban de entrar.
             *
             * Recalcularlo al leer haría que el papel entregado cambiara si
             * después se corrige un abono, y lo que se imprimió es lo que la
             * persona pagó. Ver Recibo::cuadra(), que compara lo impreso con lo
             * que hay hoy en vez de taparlo.
             */
            return $recibo->recalcularTotal();
        });
    }

    // ------------------------------------------------------------------
    //  Auxiliares
    // ------------------------------------------------------------------

    /**
     * Convierte una línea del formulario en un trámite real, comprobado.
     *
     * ------------------------------------------------------------------------
     *  LA FILA SE BLOQUEA, Y NO ES DE MÁS
     * ------------------------------------------------------------------------
     *
     * El saldo se calcula sumando los abonos que ya tiene. Dos ventanillas
     * cobrando el mismo carnet a la vez leerían las dos el mismo saldo
     * —ninguna ve el abono de la otra, que todavía no está escrito— y las dos
     * pasarían el control de «no cobrar de más». La persona terminaría pagando
     * el doble, con dos recibos válidos y sin nada que lo delate.
     *
     * @param  array{tipo: string, id: int, monto: float}  $linea
     * @return array{tramite: Model, monto: float}
     */
    private function resolver(array $linea): array
    {
        $clase = self::COBRABLES[$linea['tipo']] ?? null;

        if ($clase === null) {
            throw CobroInvalidoException::tipoDesconocido($linea['tipo']);
        }

        /** @var Model $tramite */
        $tramite = $clase::query()->whereKey($linea['id'])->lockForUpdate()->firstOrFail();

        $nombre = $this->nombrar($tramite);

        /*
         * `admitePagos()` solo lo tienen los estados que pueden decir que no
         * —hoy, la guía anulada—. Los otros no declaran el método, así que se
         * pregunta con method_exists en vez de obligar a los tres enums a
         * tenerlo por simetría.
         */
        $estado = $tramite->estado;

        if (method_exists($estado, 'admitePagos') && ! $estado->admitePagos()) {
            throw CobroInvalidoException::noAdmitePagos($nombre);
        }

        $saldo = $tramite->saldoPendiente();

        if ($saldo <= 0.0) {
            throw CobroInvalidoException::yaEstaPagado($nombre);
        }

        $monto = round((float) $linea['monto'], 2);

        if ($monto > $saldo) {
            throw CobroInvalidoException::excedeElSaldo($nombre, $monto, $saldo);
        }

        return ['tramite' => $tramite, 'monto' => $monto];
    }

    /**
     * El cupo que se termina de pagar pasa de PENDIENTE a ACTIVO.
     *
     * ------------------------------------------------------------------------
     *  SOLO EL APROVECHAMIENTO, Y SOLO SI QUEDÓ EN CERO
     * ------------------------------------------------------------------------
     *
     * El carnet y la guía no tienen un estado que dependa del cobro, así que la
     * comprobación empieza por el tipo. Y se exige el saldo COMPLETO: una cuota
     * no autoriza a pescar, o el pago fraccionado sería una forma de habilitarse
     * pagando un boliviano.
     *
     * `$tramite` es la copia BLOQUEADA que devolvió `resolver()`, así que nadie
     * puede meter otro pago en el medio. Se relee el saldo sin caché —la
     * instancia no trae `withSum`— y por eso ve los abonos recién escritos.
     */
    private function activarSiQuedoPagado(Model $tramite): void
    {
        if (! $tramite instanceof AprovechamientoPesq) {
            return;
        }

        if ($tramite->estado !== EstadoAprovechamiento::Pendiente) {
            return;
        }

        if ($tramite->saldoPendiente() > 0.0) {
            return;
        }

        $tramite->motivoAuditoria = 'Concesión pagada en su totalidad: el aprovechamiento queda habilitado.';
        $tramite->update(['estado' => EstadoAprovechamiento::Activo]);
    }

    /**
     * Cómo se nombra un trámite en el recibo y en los mensajes de error.
     *
     * El `match` va sobre la CLASE y no sobre el texto de `pagable_type`: es el
     * mismo dato, pero así el analizador avisa cuando se agrega un cobrable y
     * este método se olvida.
     */
    private function nombrar(Model $tramite): string
    {
        return match (true) {
            $tramite instanceof Carnet => 'Carnet '.$tramite->codigo_legible,
            $tramite instanceof AprovechamientoPesq => 'Aprovechamiento escala '.($tramite->categoria?->nro_escala ?? '—'),
            $tramite instanceof GuiaMovimiento => 'Guía '.$tramite->codigo_guia,
            default => 'Trámite',
        };
    }

    /**
     * El texto que se imprime cuando el operador no escribe uno.
     *
     * Se arma con los nombres de los trámites cobrados, que es exactamente lo
     * que el papel tiene que decir. Dejarlo vacío haría un comprobante que no
     * explica por qué entró esa plata — y el recibo es justamente el respaldo
     * de eso.
     *
     * @param  array<int, array{tramite: Model, monto: float}>  $resueltas
     */
    private function conceptoAutomatico(array $resueltas): string
    {
        return implode(' · ', array_map(
            fn (array $r): string => $this->nombrar($r['tramite']),
            $resueltas,
        ));
    }
}

<?php

namespace App\Services;

use App\Enums\ConceptoRecibo;
use App\Enums\FormaPago;
use App\Models\Configuracion;
use App\Models\Recibo;
use App\Models\Tramite;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 *  EL RECIBO OFICIAL DEL SEDAG
 * ============================================================================
 *
 * Reemplaza al talonario verde de tres copias —Original: Cliente, Copia
 * Amarilla: Contabilidad, Copia Verde: Archivo—.
 *
 * ----------------------------------------------------------------------------
 *  CUÁNDO NACE
 * ----------------------------------------------------------------------------
 *
 * Al pasar el expediente a EN REVISIÓN, y no antes ni después. Ese es el
 * momento en que, en el mostrador, el pescador ya entregó los papeles y la
 * plata: se va con su recibo en la mano mientras la unidad revisa.
 *
 * Lo dispara SolicitudCarnetService::enviarARevision(), dentro de su misma
 * transacción. Si la toma para revisión se deshace, el recibo tampoco queda — y
 * el número vuelve al contador, porque CorrelativoService participa de esa
 * transacción.
 *
 * ----------------------------------------------------------------------------
 *  SE EMITE UNA SOLA VEZ
 * ----------------------------------------------------------------------------
 *
 * `emitir()` es idempotente: si el trámite ya tiene recibo, devuelve el que
 * tiene y no consume otro número. Hace falta porque el papel ya se entregó —una
 * reimpresión tiene que salir con el MISMO 0016, o el pescador tendría dos
 * recibos distintos por el mismo pago y Contabilidad no podría cuadrarlos—.
 *
 * ----------------------------------------------------------------------------
 *  EL PDF NO SE GUARDA EN DISCO
 * ----------------------------------------------------------------------------
 *
 * Se arma al vuelo cada vez que alguien lo imprime, a partir de esta tabla. Un
 * archivo guardado no agregaría nada —los datos ya están congelados en la fila,
 * ver la migración— y sí traería el problema de siempre: un adjunto más que
 * limpiar cuando el expediente se borra, y que en s3 no se puede borrar.
 */
class ReciboTramiteService
{
    /**
     * La serie del contador. Los recibos llevan la suya, aparte de cualquier
     * otra numeración del sistema, porque es un talonario propio.
     */
    public const SERIE = 'RECIBO';

    public function __construct(private readonly CorrelativoService $correlativos) {}

    /**
     * Emite el recibo del trámite, o devuelve el que ya tenía.
     *
     * Se llama desde dentro de la transacción de enviarARevision(). No abre
     * una propia a propósito: si abriera la suya, el recibo sobreviviría a un
     * rollback de la operación que lo originó y quedaría un número entregado
     * por un trámite que nunca pasó a revisión.
     */
    public function emitir(Tramite $tramite, ?string $fechaEmision = null): Recibo
    {
        $existente = Recibo::query()->where('tramite_id', $tramite->getKey())->first();

        if ($existente !== null) {
            return $existente;
        }

        $gestion = $tramite->carnet?->gestion ?? (int) now()->format('Y');
        $beneficiario = $tramite->carnet?->beneficiario;
        [$formaPago, $nroDeposito] = $this->resolverPago($tramite);

        return Recibo::create([
            'tramite_id' => $tramite->getKey(),

            // El contador bloquea su fila hasta que la transacción de afuera
            // haga commit, así dos ventanillas no pueden sacar el mismo número.
            'numero' => $this->correlativos->siguienteNumero(self::SERIE, $gestion),
            'gestion' => $gestion,

            /*
             * DESDE ACÁ, TODO ES COPIA CONGELADA.
             *
             * Se escribe lo que el papel va a decir HOY. Corregir después el
             * apellido del beneficiario o la tarifa del rubro no reescribe este
             * recibo: el original ya está en manos de alguien. Ver la migración.
             */
            'beneficiario_nombre' => (string) $beneficiario?->nombreCompleto,
            'beneficiario_ci' => $beneficiario?->documento_identidad,

            'concepto' => $this->concepto($tramite),

            // Los renglones del cuadro «IMPORTE A PAGAR Bs.»: uno por depósito.
            // Ver detalle() y la migración que agregó la columna.
            'detalle' => $this->detalle($tramite),

            // La casilla dice QUÉ SE COBRÓ: siempre «Cédulas», porque lo que
            // este sistema emite es el carnet. El rubro habilitado se lee en el
            // renglón «Concepto». Ver App\Enums\ConceptoRecibo.
            'descripcion' => ConceptoRecibo::desdeTramite($tramite),

            /*
             * EL MONTO ES LO COBRADO, NO LO QUE COSTABA.
             *
             * Un recibo respalda plata recibida. Si el pescador pagó en cuotas y
             * todavía debe, el recibo dice lo que entregó —no el total del
             * trámite— porque si no estaría firmando que pagó algo que no pagó.
             *
             * Cuando no hay ningún pago cargado se cae en `monto_requerido`: es
             * el caso de la plata puesta en el mostrador, que llega a revisión
             * sin boleta y que este mismo recibo respalda.
             */
            'monto' => $tramite->montoPagado() > 0
                ? $tramite->montoPagado()
                : (float) $tramite->monto_requerido,

            'forma_pago' => $formaPago,
            'nro_deposito' => $nroDeposito,

            'lugar' => $this->lugar(),

            /*
             * LA FECHA DEL RECIBO ES LA DEL DÍA QUE SE COBRÓ, NO LA DE HOY.
             *
             * En el camino normal son la misma cosa: el recibo nace en el
             * momento en que el expediente pasa a revisión.
             *
             * Se distinguen al emitir uno atrasado —un trámite que ya estaba en
             * revisión desde antes de que este módulo existiera—. Ahí se pasa su
             * `fecha_revision`, que es cuando el pescador entregó la plata de
             * verdad. Ponerle la fecha de hoy haría que el papel declarara un
             * cobro que no ocurrió ese día, y Contabilidad lo cuadraría contra
             * el mes equivocado.
             */
            'fecha_emision' => $fechaEmision ?? now()->toDateString(),
        ]);
    }

    /**
     * ========================================================================
     *  EMITE EL RECIBO DE UN EXPEDIENTE QUE YA ESTABA EN REVISIÓN
     * ========================================================================
     *
     * Devuelve el recibo del trámite, y si todavía no tiene uno pero YA LE
     * CORRESPONDE, lo emite en el momento.
     *
     * ------------------------------------------------------------------------
     *  PARA QUÉ HACE FALTA
     * ------------------------------------------------------------------------
     *
     * El recibo nace solo al pasar a EN REVISIÓN, pero los expedientes que
     * cruzaron ese paso ANTES de que existiera este módulo se quedaron sin
     * ninguno. Son expedientes reales, con su plata cobrada y su gente
     * esperando el comprobante: no pueden quedar sin poder imprimirlo.
     *
     * Lo mismo vale para cualquier trámite que haya llegado a revisión por un
     * camino que no pase por `enviarARevision()` —una migración del padrón en
     * papel, por ejemplo—.
     *
     * ------------------------------------------------------------------------
     *  SE FECHA CUANDO SE COBRÓ, NO CUANDO SE IMPRIME
     * ------------------------------------------------------------------------
     *
     * Con `fecha_revision`, que es el día en que el pescador entregó los papeles
     * y la plata. Ver el comentario de `emitir()`.
     *
     * El número, en cambio, es el que toque HOY en la serie: los correlativos se
     * entregan en orden de emisión y no se pueden intercalar hacia atrás sin
     * pisar uno ya entregado.
     */
    public function emitirAtrasado(Tramite $tramite): ?Recibo
    {
        $existente = $this->para($tramite);

        if ($existente !== null) {
            return $existente;
        }

        if (! $this->corresponde($tramite)) {
            return null;
        }

        return DB::transaction(function () use ($tramite): Recibo {
            $tramite->loadMissing(['carnet.beneficiario', 'rubro']);

            return $this->emitir($tramite, $tramite->fecha_revision?->toDateString());
        });
    }

    /**
     * ¿A este expediente le corresponde un recibo?
     *
     * Sí desde que ventanilla lo ENVIÓ a revisión: ese es el momento en que la
     * plata entró y el pescador se va del mostrador. Antes no —un expediente
     * PENDIENTE todavía se está armando y no hay nada que respaldar—.
     *
     * Se mira `fecha_revision` y no el estado, a propósito: un trámite ya
     * APROBADO o RECHAZADO pasó por revisión en su momento y su recibo sigue
     * correspondiendo. El estado de hoy no borra que se cobró.
     */
    public function corresponde(Tramite $tramite): bool
    {
        return $tramite->fecha_revision !== null;
    }

    /**
     * El recibo de un trámite, si lo tiene.
     *
     * Lo usa la ficha para decidir si dibuja el botón de imprimir, y el
     * controlador antes de armar el PDF.
     */
    public function para(Tramite $tramite): ?Recibo
    {
        return Recibo::query()->where('tramite_id', $tramite->getKey())->first();
    }

    // ------------------------------------------------------------------
    //  Auxiliares
    // ------------------------------------------------------------------

    /**
     * Qué se está cobrando, en el renglón «Concepto:».
     *
     * Se arma con el rubro y la gestión porque son las dos cosas que el
     * pescador necesita poder leer del papel cuando vuelve a preguntar: qué
     * actividad pagó y de qué año.
     */
    private function concepto(Tramite $tramite): string
    {
        $rubro = $tramite->rubro?->nombre ?? 'Trámite de carnet';
        $gestion = $tramite->carnet?->gestion ?? now()->format('Y');

        return sprintf('%s — Carnet gestión %s', $rubro, $gestion);
    }

    /**
     * ========================================================================
     *  LOS RENGLONES DEL CUADRO DE IMPORTES
     * ========================================================================
     *
     * Uno por depósito, en el orden en que entraron:
     *
     *     Depósito 6CF39608ECB2      120,00
     *     Depósito 7712004455         50,00
     *     ─────────────────────────────────
     *     TOTAL                      170,00
     *
     * Se copian al recibo y no se leen de `pagos` al imprimir. El motivo está en
     * la migración y vale la pena tenerlo presente: un trámite SIGUE ACEPTANDO
     * depósitos después de pasar a revisión, así que leerlos al imprimir haría
     * que una reimpresión mostrara plata que entró DESPUÉS de haberse entregado
     * el papel.
     *
     * SIN NINGÚN DEPÓSITO va un solo renglón con lo que el trámite costaba: es
     * la plata del mostrador, la que se paga en efectivo y que este mismo recibo
     * respalda.
     *
     * @return array<int, array{descripcion: string, monto: float}>
     */
    private function detalle(Tramite $tramite): array
    {
        $pagos = $tramite->pagos()->orderBy('fecha_pago')->get();

        if ($pagos->isEmpty()) {
            return [[
                'descripcion' => $tramite->rubro?->nombre ?? 'Trámite de carnet',
                'monto' => (float) $tramite->monto_requerido,
            ]];
        }

        return $pagos->map(fn ($pago): array => [
            // El número de la boleta es lo que permite cruzar este renglón con
            // el extracto del banco. Sin él, dos depósitos del mismo monto en el
            // mismo día son indistinguibles.
            'descripcion' => filled($pago->nro_transaccion)
                ? 'Depósito '.$pago->nro_transaccion
                : 'Depósito',
            'monto' => (float) $pago->monto,
        ])->values()->all();
    }

    /**
     * Cuál de las dos casillas marcar, y con qué número.
     *
     * Si el expediente tiene depósitos cargados se marca «Depósito Bancario» y
     * se anotan sus números de transacción; si no tiene ninguno, es plata del
     * mostrador y se marca «Efectivo».
     *
     * Los números van todos y separados por coma cuando hay varios: el papel
     * tiene un solo campo «N°», pero un recibo que solo nombre una de las dos
     * boletas deja la otra sin respaldo escrito.
     *
     * @return array{0: FormaPago, 1: string|null}
     */
    private function resolverPago(Tramite $tramite): array
    {
        $transacciones = $tramite->pagos()
            ->orderBy('fecha_pago')
            ->pluck('nro_transaccion')
            ->filter()
            ->all();

        if ($transacciones === []) {
            return [FormaPago::Efectivo, null];
        }

        return [FormaPago::Deposito, implode(', ', $transacciones)];
    }

    /**
     * El «Lugar» del encabezado.
     *
     * Sale de Configuración y no escrito acá porque la misma unidad atiende
     * desde más de una oficina, y el día que se abra otra ventanilla el lugar
     * se cambia desde el panel. Trinidad es la capital del Beni y es donde está
     * la oficina de hoy: sirve de valor por defecto.
     */
    private function lugar(): string
    {
        return (string) Configuracion::obtener('documentos.lugar_emision', 'Trinidad - Beni');
    }
}

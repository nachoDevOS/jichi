<?php

namespace App\Services;

use App\Enums\ConceptoRecibo;
use App\Enums\EstadoTramite;
use App\Enums\FormaPago;
use App\Models\Configuracion;
use App\Models\Tramite;
use App\Support\ReciboArmado;

/**
 * ============================================================================
 *  ARMA EL RECIBO OFICIAL — el talonario verde del SEDAG
 * ============================================================================
 *
 * ----------------------------------------------------------------------------
 *  ESTE SERVICIO YA NO GUARDA NADA
 * ----------------------------------------------------------------------------
 *
 * Antes emitía: escribía una fila en `recibos` con una copia congelada del
 * comprobante y un número correlativo propio. Esa tabla se retiró a pedido del
 * responsable —toda su información ya vive en `beneficiarios`, `carnets`,
 * `rubros`, `tramites` y `pagos`— así que hoy este servicio RECONSTRUYE el
 * recibo cada vez que alguien lo pide.
 *
 * Lo que eso cambia, en una línea: el recibo dejó de ser un documento guardado
 * y pasó a ser una VISTA de los datos actuales. Las consecuencias están
 * anotadas en App\Support\ReciboArmado, y conviene leerlas antes de tocar acá.
 *
 * ----------------------------------------------------------------------------
 *  CUÁNDO EXISTE UN RECIBO
 * ----------------------------------------------------------------------------
 *
 * Cuando el expediente pasó por EN REVISIÓN, y no antes. Ese es el momento en
 * que el pescador entregó los papeles y la plata y se fue con su comprobante.
 * Lo marca `tramites.fecha_revision`, que es además la fecha que va impresa:
 * el dato ya estaba guardado, así que no se perdió nada al sacar la tabla.
 *
 * Un expediente PENDIENTE no tiene recibo porque todavía no se presentó nada.
 */
class ReciboTramiteService
{
    /*
     * NO HAY CorrelativoService ACÁ, Y ANTES SÍ.
     *
     * Le pedía el número de la serie 'RECIBO' por gestión. Un correlativo es
     * justamente lo que NO se puede derivar —hay que guardarlo en algún lado—
     * así que al sacar la tabla se fue con ella: hoy el número impreso es el id
     * del trámite. Ver ReciboArmado::numeroImpreso(), que explica qué se pierde.
     *
     * CorrelativoService queda escrito y sin usar, como ya le pasó una vez.
     */

    /**
     * ¿A este expediente le corresponde un recibo?
     *
     * Se mira `fecha_revision` y no el estado, porque el estado sigue avanzando
     * —aprobado, rechazado— y el recibo ya se entregó igual. Un trámite
     * rechazado conserva su comprobante: la plata entró.
     */
    public function corresponde(Tramite $tramite): bool
    {
        return $tramite->fecha_revision !== null
            || $tramite->estado !== EstadoTramite::Pendiente;
    }

    /**
     * ========================================================================
     *  ARMA EL RECIBO DE ESTE EXPEDIENTE, O NULL SI NO LE TOCA
     * ========================================================================
     *
     * Devuelve null cuando el trámite todavía está PENDIENTE: no hay papel que
     * imprimir porque nadie presentó nada. Quien llama decide qué hacer con eso
     * —el controlador devuelve un aviso, la ficha muestra «todavía no»—.
     *
     * Carga las relaciones que necesita con `loadMissing`: si quien llamó ya las
     * trajo con `with()`, no se vuelve a consultar.
     */
    public function armar(Tramite $tramite): ?ReciboArmado
    {
        if (! $this->corresponde($tramite)) {
            return null;
        }

        $tramite->loadMissing(['carnet.beneficiario', 'rubro', 'pagos']);

        $beneficiario = $tramite->carnet?->beneficiario;
        [$formaPago, $nroDeposito] = $this->resolverPago($tramite);

        return new ReciboArmado(
            // El id del expediente hace de número. Ver numeroImpreso().
            numero: (int) $tramite->getKey(),
            gestion: $tramite->carnet?->gestion ?? (int) now()->format('Y'),

            beneficiario_nombre: (string) $beneficiario?->nombreCompleto,
            beneficiario_ci: $beneficiario?->documento_identidad,

            concepto: $this->concepto($tramite),

            // La casilla dice QUÉ SE COBRÓ: siempre «Cédulas», porque lo que
            // este sistema emite es el carnet. El rubro habilitado se lee en el
            // renglón «Concepto». Ver App\Enums\ConceptoRecibo.
            descripcion: ConceptoRecibo::desdeTramite($tramite),

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
            monto: $tramite->montoPagado() > 0
                ? $tramite->montoPagado()
                : (float) $tramite->monto_requerido,

            forma_pago: $formaPago,
            nro_deposito: $nroDeposito,

            lugar: $this->lugar(),

            /*
             * LA FECHA DEL PAPEL ES LA DE REVISIÓN, no la de hoy.
             *
             * Es el dato que salvó la eliminación de la tabla: `fecha_revision`
             * ya guardaba el momento exacto en que se entregó el comprobante, así
             * que una reimpresión de marzo sigue diciendo marzo.
             *
             * Se cae a `fecha_solicitud` para los expedientes que cruzaron ese
             * paso antes de que existiera la columna.
             */
            fecha_emision: $tramite->fecha_revision ?? $tramite->fecha_solicitud,

            detalle: $this->detalle($tramite),
        );
    }

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

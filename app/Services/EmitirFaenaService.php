<?php

namespace App\Services;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoFaena;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\PermisoFaena;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 *  PASO 4 DEL FLUJO — el PERMISO DE FAENA, una salida de pesca
 */
class EmitirFaenaService
{
    public function __construct(private readonly CorrelativoService $correlativos) {}

    /**
     * Registra la solicitud de una salida. NACE PENDIENTE: no autoriza nada
     * hasta que se cobre el arancel y alguien la firme.
     *
     * @param  array<string, string|null>  $papel  Los renglones del talonario:
     *                                             embarcacion, propietario, comandante_barco,
     *                                             matricula_naval, nro_kardex, region_desde,
     *                                             region_hasta.
     */
    public function emitir(
        Carnet $carnet,
        float $kilos,
        ?Carbon $salida = null,
        ?Carbon $desembarque = null,
        array $papel = [],
    ): PermisoFaena {
        $salida ??= now();
        // Sin fecha del papel, la ventana es el plazo entero de la resolución.
        $desembarque ??= PermisoFaena::limiteDesde($salida);

        /*
         * LAS COMPROBACIONES DEL CARNET VAN ANTES DE LA TRANSACCIÓN, y las del
         * cupo adentro. La diferencia es qué puede cambiar mientras tanto: el
         * carnet no se revoca en el medio de esta operación, pero el saldo sí
         * puede moverlo otra ventanilla en el mismo segundo.
         */
        if (! $carnet->tipo_actor->emiteFaenas()) {
            throw PermisoOperativoException::actorNoEmite('permisos de faena', $carnet->tipo_actor);
        }

        if (! $carnet->estaVigente()) {
            throw PermisoOperativoException::carnetNoVigente($carnet->estado);
        }

        if ($carnet->aprovechamiento_id === null) {
            throw PermisoOperativoException::sinCupoVigente();
        }

        return DB::transaction(function () use ($carnet, $kilos, $salida, $desembarque, $papel): PermisoFaena {
            // La fila del cupo es la que contiene el recurso escaso: es la que
            // se bloquea. Releerla devuelve OTRA instancia, y acá se usa esa a
            // propósito — es la que tiene el saldo al día.
            $cupo = AprovechamientoPesq::query()
                ->whereKey($carnet->aprovechamiento_id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * SE PREGUNTA POR LA FECHA, NO POR `estaVigente()`.
             */
            if (! $cupo->estaEnFecha()) {
                throw PermisoOperativoException::sinCupoVigente();
            }

            /*
             * EL CUPO SIN COBRAR TIENE SU PROPIO MENSAJE, y hace falta.
             */
            if ($cupo->estado === EstadoAprovechamiento::Pendiente) {
                throw PermisoOperativoException::cupoPendienteDePago();
            }

            /*
             * Y EL OTRO ESTADO QUE NO HABILITA: presentado y sin firmar.
             */
            if ($cupo->estado === EstadoAprovechamiento::EnRevision) {
                throw PermisoOperativoException::cupoEnRevision();
            }

            /*
             * EL TOPE SOLO SE HACE CUMPLIR EN MODO ESTRICTO.
             *
             * ⚠️ ACÁ YA NO ES UNA RESERVA, es un aviso temprano: como las
             * pendientes no descuentan, dos solicitudes por el volumen entero
             * pasan las dos. El control que decide de verdad corre al APROBAR.
             */
            if (AprovechamientoPesq::modoEstricto()) {
                $saldo = $cupo->saldoKg();

                if ($kilos > $saldo) {
                    throw PermisoOperativoException::excedeCupo($kilos, $saldo);
                }
            }

            $faena = PermisoFaena::create([
                'carnet_id' => $carnet->id,
                // EL NÚMERO LO PONE EL SISTEMA, no el operador: correlativo
                // global y continuo, como el talonario de papel.
                'numero_faena' => $this->correlativos->siguienteContinuo(PermisoFaena::SERIE),
                // Copia congelada del arancel: ver PermisoFaena::montoACobrar().
                'monto' => PermisoFaena::tarifaVigente(),
                'kilos_extraidos' => $kilos,
                ...$this->renglonesDelPapel($papel),
                // PENDIENTE, como el carnet y el cupo: la emisión la escribe
                // la aprobación, y hasta entonces esto es una solicitud.
                'estado' => EstadoFaena::Pendiente,
                'fecha_solicitud' => now()->toDateString(),
                'fecha_salida' => $salida->toDateString(),
                'fecha_desembarque' => $desembarque->toDateString(),
                // Se GUARDA la fecha calculada en vez de derivarla al leer: si
                // mañana la resolución baja el plazo, los permisos ya emitidos
                // tienen que seguir venciendo cuando dice el papel que el
                // pescador tiene en la mano.
                'fecha_limite' => PermisoFaena::limiteDesde($salida)->toDateString(),
            ]);

            /*
             * EL CUPO NO SE TOCA ACÁ. Una faena nace PENDIENTE y una pendiente
             * ya no descuenta —ver EstadoFaena::consumeCupo()—, así que no hay
             * nada que agotar: eso pasa recién al firmarla.
             */

            return $faena;
        });
    }

    /**
     *  CORREGIR EL BORRADOR
     *
     * El CARNET no se toca: cambiar de titular no es corregir una salida, es
     * emitir otra. Dejarlo editable movería un permiso de una persona a otra
     * sin más rastro que la auditoría.
     *
     * @param  array<string, string|null>  $papel  Los renglones del talonario.
     */
    public function editar(
        PermisoFaena $faena,
        float $kilos,
        Carbon $salida,
        Carbon $desembarque,
        array $papel = [],
    ): PermisoFaena {
        return DB::transaction(function () use ($faena, $kilos, $salida, $desembarque, $papel): PermisoFaena {
            $bloqueada = PermisoFaena::query()->whereKey($faena->id)->lockForUpdate()->firstOrFail();

            // Se comprueba con la copia bloqueada, no con la que llegó: entre
            // que la pantalla se dibujó y llegó el submit, otra ventanilla
            // pudo enviarla a revisión.
            if (! $bloqueada->estado->permiteEdicion()) {
                throw PermisoOperativoException::faenaNoSePuedeEditar(
                    mb_strtolower($bloqueada->estado->etiqueta()),
                );
            }

            if (($pagos = $bloqueada->pagos()->count()) > 0) {
                throw PermisoOperativoException::faenaTienePagos($pagos);
            }

            $bloqueada->loadMissing('carnet');

            $cupo = AprovechamientoPesq::query()
                ->whereKey($bloqueada->carnet?->aprovechamiento_id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * LOS KILOS PROPIOS SE SUMAN DE VUELTA SOLO SI DESCONTABAN.
             *
             * Desde que la pendiente dejó de descontar, `saldoKg()` ya NO la
             * está restando: devolvérselos igual contaría dos veces el mismo
             * volumen y dejaría pasar el doble del cupo. Se corrige una faena
             * pendiente, así que hoy la rama de abajo no suma nada — se
             * pregunta igual para que siga valiendo si la edición se abre en
             * otro estado.
             */
            if (AprovechamientoPesq::modoEstricto()) {
                $disponible = $cupo->saldoKg()
                    + ($bloqueada->consumeCupo() ? (float) $bloqueada->kilos_extraidos : 0.0);

                if ($kilos > $disponible) {
                    throw PermisoOperativoException::excedeCupo($kilos, $disponible);
                }
            }

            $bloqueada->update([
                'kilos_extraidos' => $kilos,
                ...$this->renglonesDelPapel($papel),
                'fecha_salida' => $salida->toDateString(),
                'fecha_desembarque' => $desembarque->toDateString(),
                // El límite se recalcula: cuelga de la salida, y si la salida
                // se corrigió el papel vence otro día.
                'fecha_limite' => PermisoFaena::limiteDesde($salida)->toDateString(),
            ]);

            // La corrección pudo agotar el cupo o destrabarlo.
            $this->sincronizarEstadoDelCupo($cupo->fresh());

            // La original refrescada, no la copia bloqueada. Ver CLAUDE.md.
            return $faena->refresh();
        });
    }

    /**
     *  ELIMINAR UNA FAENA CARGADA POR ERROR
     *
     * Devuelve sus kilos a la bolsa madre: una pendiente los tenía reservados.
     */
    public function eliminar(PermisoFaena $faena, string $motivo): void
    {
        DB::transaction(function () use ($faena, $motivo): void {
            $bloqueada = PermisoFaena::query()->whereKey($faena->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueada->estado->permiteEliminacion()) {
                throw PermisoOperativoException::faenaNoSePuedeEliminar(
                    mb_strtolower($bloqueada->estado->etiqueta()),
                );
            }

            if (($pagos = $bloqueada->pagos()->count()) > 0) {
                throw PermisoOperativoException::faenaTienePagos($pagos);
            }

            $bloqueada->loadMissing('carnet');

            $cupo = AprovechamientoPesq::query()
                ->whereKey($bloqueada->carnet?->aprovechamiento_id)
                ->lockForUpdate()
                ->first();

            /*
             * EL MOTIVO SE DEJA EN EL MODELO Y SE BORRA: el trait `Auditable`
             * ya engancha el `deleted`, y llamar además a `registrarAuditoria()`
             * dejaría el mismo borrado dos veces, una sin explicación.
             */
            $bloqueada->motivoAuditoria = $motivo;
            $bloqueada->delete();

            /*
             * EL NÚMERO DEL TALONARIO NO SE REUSA. La baja es lógica y el
             * correlativo sigue donde estaba: la serie queda con un hueco, que
             * es justamente lo que el motivo en la auditoría explica.
             */

            // Sus kilos vuelven a la bolsa: un cupo agotado puede destrabarse.
            if ($cupo !== null) {
                $this->sincronizarEstadoDelCupo($cupo->fresh());
            }
        });
    }

    /**
     * Registra que el pescador volvió y descargó.
     */
    public function completar(PermisoFaena $faena, ?float $kilosReales = null): PermisoFaena
    {
        if ($faena->estado !== EstadoFaena::Activo) {
            throw PermisoOperativoException::noSePuedeCompletar(
                mb_strtolower($faena->estado->etiqueta()),
            );
        }

        return DB::transaction(function () use ($faena, $kilosReales): PermisoFaena {
            // El cupo se alcanza por el carnet: la faena ya no lo guarda.
            $faena->loadMissing('carnet');

            $cupo = AprovechamientoPesq::query()
                ->whereKey($faena->carnet?->aprovechamiento_id)
                ->lockForUpdate()
                ->firstOrFail();

            $bloqueada = PermisoFaena::query()->whereKey($faena->id)->lockForUpdate()->firstOrFail();

            $cambios = ['estado' => EstadoFaena::Completado];

            if ($kilosReales !== null && abs($kilosReales - (float) $bloqueada->kilos_extraidos) > 0.001) {
                /*
                 * El saldo se mide SIN esta faena: está APROBADA, así que
                 * `saldoKg()` ya la está descontando y comparar contra el
                 * saldo pelado rechazaría hasta una corrección hacia abajo.
                 */
                $disponible = $cupo->saldoKg()
                    + ($bloqueada->consumeCupo() ? (float) $bloqueada->kilos_extraidos : 0.0);

                if ($kilosReales > $disponible) {
                    throw PermisoOperativoException::excedeCupo($kilosReales, $disponible);
                }

                $cambios['kilos_extraidos'] = $kilosReales;
            }

            $bloqueada->update($cambios);

            // El cupo puede haber quedado agotado —o haberse destrabado, si la
            // corrección fue hacia abajo—, así que se recalcula el estado.
            $this->sincronizarEstadoDelCupo($cupo->fresh());

            // Se devuelve la instancia ORIGINAL refrescada: quien llamó tiene
            // esa en la mano, y darle la copia bloqueada lo deja con el estado
            // viejo en memoria.
            return $faena->refresh();
        });
    }

    /**
     * Los renglones del talonario, normalizados: '' entra como null.
     *
     * @param  array<string, string|null>  $papel
     * @return array<string, string|null>
     */
    private function renglonesDelPapel(array $papel): array
    {
        $campos = [
            'embarcacion', 'propietario', 'comandante_barco',
            'matricula_naval', 'nro_kardex', 'region_desde', 'region_hasta',
        ];

        return collect($campos)
            ->mapWithKeys(fn (string $c): array => [$c => trim((string) ($papel[$c] ?? '')) ?: null])
            ->all();
    }

    /**
     * Pone el cupo en `agotado` o lo devuelve a `aprobado` según su saldo real.
     *
     * La regla vive en el MODELO porque la comparten dos servicios: este y el
     * que firma las faenas, que es donde ahora se consume el volumen.
     */
    private function sincronizarEstadoDelCupo(AprovechamientoPesq $cupo): void
    {
        $cupo->sincronizarEstadoPorSaldo();
    }
}

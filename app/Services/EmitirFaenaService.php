<?php

namespace App\Services;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoFaena;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\PermisoFaena;
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
        array $papel = [],
    ): PermisoFaena {

        // El carnet se comprueba ANTES de la transacción y el cupo adentro: el
        // carnet no se revoca en el medio, el saldo sí puede moverlo otro.
        if (! $carnet->tipo_actor->emiteFaenas()) {
            throw PermisoOperativoException::actorNoEmite('permisos de faena', $carnet->tipo_actor);
        }

        if (! $carnet->estaVigente()) {
            throw PermisoOperativoException::carnetNoVigente($carnet->estado);
        }

        if ($carnet->aprovechamiento_id === null) {
            throw PermisoOperativoException::sinCupoVigente();
        }

        return DB::transaction(function () use ($carnet, $kilos, $papel): PermisoFaena {
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

            // Solo en modo ESTRICTO, y es un aviso temprano y no una reserva: dos
            // solicitudes por el volumen entero pasan las dos. El control de
            // verdad corre al APROBAR.
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
                // Salida y desembarque quedan en NULL: los escribe la aprobación.
                'fecha_solicitud' => now()->toDateString(),
            ]);

            // Su llave pública, en la misma transacción: sin código, el documento
            // no se puede verificar.
            $faena->asignarCodigo();

            // El cupo NO se toca acá: una faena nace PENDIENTE y la pendiente ya
            // no descuenta. Eso pasa al firmarla.

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
        array $papel = [],
    ): PermisoFaena {
        return DB::transaction(function () use ($faena, $kilos, $papel): PermisoFaena {
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

            // Se suman de vuelta SOLO si descontaban: la pendiente ya no resta, y
            // devolvérselos contaría dos veces el mismo volumen. Hoy la rama no
            // suma nada; se pregunta por si la edición se abre en otro estado.
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

            // El motivo se deja y se borra: `Auditable` ya engancha el `deleted`,
            // y registrarlo a mano además dejaría el hecho dos veces.
            $bloqueada->motivoAuditoria = $motivo;
            $bloqueada->delete();

            // El número NO se reusa: la serie queda con un hueco, que es lo que
            // el motivo en la auditoría explica.

            // Sus kilos vuelven a la bolsa: un cupo agotado puede destrabarse.
            if ($cupo !== null) {
                $this->sincronizarEstadoDelCupo($cupo->fresh());
            }
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

<?php

namespace App\Services;

use App\Enums\EstadoAprovechamiento;
use App\Exceptions\CupoInvalidoException;
use App\Models\AprovechamientoPesq;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 *  EL CIRCUITO DE REVISIÓN DE UN APROVECHAMIENTO
 * ============================================================================
 *
 *     PENDIENTE ──[enviar, con el monto cubierto]──▶ EN REVISIÓN
 *     (borrador)                                         │
 *          ▲                              ┌──────────────┴──────────────┐
 *          └──────────[rechazar]──────────┤                             │
 *                                    [aprobar]                          │
 *                                         │                             │
 *                                      ACTIVO ──▶ recién acá emite faenas
 *
 * ----------------------------------------------------------------------------
 *  ENVIAR NO ES APROBAR, Y SON DOS PERSONAS DISTINTAS
 * ----------------------------------------------------------------------------
 *
 * Ventanilla carga los depósitos y declara que el expediente está completo;
 * quien firma mira las boletas contra el extracto del banco y recién ahí el cupo
 * queda habilitado. Sin el paso del medio, la plata entraba y el pescador salía
 * a pescar sin que nadie hubiera mirado nada — que es exactamente lo que este
 * circuito viene a impedir.
 *
 * Por eso son PERMISOS distintos: `aprovechamientos.enviar` es de ventanilla y
 * `aprovechamientos.aprobar` es de supervisión.
 *
 * ----------------------------------------------------------------------------
 *  RECHAZADO NO ES EL FINAL: VUELVE A PENDIENTE
 * ----------------------------------------------------------------------------
 *
 * Rechazar es devolverle el expediente a ventanilla con el motivo escrito, y lo
 * que sigue es que lo corrijan y lo vuelvan a presentar. Devolverlo a PENDIENTE
 * es lo que permite eso: los pagos ya cargados SIGUEN AHÍ —cuelgan del cupo, no
 * del envío— así que nadie tiene que volver a cargarlos.
 *
 * El rechazo queda en `auditorias` con su motivo; el estado no lo recuerda, y no
 * hace falta que lo recuerde.
 */
class RevisarCupoService
{
    /**
     * PENDIENTE ──▶ EN REVISIÓN.
     *
     * Las dos condiciones se comprueban con la fila BLOQUEADA: entre que el
     * operador ve el botón encendido y lo aprieta, otra ventanilla pudo anular
     * un pago y dejar el cupo sin cubrir.
     */
    public function enviar(AprovechamientoPesq $cupo): AprovechamientoPesq
    {
        return DB::transaction(function () use ($cupo): AprovechamientoPesq {
            $bloqueado = AprovechamientoPesq::query()->whereKey($cupo->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estado->permiteEnvio()) {
                throw CupoInvalidoException::noSePuedeEnviar($bloqueado->estado->etiqueta());
            }

            if ($bloqueado->saldoPendiente() > 0.0) {
                throw CupoInvalidoException::faltaCubrirElMonto($bloqueado->saldoPendiente());
            }

            $bloqueado->motivoAuditoria = 'Depósitos cargados y monto cubierto: se presenta para revisión.';
            $bloqueado->update(['estado' => EstadoAprovechamiento::EnRevision]);

            // La instancia ORIGINAL refrescada, no la copia bloqueada: quien
            // llamó tiene esa en la mano. Ver CLAUDE.md.
            return $cupo->refresh();
        });
    }

    /**
     * EN REVISIÓN ──▶ ACTIVO. Recién acá el cupo autoriza faenas.
     */
    public function aprobar(AprovechamientoPesq $cupo): AprovechamientoPesq
    {
        return DB::transaction(function () use ($cupo): AprovechamientoPesq {
            $bloqueado = AprovechamientoPesq::query()->whereKey($cupo->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estado->permiteRevision()) {
                throw CupoInvalidoException::noSePuedeRevisar($bloqueado->estado->etiqueta());
            }

            /*
             * SE VUELVE A MIRAR EL MONTO, aunque el envío ya lo había mirado.
             *
             * No es redundancia: entre el envío y la firma pueden pasar días, y
             * en el medio alguien pudo dar de baja un pago. Aprobar un cupo que
             * dejó de estar cubierto lo habilitaría para pescar sin la plata.
             */
            if ($bloqueado->saldoPendiente() > 0.0) {
                throw CupoInvalidoException::faltaCubrirElMonto($bloqueado->saldoPendiente());
            }

            $bloqueado->motivoAuditoria = 'Depósitos verificados: el aprovechamiento queda habilitado.';
            $bloqueado->update(['estado' => EstadoAprovechamiento::Activo]);

            return $cupo->refresh();
        });
    }

    /**
     * EN REVISIÓN ──▶ PENDIENTE, con el motivo escrito.
     *
     * Los pagos NO se tocan: cuelgan del cupo y siguen ahí, así que ventanilla
     * corrige lo que haya que corregir y vuelve a presentarlo sin recargar nada.
     */
    public function rechazar(AprovechamientoPesq $cupo, string $motivo): AprovechamientoPesq
    {
        return DB::transaction(function () use ($cupo, $motivo): AprovechamientoPesq {
            $bloqueado = AprovechamientoPesq::query()->whereKey($cupo->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estado->permiteRevision()) {
                throw CupoInvalidoException::noSePuedeRevisar($bloqueado->estado->etiqueta());
            }

            $bloqueado->motivoAuditoria = $motivo;
            $bloqueado->update(['estado' => EstadoAprovechamiento::Pendiente]);

            return $cupo->refresh();
        });
    }
}

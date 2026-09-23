<?php

namespace App\Services;

use App\Enums\EstadoAprovechamiento;
use App\Exceptions\CupoInvalidoException;
use App\Models\AprovechamientoPesq;
use Illuminate\Support\Facades\DB;

/**
 *  EL CIRCUITO DE REVISIÓN DE UN APROVECHAMIENTO
 */
class RevisarCupoService
{
    public function __construct(private readonly CobrarService $caja) {}

    /**
     * PENDIENTE ──▶ EN REVISIÓN.
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

            // Devuelve null en un REENVÍO: no hay depósitos sueltos, no se toca
            // el correlativo y el número que la persona tiene sigue valiendo.
            // El recibo sale a nombre del titular del cupo: no hace falta
            // pasárselo, lo lee de `beneficiario_id`.
            $this->caja->emitirRecibo($bloqueado);

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
             */
            if ($bloqueado->saldoPendiente() > 0.0) {
                throw CupoInvalidoException::faltaCubrirElMonto($bloqueado->saldoPendiente());
            }

            // Tercera condición: todas las boletas controladas. El monto cubierto
            // dice cuánto se DECLARÓ, no que la plata haya entrado.
            $sinControlar = $bloqueado->pagos()->sinValidar()->count();

            if ($sinControlar > 0) {
                throw CupoInvalidoException::faltaControlarBoletas($sinControlar);
            }

            // La emisión se escribe ACÁ: hasta la firma había una solicitud. El
            // vencimiento se recalcula sobre ella porque el cupo vale por GESTIÓN.
            $emision = now();

            $bloqueado->motivoAuditoria = 'Depósitos verificados: el aprovechamiento queda habilitado.';
            $bloqueado->update([
                'estado' => EstadoAprovechamiento::Aprobado,
                'fecha_emision' => $emision->toDateString(),
                'fecha_vencimiento' => $emision->copy()->endOfYear()->toDateString(),
            ]);

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

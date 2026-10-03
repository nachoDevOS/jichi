<?php

namespace App\Services;

use App\Enums\EstadoAprovechamiento;
use App\Exceptions\CupoInvalidoException;
use App\Models\AprovechamientoPesq;
use Illuminate\Support\Facades\DB;

/**
 * Aprobar (lo llama ConfirmarPagoService cuando SIREB confirma el pago) y revocar
 * la autorización.
 */
class RevisarCupoService
{
    /**
     * PENDIENTE ──▶ APROBADO, con el pago ya confirmado en SIREB. Recién acá autoriza faenas.
     */
    public function aprobar(AprovechamientoPesq $cupo): AprovechamientoPesq
    {
        return DB::transaction(function () use ($cupo): AprovechamientoPesq {
            $bloqueado = AprovechamientoPesq::query()->whereKey($cupo->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estado->estaAbierto()) {
                throw CupoInvalidoException::noSePuedeRevisar($bloqueado->estado->etiqueta());
            }

            // La emisión se escribe ACÁ: hasta el pago había una solicitud. El
            // vencimiento se recalcula sobre ella porque el cupo vale por GESTIÓN.
            $emision = now();

            $bloqueado->motivoAuditoria = 'Pago confirmado en SIREB: el aprovechamiento queda habilitado.';
            $bloqueado->update([
                'estado' => EstadoAprovechamiento::Aprobado,
                'fecha_emision' => $emision->toDateString(),
                'fecha_vencimiento' => $emision->copy()->endOfYear()->toDateString(),
            ]);

            return $cupo->refresh();
        });
    }

    /**
     * APROBADO | AGOTADO ──▶ REVOCADO, con el motivo escrito.
     *
     * Aunque siga en fecha deja de autorizar faenas y carnets nuevos, y libera el
     * lugar para otorgar otra. NO reescribe sus carnets ni sus faenas: quedan como
     * estaban y pierden efecto porque su vigencia mira a la autorización. Ver
     * REGLAS-NEGOCIO, «Una autorización vigente por persona».
     */
    public function revocar(AprovechamientoPesq $cupo, string $motivo): AprovechamientoPesq
    {
        if (trim($motivo) === '') {
            throw CupoInvalidoException::motivoObligatorio();
        }

        return DB::transaction(function () use ($cupo, $motivo): AprovechamientoPesq {
            $bloqueado = AprovechamientoPesq::query()->whereKey($cupo->id)->lockForUpdate()->firstOrFail();

            // Con la copia bloqueada: otra ventanilla pudo revocarlo recién.
            if ($bloqueado->estado === EstadoAprovechamiento::Revocado) {
                throw CupoInvalidoException::yaRevocado();
            }

            if (! $bloqueado->estado->permiteRevocacion()) {
                throw CupoInvalidoException::noSePuedeRevocar(mb_strtolower($bloqueado->estado->etiqueta()));
            }

            // El motivo antes de guardar: `Auditable` lo lee en el evento `updated`.
            $bloqueado->motivoAuditoria = $motivo;
            $bloqueado->update(['estado' => EstadoAprovechamiento::Revocado]);

            return $cupo->refresh();
        });
    }
}

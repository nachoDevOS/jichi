<?php

namespace App\Services;

use App\Enums\EstadoGuia;
use App\Exceptions\PermisoOperativoException;
use App\Models\GuiaMovimiento;
use Illuminate\Support\Facades\DB;

/**
 * Aprobar la guía cuando SIREB confirma el pago (lo llama ConfirmarPagoService).
 */
class RevisarGuiaService
{
    /**
     * PENDIENTE ──▶ APROBADA, con el pago ya confirmado en SIREB. Recién acá ampara el traslado.
     */
    public function aprobar(GuiaMovimiento $guia): GuiaMovimiento
    {
        return DB::transaction(function () use ($guia): GuiaMovimiento {
            $bloqueada = GuiaMovimiento::query()->whereKey($guia->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueada->estado->estaAbierto()) {
                throw PermisoOperativoException::guiaNoSePuedeRevisar($bloqueada->estado->etiqueta());
            }

            // Los cinco días corren desde ACÁ: contarlos desde el borrador le
            // comería al camión los días que estuvo esperando en ventanilla.
            $emision = now();

            $bloqueada->motivoAuditoria = 'Pago confirmado en SIREB: la guía queda habilitada.';
            $bloqueada->update([
                'estado' => EstadoGuia::Aprobada,
                'fecha_emision' => $emision,
                'fecha_vencimiento' => GuiaMovimiento::vencimientoDesde($emision),
            ]);

            return $guia->refresh();
        });
    }
}

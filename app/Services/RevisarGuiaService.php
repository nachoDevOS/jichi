<?php

namespace App\Services;

use App\Enums\EstadoGuia;
use App\Exceptions\PermisoOperativoException;
use App\Models\GuiaMovimiento;
use Illuminate\Support\Facades\DB;

/**
 * El circuito de revisión de una guía. Espejo de `RevisarFaenaService`: el
 * operador aprende un solo circuito para los cuatro trámites.
 */
class RevisarGuiaService
{
    public function __construct(private readonly CobrarService $caja) {}

    /**
     * PENDIENTE ──▶ EN REVISIÓN, y acá sale el RECIBO.
     */
    public function enviar(GuiaMovimiento $guia): GuiaMovimiento
    {
        return DB::transaction(function () use ($guia): GuiaMovimiento {
            $bloqueada = GuiaMovimiento::query()->whereKey($guia->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueada->estado->permiteEnvio()) {
                throw PermisoOperativoException::guiaNoSePuedeEnviar($bloqueada->estado->etiqueta());
            }

            if ($bloqueada->saldoPendiente() > 0.0) {
                throw PermisoOperativoException::faltaCubrirElArancelDeLaGuia(
                    $bloqueada->saldoPendiente(),
                );
            }

            $bloqueada->motivoAuditoria = 'Depósitos cargados y arancel cubierto: se presenta para revisión.';
            $bloqueada->update(['estado' => EstadoGuia::EnRevision]);

            // Devuelve null en un REENVÍO: sin depósitos sueltos no se toca el
            // correlativo y el número que la persona tiene sigue valiendo.
            $this->caja->emitirRecibo($bloqueada);

            // La instancia ORIGINAL refrescada, no la copia bloqueada. Ver CLAUDE.md.
            return $guia->refresh();
        });
    }

    /**
     * EN REVISIÓN ──▶ ACTIVA. Recién acá la guía ampara el traslado.
     */
    public function aprobar(GuiaMovimiento $guia): GuiaMovimiento
    {
        return DB::transaction(function () use ($guia): GuiaMovimiento {
            $bloqueada = GuiaMovimiento::query()->whereKey($guia->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueada->estado->permiteRevision()) {
                throw PermisoOperativoException::guiaNoSePuedeRevisar($bloqueada->estado->etiqueta());
            }

            // Se vuelve a mirar el arancel: entre el envío y la firma se pudo
            // dar de baja un depósito.
            if ($bloqueada->saldoPendiente() > 0.0) {
                throw PermisoOperativoException::faltaCubrirElArancelDeLaGuia(
                    $bloqueada->saldoPendiente(),
                );
            }

            // Y que ninguna boleta quede sin controlar: `sinValidar()` cuenta
            // también las observadas.
            $sinControlar = $bloqueada->pagos()->sinValidar()->count();

            if ($sinControlar > 0) {
                throw PermisoOperativoException::faltaControlarBoletasDeLaGuia($sinControlar);
            }

            // Los cinco días corren desde ACÁ: contarlos desde el borrador le
            // comería al camión los días que estuvo esperando en ventanilla.
            $emision = now();

            $bloqueada->motivoAuditoria = 'Depósitos verificados: la guía queda habilitada.';
            $bloqueada->update([
                'estado' => EstadoGuia::Activa,
                'fecha_emision' => $emision,
                'fecha_vencimiento' => GuiaMovimiento::vencimientoDesde($emision),
            ]);

            return $guia->refresh();
        });
    }

    /**
     * EN REVISIÓN ──▶ PENDIENTE, con el motivo escrito.
     *
     * Los pagos NO se tocan y el recibo tampoco se anula: ese papel ya está en
     * manos de la persona, y un reenvío no emite un segundo.
     */
    public function rechazar(GuiaMovimiento $guia, string $motivo): GuiaMovimiento
    {
        return DB::transaction(function () use ($guia, $motivo): GuiaMovimiento {
            $bloqueada = GuiaMovimiento::query()->whereKey($guia->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueada->estado->permiteRevision()) {
                throw PermisoOperativoException::guiaNoSePuedeRevisar($bloqueada->estado->etiqueta());
            }

            $bloqueada->motivoAuditoria = $motivo;
            $bloqueada->update(['estado' => EstadoGuia::Pendiente]);

            return $guia->refresh();
        });
    }
}

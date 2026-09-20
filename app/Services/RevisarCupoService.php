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
    public function enviar(
        AprovechamientoPesq $cupo,
        ?string $nitCi = null,
        ?string $nombreFactura = null,
    ): AprovechamientoPesq {
        return DB::transaction(function () use ($cupo, $nitCi, $nombreFactura): AprovechamientoPesq {
            $bloqueado = AprovechamientoPesq::query()->whereKey($cupo->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estado->permiteEnvio()) {
                throw CupoInvalidoException::noSePuedeEnviar($bloqueado->estado->etiqueta());
            }

            if ($bloqueado->saldoPendiente() > 0.0) {
                throw CupoInvalidoException::faltaCubrirElMonto($bloqueado->saldoPendiente());
            }

            $bloqueado->motivoAuditoria = 'Depósitos cargados y monto cubierto: se presenta para revisión.';
            $bloqueado->update(['estado' => EstadoAprovechamiento::EnRevision]);

            $bloqueado->loadMissing('beneficiario');

            // Devuelve null en un REENVÍO: no hay depósitos sueltos, no se toca
            // el correlativo y el número que la persona tiene sigue valiendo.
            $this->caja->emitirRecibo(
                $bloqueado,
                filled($nitCi) ? $nitCi : ($bloqueado->beneficiario?->ci ?? 'S/N'),
                filled($nombreFactura) ? $nombreFactura : ($bloqueado->beneficiario?->nombreCompleto ?? 'Sin nombre'),
            );

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

            /*
             * TERCERA CONDICIÓN: todas las boletas controladas. El monto cubierto
             * dice cuánto se DECLARÓ, no que la plata haya entrado. `sinValidar()`
             * cuenta también los observados: un reparo abierto no se firma.
             */
            $sinControlar = $bloqueado->pagos()->sinValidar()->count();

            if ($sinControlar > 0) {
                throw CupoInvalidoException::faltaControlarBoletas($sinControlar);
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

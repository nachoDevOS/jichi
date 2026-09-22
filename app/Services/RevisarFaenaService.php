<?php

namespace App\Services;

use App\Enums\EstadoFaena;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\PermisoFaena;
use Illuminate\Support\Facades\DB;

/**
 *  EL CIRCUITO DE REVISIÓN DE UNA FAENA
 *
 * Espejo de `RevisarCarnetService` y de `RevisarCupoService`: la salida de
 * pesca se cobra y se firma igual que los otros dos trámites, así que el
 * operador aprende un solo circuito.
 */
class RevisarFaenaService
{
    public function __construct(private readonly CobrarService $caja) {}

    /**
     * PENDIENTE ──▶ EN REVISIÓN, y acá sale el RECIBO.
     */
    public function enviar(PermisoFaena $faena): PermisoFaena
    {
        return DB::transaction(function () use ($faena): PermisoFaena {
            $bloqueada = PermisoFaena::query()->whereKey($faena->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueada->estado->permiteEnvio()) {
                throw PermisoOperativoException::faenaNoSePuedeEnviar($bloqueada->estado->etiqueta());
            }

            if ($bloqueada->saldoPendiente() > 0.0) {
                throw PermisoOperativoException::faltaCubrirElArancelDeLaFaena(
                    $bloqueada->saldoPendiente(),
                );
            }

            $bloqueada->motivoAuditoria = 'Depósitos cargados y arancel cubierto: se presenta para revisión.';
            $bloqueada->update(['estado' => EstadoFaena::EnRevision]);

            // Devuelve null en un REENVÍO: sin depósitos sueltos no se toca el
            // correlativo y el número que la persona tiene sigue valiendo.
            $this->caja->emitirRecibo($bloqueada);

            // La instancia ORIGINAL refrescada, no la copia bloqueada. Ver CLAUDE.md.
            return $faena->refresh();
        });
    }

    /**
     * EN REVISIÓN ──▶ APROBADO. Recién acá el permiso autoriza a salir.
     */
    public function aprobar(PermisoFaena $faena): PermisoFaena
    {
        return DB::transaction(function () use ($faena): PermisoFaena {
            $bloqueada = PermisoFaena::query()->whereKey($faena->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueada->estado->permiteRevision()) {
                throw PermisoOperativoException::faenaNoSePuedeRevisar($bloqueada->estado->etiqueta());
            }

            // Se vuelve a mirar el arancel: entre el envío y la firma se pudo
            // dar de baja un depósito.
            if ($bloqueada->saldoPendiente() > 0.0) {
                throw PermisoOperativoException::faltaCubrirElArancelDeLaFaena(
                    $bloqueada->saldoPendiente(),
                );
            }

            // Y que ninguna boleta quede sin controlar: `sinValidar()` cuenta
            // también las observadas.
            $sinControlar = $bloqueada->pagos()->sinValidar()->count();

            if ($sinControlar > 0) {
                throw PermisoOperativoException::faltaControlarBoletasDeLaFaena($sinControlar);
            }

            /*
             *  ACÁ SE CONSUME EL CUPO, y por eso acá está el control de saldo.
             *
             * Una faena pendiente NO descuenta —ver EstadoFaena::consumeCupo()—
             * así que la firma es el primer y único momento en que el volumen
             * sale de la bolsa. Sin este control, tres solicitudes por el cupo
             * entero se aprobarían las tres: ninguna vería a las otras.
             *
             * La fila del CUPO se bloquea, no la de la faena: es la que
             * contiene el recurso escaso y la que pueden estar tocando dos
             * ventanillas en el mismo segundo.
             */
            $bloqueada->loadMissing('carnet');

            $cupo = AprovechamientoPesq::query()
                ->whereKey($bloqueada->carnet?->aprovechamiento_id)
                ->lockForUpdate()
                ->first();

            if ($cupo !== null && AprovechamientoPesq::modoEstricto()) {
                $saldo = $cupo->saldoKg();

                if ((float) $bloqueada->kilos_extraidos > $saldo) {
                    throw PermisoOperativoException::excedeCupo(
                        (float) $bloqueada->kilos_extraidos,
                        $saldo,
                    );
                }
            }

            /*
             * LA FECHA DE EMISIÓN SE ESCRIBE ACÁ: hasta la firma lo único que
             * había era una solicitud. Las de salida y límite NO se recalculan
             * —son el permiso que el pescador pidió y se le va a imprimir—.
             */
            $bloqueada->motivoAuditoria = 'Depósitos verificados: el permiso queda habilitado.';
            $bloqueada->update([
                'estado' => EstadoFaena::Activo,
                'fecha_emision' => now()->toDateString(),
            ]);

            // Si esta firma dejó la bolsa en cero, el cupo pasa a `agotado`.
            $cupo?->fresh()->sincronizarEstadoPorSaldo();

            return $faena->refresh();
        });
    }

    /**
     * EN REVISIÓN ──▶ PENDIENTE, con el motivo escrito.
     *
     * Los pagos NO se tocan y el recibo tampoco se anula: ese papel ya está en
     * manos de la persona, y un reenvío no emite un segundo.
     */
    public function rechazar(PermisoFaena $faena, string $motivo): PermisoFaena
    {
        return DB::transaction(function () use ($faena, $motivo): PermisoFaena {
            $bloqueada = PermisoFaena::query()->whereKey($faena->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueada->estado->permiteRevision()) {
                throw PermisoOperativoException::faenaNoSePuedeRevisar($bloqueada->estado->etiqueta());
            }

            $bloqueada->motivoAuditoria = $motivo;
            $bloqueada->update(['estado' => EstadoFaena::Pendiente]);

            return $faena->refresh();
        });
    }
}

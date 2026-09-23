<?php

namespace App\Services;

use App\Enums\EstadoCarnet;
use App\Exceptions\CarnetInvalidoException;
use App\Models\Carnet;
use Illuminate\Support\Facades\DB;

/**
 *  EL CIRCUITO DE REVISIÓN DE UN CARNET
 *
 * Espejo de `RevisarCupoService`, y a propósito: el carnet se cobra y se firma
 * igual que el aprovechamiento, así que el operador aprende un solo circuito.
 */
class RevisarCarnetService
{
    /** La serie del registro de carnets. Una sola: no se parte por actividad. */
    private const SERIE_REGISTRO = 'CARNET';

    public function __construct(
        private readonly CobrarService $caja,
        private readonly CorrelativoService $correlativos,
    ) {}

    /**
     * PENDIENTE ──▶ EN REVISIÓN, y acá sale el RECIBO.
     *
     * Es el momento en que ventanilla declara que el expediente está completo:
     * la persona entregó sus papeles y su boleta, y se va con el comprobante.
     */
    public function enviar(Carnet $carnet): Carnet
    {
        return DB::transaction(function () use ($carnet): Carnet {
            $bloqueado = Carnet::query()->whereKey($carnet->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estado->permiteEnvio()) {
                throw CarnetInvalidoException::noSePuedeEnviar($bloqueado->estado->etiqueta());
            }

            if ($bloqueado->saldoPendiente() > 0.0) {
                throw CarnetInvalidoException::faltaCubrirElArancel($bloqueado->saldoPendiente());
            }

            $bloqueado->motivoAuditoria = 'Depósitos cargados y arancel cubierto: se presenta para revisión.';
            $bloqueado->update(['estado' => EstadoCarnet::EnRevision]);

            // Devuelve null en un REENVÍO: no hay depósitos sueltos, no se toca
            // el correlativo y el número que la persona tiene sigue valiendo.
            $this->caja->emitirRecibo($bloqueado);

            // La instancia ORIGINAL refrescada, no la copia bloqueada. Ver CLAUDE.md.
            return $carnet->refresh();
        });
    }

    /**
     * EN REVISIÓN ──▶ ACTIVO. Recién acá el carnet habilita a trabajar.
     */
    public function aprobar(Carnet $carnet): Carnet
    {
        return DB::transaction(function () use ($carnet): Carnet {
            $bloqueado = Carnet::query()->whereKey($carnet->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estado->permiteRevision()) {
                throw CarnetInvalidoException::noSePuedeRevisar($bloqueado->estado->etiqueta());
            }

            // Se vuelve a mirar el arancel: entre el envío y la firma se pudo dar
            // de baja un depósito.
            if ($bloqueado->saldoPendiente() > 0.0) {
                throw CarnetInvalidoException::faltaCubrirElArancel($bloqueado->saldoPendiente());
            }

            // Y que ninguna boleta quede sin controlar: `sinValidar()` cuenta
            // también las observadas. Sin esto, validar sería decorativo.
            $sinControlar = $bloqueado->pagos()->sinValidar()->count();

            if ($sinControlar > 0) {
                throw CarnetInvalidoException::faltaControlarBoletas($sinControlar);
            }

            // La emisión se escribe ACÁ: hasta la firma había una solicitud. El
            // vencimiento se recalcula sobre ella porque el carnet vale por
            // GESTIÓN: uno pedido el 28/12 y firmado en enero vence con el año nuevo.
            $emision = now();
            $gestion = (int) $emision->format('Y');

            // El número se asigna ACÁ: un carnet que nunca se firma no puede
            // gastar uno del libro. `siguienteNumero()` bloquea la fila del
            // contador, y solo se pide si NO tiene: un rechazo conserva el suyo.
            $registro = $bloqueado->nro_registro
                ?? $this->correlativos->siguienteNumero(self::SERIE_REGISTRO, $gestion);

            $bloqueado->motivoAuditoria = 'Depósitos verificados: la credencial queda habilitada.';
            $bloqueado->update([
                'estado' => EstadoCarnet::Activo,
                'nro_registro' => $registro,
                'fecha_emision' => $emision->toDateString(),
                'fecha_vencimiento' => $emision->copy()->endOfYear()->toDateString(),
            ]);

            return $carnet->refresh();
        });
    }

    /**
     * EN REVISIÓN ──▶ PENDIENTE, con el motivo escrito.
     *
     * Los pagos NO se tocan: cuelgan del carnet y siguen ahí, así que
     * ventanilla corrige lo que haga falta y lo vuelve a presentar sin recargar
     * nada. El recibo tampoco se anula: ese papel ya está en manos de la
     * persona, y un reenvío no emite un segundo.
     */
    public function rechazar(Carnet $carnet, string $motivo): Carnet
    {
        return DB::transaction(function () use ($carnet, $motivo): Carnet {
            $bloqueado = Carnet::query()->whereKey($carnet->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estado->permiteRevision()) {
                throw CarnetInvalidoException::noSePuedeRevisar($bloqueado->estado->etiqueta());
            }

            $bloqueado->motivoAuditoria = $motivo;
            $bloqueado->update(['estado' => EstadoCarnet::Pendiente]);

            return $carnet->refresh();
        });
    }
}

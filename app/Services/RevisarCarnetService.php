<?php

namespace App\Services;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Exceptions\CarnetInvalidoException;
use App\Models\Carnet;
use Illuminate\Support\Facades\DB;

/**
 * Aprobar el carnet cuando SIREB confirma el pago (lo llama ConfirmarPagoService).
 */
class RevisarCarnetService
{
    /** La serie del registro de carnets. Una sola: no se parte por actividad. */
    private const SERIE_REGISTRO = 'CARNET';

    public function __construct(private readonly CorrelativoService $correlativos) {}

    /**
     * PENDIENTE ──▶ APROBADO, con el pago ya confirmado en SIREB. Recién acá habilita a trabajar.
     */
    public function aprobar(Carnet $carnet): Carnet
    {
        return DB::transaction(function () use ($carnet): Carnet {
            $bloqueado = Carnet::query()->whereKey($carnet->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estado->estaAbierto()) {
                throw CarnetInvalidoException::noSePuedeRevisar($bloqueado->estado->etiqueta());
            }

            // Si revocaron la autorización mientras esperaba el pago, ya no se aprueba.
            if ($bloqueado->aprovechamiento?->estado === EstadoAprovechamiento::Revocado) {
                throw CarnetInvalidoException::cupoRevocado();
            }

            // La emisión se escribe ACÁ: hasta el pago había una solicitud. El
            // vencimiento se recalcula sobre ella porque el carnet vale por
            // GESTIÓN: uno pedido el 28/12 y firmado en enero vence con el año nuevo.
            $emision = now();
            $gestion = (int) $emision->format('Y');

            // El número se asigna ACÁ: un carnet que nunca se firma no puede
            // gastar uno del libro. `siguienteNumero()` bloquea la fila del
            // contador, y solo se pide si NO tiene.
            $registro = $bloqueado->nro_registro
                ?? $this->correlativos->siguienteNumero(self::SERIE_REGISTRO, $gestion);

            $bloqueado->motivoAuditoria = 'Pago confirmado en SIREB: la credencial queda habilitada.';
            $bloqueado->update([
                'estado' => EstadoCarnet::Aprobado,
                'nro_registro' => $registro,
                'fecha_emision' => $emision->toDateString(),
                'fecha_vencimiento' => $emision->copy()->endOfYear()->toDateString(),
            ]);

            return $carnet->refresh();
        });
    }
}

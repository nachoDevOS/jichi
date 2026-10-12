<?php

namespace App\Services;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoFaena;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\PermisoFaena;
use Illuminate\Support\Facades\DB;

/**
 * Aprobar la faena cuando SIREB confirma el pago (lo llama ConfirmarPagoService).
 */
class RevisarFaenaService
{
    /**
     * PENDIENTE ──▶ APROBADO, con el pago ya confirmado en SIREB. Recién acá autoriza a salir.
     */
    public function aprobar(PermisoFaena $faena): PermisoFaena
    {
        return DB::transaction(function () use ($faena): PermisoFaena {
            $bloqueada = PermisoFaena::query()->whereKey($faena->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueada->estado->estaAbierto()) {
                throw PermisoOperativoException::faenaNoSePuedeRevisar($bloqueada->estado->etiqueta());
            }

            // La faena no descuenta kilos: solo se mira que la autorización siga valiendo.
            $bloqueada->loadMissing('carnet');
            $cupo = AprovechamientoPesq::query()->find($bloqueada->carnet?->aprovechamiento_id);

            // La salida es HOY: un cupo que ya venció no puede respaldarla.
            if ($cupo !== null && ! $cupo->estaEnFecha()) {
                throw PermisoOperativoException::sinCupoVigente();
            }

            // Si la revocaron mientras la faena esperaba el pago, ya no se aprueba.
            if ($cupo !== null && $cupo->estado === EstadoAprovechamiento::Revocado) {
                throw PermisoOperativoException::cupoRevocado();
            }

            // La aprobación fija las dos fechas del papel: sale hoy y desembarca al techo.
            $bloqueada->motivoAuditoria = 'Pago confirmado en SIREB: el permiso queda habilitado.';
            $bloqueada->update([
                'estado' => EstadoFaena::Aprobado,
                'fecha_salida' => now()->toDateString(),
                'fecha_desembarque' => PermisoFaena::desembarqueDesde(now())->toDateString(),
            ]);

            return $faena->refresh();
        });
    }
}

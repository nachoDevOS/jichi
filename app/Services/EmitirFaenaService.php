<?php

namespace App\Services;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoFaena;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\PermisoFaena;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 *  PASO 4 DEL FLUJO — el PERMISO DE FAENA, una salida de pesca
 */
class EmitirFaenaService
{
    /**
     * Registra la solicitud de una salida. NACE PENDIENTE: no autoriza nada
     * hasta que se cobre el arancel y alguien la firme.
     */
    public function emitir(
        Carnet $carnet,
        int $numeroFaena,
        float $kilos,
        ?Carbon $salida = null,
    ): PermisoFaena {
        $salida ??= now();

        /*
         * LAS COMPROBACIONES DEL CARNET VAN ANTES DE LA TRANSACCIÓN, y las del
         * cupo adentro. La diferencia es qué puede cambiar mientras tanto: el
         * carnet no se revoca en el medio de esta operación, pero el saldo sí
         * puede moverlo otra ventanilla en el mismo segundo.
         */
        if (! $carnet->tipo_actor->emiteFaenas()) {
            throw PermisoOperativoException::actorNoEmite('permisos de faena', $carnet->tipo_actor);
        }

        if (! $carnet->estaVigente()) {
            throw PermisoOperativoException::carnetNoVigente($carnet->estado);
        }

        if ($carnet->aprovechamiento_id === null) {
            throw PermisoOperativoException::sinCupoVigente();
        }

        return DB::transaction(function () use ($carnet, $numeroFaena, $kilos, $salida): PermisoFaena {
            // La fila del cupo es la que contiene el recurso escaso: es la que
            // se bloquea. Releerla devuelve OTRA instancia, y acá se usa esa a
            // propósito — es la que tiene el saldo al día.
            $cupo = AprovechamientoPesq::query()
                ->whereKey($carnet->aprovechamiento_id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * SE PREGUNTA POR LA FECHA, NO POR `estaVigente()`.
             */
            if (! $cupo->estaEnFecha()) {
                throw PermisoOperativoException::sinCupoVigente();
            }

            /*
             * EL CUPO SIN COBRAR TIENE SU PROPIO MENSAJE, y hace falta.
             */
            if ($cupo->estado === EstadoAprovechamiento::Pendiente) {
                throw PermisoOperativoException::cupoPendienteDePago();
            }

            /*
             * Y EL OTRO ESTADO QUE NO HABILITA: presentado y sin firmar.
             */
            if ($cupo->estado === EstadoAprovechamiento::EnRevision) {
                throw PermisoOperativoException::cupoEnRevision();
            }

            /*
             * EL TOPE SOLO SE HACE CUMPLIR EN MODO ESTRICTO.
             */
            if (AprovechamientoPesq::modoEstricto()) {
                $saldo = $cupo->saldoKg();

                if ($kilos > $saldo) {
                    throw PermisoOperativoException::excedeCupo($kilos, $saldo);
                }
            }

            // El número es correlativo DENTRO DEL CARNET: es su talonario, y
            // así lo exige el único `(carnet_id, numero_faena)`.
            if ($carnet->faenas()->where('numero_faena', $numeroFaena)->exists()) {
                throw PermisoOperativoException::numeroRepetido('una faena', (string) $numeroFaena);
            }

            $faena = PermisoFaena::create([
                'carnet_id' => $carnet->id,
                'numero_faena' => $numeroFaena,
                'kilos_extraidos' => $kilos,
                // PENDIENTE, como el carnet y el cupo: la emisión la escribe
                // la aprobación, y hasta entonces esto es una solicitud.
                'estado' => EstadoFaena::Pendiente,
                'fecha_solicitud' => now()->toDateString(),
                'fecha_salida' => $salida->toDateString(),
                // Se GUARDA la fecha calculada en vez de derivarla al leer: si
                // mañana la resolución baja el plazo, los permisos ya emitidos
                // tienen que seguir venciendo cuando dice el papel que el
                // pescador tiene en la mano.
                'fecha_limite' => PermisoFaena::limiteDesde($salida)->toDateString(),
            ]);

            /*
             * SI ESTA SOLICITUD DEJÓ EL CUPO EN CERO, EL CUPO PASA A `agotado`.
             * Los kilos se reservan desde que se piden —ver
             * EstadoFaena::consumeCupo()—, no desde la firma.
             */
            if ($cupo->fresh()->saldoKg() <= 0.0) {
                $cupo->update(['estado' => EstadoAprovechamiento::Agotado]);
            }

            return $faena;
        });
    }

    /**
     * Registra que el pescador volvió y descargó.
     */
    public function completar(PermisoFaena $faena, ?float $kilosReales = null): PermisoFaena
    {
        if ($faena->estado !== EstadoFaena::Activo) {
            throw PermisoOperativoException::noSePuedeCompletar(
                mb_strtolower($faena->estado->etiqueta()),
            );
        }

        return DB::transaction(function () use ($faena, $kilosReales): PermisoFaena {
            // El cupo se alcanza por el carnet: la faena ya no lo guarda.
            $faena->loadMissing('carnet');

            $cupo = AprovechamientoPesq::query()
                ->whereKey($faena->carnet?->aprovechamiento_id)
                ->lockForUpdate()
                ->firstOrFail();

            $bloqueada = PermisoFaena::query()->whereKey($faena->id)->lockForUpdate()->firstOrFail();

            $cambios = ['estado' => EstadoFaena::Completado];

            if ($kilosReales !== null && abs($kilosReales - (float) $bloqueada->kilos_extraidos) > 0.001) {
                /*
                 * El saldo se mide SIN esta faena: `saldoKg()` ya la está
                 * descontando, así que comparar el nuevo peso contra el saldo a
                 * secas rechazaría hasta una corrección hacia abajo.
                 */
                $disponible = $cupo->saldoKg() + (float) $bloqueada->kilos_extraidos;

                if ($kilosReales > $disponible) {
                    throw PermisoOperativoException::excedeCupo($kilosReales, $disponible);
                }

                $cambios['kilos_extraidos'] = $kilosReales;
            }

            $bloqueada->update($cambios);

            // El cupo puede haber quedado agotado —o haberse destrabado, si la
            // corrección fue hacia abajo—, así que se recalcula el estado.
            $this->sincronizarEstadoDelCupo($cupo->fresh());

            // Se devuelve la instancia ORIGINAL refrescada: quien llamó tiene
            // esa en la mano, y darle la copia bloqueada lo deja con el estado
            // viejo en memoria.
            return $faena->refresh();
        });
    }

    /**
     * Pone el cupo en `agotado` o lo devuelve a `activo` según su saldo real.
     */
    private function sincronizarEstadoDelCupo(AprovechamientoPesq $cupo): void
    {
        // Un cupo VENCIDO no se toca: su problema es la fecha, no los kilos, y
        // devolverlo a `activo` porque le sobró volumen sería mentir.
        if ($cupo->estado === EstadoAprovechamiento::Vencido) {
            return;
        }

        $deberiaEstar = $cupo->saldoKg() <= 0.0
            ? EstadoAprovechamiento::Agotado
            : EstadoAprovechamiento::Aprobado;

        if ($cupo->estado !== $deberiaEstar) {
            $cupo->update(['estado' => $deberiaEstar]);
        }
    }
}

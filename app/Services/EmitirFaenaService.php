<?php

namespace App\Services;

use App\Enums\ConceptoArancel;
use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoFaena;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\ArancelSireb;
use App\Models\Carnet;
use App\Models\PermisoFaena;
use App\Sireb\PrecioSireb;
use App\Sireb\SinPrecioException;
use Illuminate\Support\Facades\DB;

/**
 *  PASO 4 DEL FLUJO — el PERMISO DE FAENA, una salida de pesca
 */
class EmitirFaenaService
{
    public function __construct(
        private readonly CorrelativoService $correlativos,
        private readonly PrecioSireb $precios,
        private readonly LiquidarSirebService $liquidaciones,
    ) {}

    /**
     * Registra la solicitud de una salida. NACE PENDIENTE: no autoriza nada
     * hasta que se cobre el arancel y alguien la firme.
     *
     * @param  array<string, string|null>  $papel  Los renglones del talonario:
     *                                             embarcacion, propietario, comandante_barco,
     *                                             matricula_naval, nro_kardex, region_desde,
     *                                             region_hasta.
     */
    public function emitir(
        Carnet $carnet,
        float $kilos,
        array $papel = [],
    ): PermisoFaena {

        // El carnet se comprueba ANTES de la transacción y el cupo adentro: el
        // carnet no se revoca en el medio, el saldo sí puede moverlo otro.
        if (! $carnet->tipo_actor->emiteFaenas()) {
            throw PermisoOperativoException::actorNoEmite('permisos de faena', $carnet->tipo_actor);
        }

        // Antes que la vigencia: sin efecto, el carnet dice «aprobado» y el mensaje
        // genérico hablaría de una fecha que no es el problema.
        if ($carnet->autorizacionRevocada()) {
            throw PermisoOperativoException::cupoRevocado();
        }

        if (! $carnet->estaVigente()) {
            throw PermisoOperativoException::carnetNoVigente($carnet->estado);
        }

        if ($carnet->aprovechamiento_id === null) {
            throw PermisoOperativoException::sinCupoVigente();
        }

        // Fuera de la transacción: una llamada a SIREB no debe tener el cupo bloqueado.
        $precio = $this->precioDeLaFaena();

        $faena = DB::transaction(function () use ($carnet, $kilos, $papel, $precio): PermisoFaena {
            // La fila del cupo es la que contiene el recurso escaso: es la que
            // se bloquea. Releerla devuelve OTRA instancia, y acá se usa esa a
            // propósito — es la que tiene el saldo al día.
            $cupo = AprovechamientoPesq::query()
                ->whereKey($carnet->aprovechamiento_id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Se pregunta por la fecha, no por `estaVigente()`.
             */
            if (! $cupo->estaEnFecha()) {
                throw PermisoOperativoException::sinCupoVigente();
            }

            // Revocada: el carnet puede seguir aprobado, pero su autorización ya no respalda nada.
            if ($cupo->estado === EstadoAprovechamiento::Revocado) {
                throw PermisoOperativoException::cupoRevocado();
            }

            /*
             * El cupo sin cobrar tiene su propio mensaje, y hace falta.
             */
            if ($cupo->estado === EstadoAprovechamiento::Pendiente) {
                throw PermisoOperativoException::cupoPendienteDePago();
            }

            // Solo en modo ESTRICTO: la pendiente RESERVA, así que se mide contra
            // lo libre. Con la fila del cupo bloqueada, dos ventanillas no apartan lo mismo.
            if (AprovechamientoPesq::modoEstricto()) {
                $this->exigirKilosLibres($cupo, $kilos, 0.0);
            }

            $faena = PermisoFaena::create([
                'carnet_id' => $carnet->id,
                // El número lo pone el sistema, no el operador: correlativo
                // global y continuo, como el talonario de papel.
                'nro' => $this->correlativos->siguienteContinuo(PermisoFaena::SERIE),
                // Copia congelada del precio de SIREB: ver PermisoFaena::montoACobrar().
                'monto' => $precio['monto'],
                'sireb_tarifa_id' => $precio['tarifa_id'],
                'kilos_extraidos' => $kilos,
                ...$this->renglonesDelPapel($papel),
                // PENDIENTE, como el carnet y el cupo: la emisión la escribe
                // la aprobación, y hasta entonces esto es una solicitud.
                'estado' => EstadoFaena::Pendiente,
                // Salida y desembarque quedan en NULL: los escribe la aprobación.
                'fecha_solicitud' => now()->toDateString(),
            ]);

            // Su llave pública, en la misma transacción: sin código, el documento
            // no se puede verificar.
            $faena->asignarCodigo();
            $this->liquidaciones->preparar($faena);

            // El cupo NO se descuenta acá: la pendiente solo reserva. Descuenta al aprobarse.

            return $faena;
        });

        $this->liquidaciones->enviarSinFrenar($faena);

        return $faena;
    }

    /**
     *  Corregir el borrador
     *
     * El CARNET no se toca: cambiar de titular no es corregir una salida, es
     * emitir otra. Dejarlo editable movería un permiso de una persona a otra
     * sin más rastro que la auditoría.
     *
     * @param  array<string, string|null>  $papel  Los renglones del talonario.
     */
    public function editar(
        PermisoFaena $faena,
        float $kilos,
        array $papel = [],
    ): PermisoFaena {
        return DB::transaction(function () use ($faena, $kilos, $papel): PermisoFaena {
            $bloqueada = PermisoFaena::query()->whereKey($faena->id)->lockForUpdate()->firstOrFail();

            // Con la copia bloqueada: entre que la pantalla se dibujó y llegó el
            // submit, el pago pudo aprobarla. Los kilos no cambian lo que se cobra.
            if (! $bloqueada->estado->permiteEdicion()) {
                throw PermisoOperativoException::faenaNoSePuedeEditar(
                    mb_strtolower($bloqueada->estado->etiqueta()),
                );
            }

            $bloqueada->loadMissing('carnet');

            $cupo = AprovechamientoPesq::query()
                ->whereKey($bloqueada->carnet?->aprovechamiento_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Sus propios kilos vuelven a lo libre: los tenía reservados ella misma.
            if (AprovechamientoPesq::modoEstricto()) {
                $this->exigirKilosLibres(
                    $cupo,
                    $kilos,
                    $bloqueada->estado->reservaCupo() ? (float) $bloqueada->kilos_extraidos : 0.0,
                );
            }

            $bloqueada->update([
                'kilos_extraidos' => $kilos,
                ...$this->renglonesDelPapel($papel),
            ]);

            // La corrección pudo agotar el cupo o destrabarlo.
            $this->sincronizarEstadoDelCupo($cupo->fresh());

            // La original refrescada, no la copia bloqueada. Ver CLAUDE.md.
            return $faena->refresh();
        });
    }

    /**
     *  Eliminar una faena cargada por error
     *
     * Devuelve sus kilos a la bolsa madre: una pendiente los tenía reservados.
     */
    public function eliminar(PermisoFaena $faena, string $motivo): void
    {
        if (! $faena->puedeEliminarse()) {
            throw PermisoOperativoException::faenaNoSePuedeEliminar(mb_strtolower($faena->estado->etiqueta()));
        }

        // Primero SIREB: si no anula la liquidación, no se elimina.
        $this->liquidaciones->anular($faena, 'Eliminado en Jichi: '.$motivo);

        DB::transaction(function () use ($faena, $motivo): void {
            $bloqueada = PermisoFaena::query()->whereKey($faena->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueada->estado->permiteEliminacion()) {
                throw PermisoOperativoException::faenaNoSePuedeEliminar(
                    mb_strtolower($bloqueada->estado->etiqueta()),
                );
            }

            $bloqueada->loadMissing('carnet');

            $cupo = AprovechamientoPesq::query()
                ->whereKey($bloqueada->carnet?->aprovechamiento_id)
                ->lockForUpdate()
                ->first();

            // El motivo se deja y se borra: `Auditable` ya engancha el `deleted`,
            // y registrarlo a mano además dejaría el hecho dos veces.
            $bloqueada->motivoAuditoria = $motivo;
            $bloqueada->delete();

            // El número NO se reusa: la serie queda con un hueco, que es lo que
            // el motivo en la auditoría explica.

            // Sus kilos vuelven a la bolsa: un cupo agotado puede destrabarse.
            if ($cupo !== null) {
                $this->sincronizarEstadoDelCupo($cupo->fresh());
            }
        });
    }

    /**
     * El precio de la salida según SIREB, de la fila `faena` de Aranceles. Sin
     * él no se emite: ninguna tarifa se escribe a mano.
     *
     * @return array{monto: float, tarifa_id: string}
     */
    private function precioDeLaFaena(): array
    {
        $arancel = ArancelSireb::de(ConceptoArancel::Faena);

        try {
            return $this->precios->de($arancel?->servicio_sireb, $arancel?->tarifa_sireb);
        } catch (SinPrecioException $e) {
            throw PermisoOperativoException::faenaSinPrecio($e->getMessage());
        }
    }

    /**
     * Los renglones del talonario, normalizados: '' entra como null.
     *
     * @param  array<string, string|null>  $papel
     * @return array<string, string|null>
     */
    private function renglonesDelPapel(array $papel): array
    {
        $campos = [
            'embarcacion', 'propietario', 'comandante_barco',
            'matricula_naval', 'nro_kardex', 'region_desde', 'region_hasta',
        ];

        return collect($campos)
            ->mapWithKeys(fn (string $c): array => [$c => trim((string) ($papel[$c] ?? '')) ?: null])
            ->all();
    }

    /**
     * Pone el cupo en `agotado` o lo devuelve a `aprobado` según su saldo real.
     *
     * La regla vive en el MODELO porque la comparten dos servicios: este y el
     * que firma las faenas, que es donde ahora se consume el volumen.
     */
    private function sincronizarEstadoDelCupo(AprovechamientoPesq $cupo): void
    {
        $cupo->sincronizarEstadoPorSaldo();
    }

    /**
     * Frena si los kilos no entran en lo libre. `$propios` son los que la misma
     * faena ya reservaba, al editarla. Sin reservas, el mensaje es el de siempre.
     */
    private function exigirKilosLibres(AprovechamientoPesq $cupo, float $kilos, float $propios): void
    {
        $libre = $cupo->libreKg() + $propios;

        if ($kilos <= $libre) {
            return;
        }

        $reservadoPorOtras = max(0.0, $cupo->kilosReservados() - $propios);

        throw $reservadoPorOtras > 0.0
            ? PermisoOperativoException::excedeLibre($kilos, $libre, $reservadoPorOtras)
            : PermisoOperativoException::excedeCupo($kilos, $libre);
    }
}

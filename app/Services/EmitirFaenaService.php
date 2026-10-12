<?php

namespace App\Services;

use App\Enums\ConceptoArancel;
use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoFaena;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\PermisoFaena;
use App\Sireb\SinPrecioException;
use App\Sireb\TarifarioSireb;
use Illuminate\Support\Facades\DB;

/**
 *  PASO 4 DEL FLUJO — el PERMISO DE FAENA, una salida de pesca
 */
class EmitirFaenaService
{
    public function __construct(
        private readonly CorrelativoService $correlativos,
        private readonly TarifarioSireb $tarifario,
        private readonly LiquidarSirebService $liquidaciones,
    ) {}

    /**
     * Registra la solicitud de una salida. NACE PENDIENTE: no autoriza nada
     * hasta que se pague en SIREB. Sus kilos son los de la autorización, fijos:
     * la faena NO descuenta nada (12/10/2026).
     *
     * @param  array<string, string|null>  $papel  Los renglones del talonario:
     *                                             embarcacion, propietario, comandante_barco,
     *                                             matricula_naval, nro_kardex, region_desde,
     *                                             region_hasta.
     */
    public function emitir(
        Carnet $carnet,
        // La tarifa del servicio de la faena en SIREB que eligió ventanilla.
        string $tarifaId,
        array $papel = [],
    ): PermisoFaena {
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

        $cupo = AprovechamientoPesq::query()->find($carnet->aprovechamiento_id);

        if ($cupo === null || ! $cupo->estaEnFecha()) {
            throw PermisoOperativoException::sinCupoVigente();
        }

        // La autorización sin cobrar tiene su propio mensaje, y hace falta.
        if ($cupo->estado === EstadoAprovechamiento::Pendiente) {
            throw PermisoOperativoException::cupoPendienteDePago();
        }

        // Fuera de la transacción: una llamada a SIREB no debe tener filas bloqueadas.
        $precio = $this->precioDeLaFaena($tarifaId);

        $faena = DB::transaction(function () use ($carnet, $cupo, $papel, $precio): PermisoFaena {
            $faena = PermisoFaena::create([
                'carnet_id' => $carnet->id,
                // El número lo pone el sistema: correlativo global y continuo, como el talonario.
                'nro' => $this->correlativos->siguienteContinuo(PermisoFaena::SERIE),
                // Copia congelada del precio de SIREB: ver PermisoFaena::montoACobrar().
                'monto' => $precio['monto'],
                'sireb_tarifa_id' => $precio['tarifa_id'],
                // Fijos: los disponibles de la autorización. No se descuentan de ella.
                'kilos_extraidos' => (float) $cupo->kilos_disponibles,
                ...$this->renglonesDelPapel($papel),
                // Salida y desembarque quedan en NULL: los escribe la aprobación.
                'estado' => EstadoFaena::Pendiente,
                'fecha_solicitud' => now()->toDateString(),
            ]);

            // Su llave pública, en la misma transacción: sin código no se puede verificar.
            $faena->asignarCodigo();
            $this->liquidaciones->preparar($faena);

            return $faena;
        });

        $this->liquidaciones->enviarSinFrenar($faena);

        return $faena;
    }

    /**
     * Corregir el borrador: solo los renglones del papel. El carnet y los kilos no
     * se tocan; cambiarlos es otra faena.
     *
     * @param  array<string, string|null>  $papel  Los renglones del talonario.
     */
    public function editar(PermisoFaena $faena, array $papel = []): PermisoFaena
    {
        return DB::transaction(function () use ($faena, $papel): PermisoFaena {
            $bloqueada = PermisoFaena::query()->whereKey($faena->id)->lockForUpdate()->firstOrFail();

            // Con la copia bloqueada: entre que la pantalla se dibujó y llegó el
            // submit, el pago pudo aprobarla.
            if (! $bloqueada->estado->permiteEdicion()) {
                throw PermisoOperativoException::faenaNoSePuedeEditar(
                    mb_strtolower($bloqueada->estado->etiqueta()),
                );
            }

            $bloqueada->update($this->renglonesDelPapel($papel));

            // La original refrescada, no la copia bloqueada. Ver CLAUDE.md.
            return $faena->refresh();
        });
    }

    /**
     *  Eliminar una faena cargada por error
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

            // El motivo se deja y se borra: `Auditable` ya engancha el `deleted`,
            // y registrarlo a mano además dejaría el hecho dos veces. El número
            // NO se reusa: la serie queda con un hueco que el motivo explica.
            $bloqueada->motivoAuditoria = $motivo;
            $bloqueada->delete();
        });
    }

    /**
     * Da de baja una faena aprobada, con motivo. Igual que el carnet y la guía:
     * queda en el historial.
     */
    public function revocar(PermisoFaena $faena, string $motivo): PermisoFaena
    {
        if (trim($motivo) === '') {
            throw PermisoOperativoException::motivoObligatorio();
        }

        if ($faena->estado === EstadoFaena::Revocado) {
            throw PermisoOperativoException::faenaYaRevocada();
        }

        if (! $faena->estado->permiteRevocacion()) {
            throw PermisoOperativoException::faenaNoSeRevoca(mb_strtolower($faena->estado->etiqueta()));
        }

        DB::transaction(function () use ($faena, $motivo): void {
            $bloqueada = PermisoFaena::query()->whereKey($faena->id)->lockForUpdate()->firstOrFail();

            // Otra ventanilla pudo revocarla mientras tanto.
            if (! $bloqueada->estado->permiteRevocacion()) {
                throw PermisoOperativoException::faenaNoSeRevoca(mb_strtolower($bloqueada->estado->etiqueta()));
            }

            // El motivo antes del update: `Auditable` lo lee en el evento.
            $bloqueada->motivoAuditoria = $motivo;
            $bloqueada->update(['estado' => EstadoFaena::Revocado]);
        });

        return $faena->refresh();
    }

    /**
     * El precio de una tarifa del servicio de la faena (Catálogos › Aranceles)
     * según SIREB, el de ahora. Sin él no se emite: ninguna tarifa se escribe a mano.
     *
     * @return array{monto: float, tarifa_id: string, tarifa: array}
     */
    public function precioDeLaFaena(string $tarifaId): array
    {
        try {
            return $this->tarifario->precio(ConceptoArancel::Faena, $tarifaId);
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
}

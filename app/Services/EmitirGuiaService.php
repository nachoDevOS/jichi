<?php

namespace App\Services;

use App\Enums\EstadoGuia;
use App\Exceptions\PermisoOperativoException;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 *  PASO 4 DEL FLUJO, RAMA COMERCIALIZADOR — la GUÍA DE MOVIMIENTO
 */
class EmitirGuiaService
{
    /**
     * Emite el amparo de un traslado.
     */
    public function emitir(
        Carnet $carnet,
        string $codigo,
        string $origen,
        string $destino,
        float $pesoKg,
        bool $esPiscicultura,
        ?Carbon $emision = null,
    ): GuiaMovimiento {
        $emision ??= now();
        $codigo = trim($codigo);

        if (! $carnet->tipo_actor->emiteGuias()) {
            throw PermisoOperativoException::actorNoEmite('guías de movimiento', $carnet->tipo_actor);
        }

        if (! $carnet->estaVigente()) {
            throw PermisoOperativoException::carnetNoVigente($carnet->estado);
        }

        return DB::transaction(function () use ($carnet, $codigo, $origen, $destino, $pesoKg, $esPiscicultura, $emision): GuiaMovimiento {
            /*
             * La comprobación va DENTRO de la transacción aunque no haya nada
             * bloqueado: entre el control y el INSERT otra ventanilla puede usar
             * el mismo código, y ahí el índice único lo rechazaría con un error
             * de base de datos ilegible. Esto convierte esa carrera en un
             * mensaje que el operador entiende, y el índice queda como red.
             */
            if (GuiaMovimiento::query()->where('codigo_guia', $codigo)->exists()) {
                throw PermisoOperativoException::numeroRepetido('una guía', $codigo);
            }

            return GuiaMovimiento::create([
                // La guía cuelga del CARNET: la persona se lee de él.
                'carnet_id' => $carnet->id,

                /*
                 * LA ASOCIACIÓN SE COPIA DEL CARNET, no se pregunta de nuevo.
                 */
                'asociacion_id' => $carnet->asociacion_id,

                'codigo_guia' => $codigo,
                'origen' => $origen,
                'destino' => $destino,
                'peso_total_kg' => $pesoKg,
                'es_piscicultura' => $esPiscicultura,

                'estado' => EstadoGuia::Activa,
                'fecha_emision' => $emision,
                // Se GUARDA el vencimiento calculado en vez de derivarlo al
                // leer: si mañana la resolución cambia el plazo, las guías ya
                // emitidas tienen que seguir venciendo cuando dice el papel que
                // va dentro del camión.
                'fecha_vencimiento' => GuiaMovimiento::vencimientoDesde($emision),
            ]);
        });
    }

    /**
     * Registra que la carga llegó a destino.
     */
    public function cerrar(GuiaMovimiento $guia, ?float $pesoReal = null): GuiaMovimiento
    {
        if ($guia->estado !== EstadoGuia::Activa) {
            throw PermisoOperativoException::noSePuedeCerrar(mb_strtolower($guia->estado->etiqueta()));
        }

        return DB::transaction(function () use ($guia, $pesoReal): GuiaMovimiento {
            $bloqueada = GuiaMovimiento::query()->whereKey($guia->id)->lockForUpdate()->firstOrFail();

            $cambios = ['estado' => EstadoGuia::Cerrada];

            if ($pesoReal !== null && abs($pesoReal - (float) $bloqueada->peso_total_kg) > 0.001) {
                $cambios['peso_total_kg'] = $pesoReal;
            }

            $bloqueada->update($cambios);

            // Se devuelve la instancia ORIGINAL refrescada: quien llamó tiene
            // esa en la mano, y darle la copia bloqueada lo deja con el estado
            // viejo en memoria.
            return $guia->refresh();
        });
    }

    /**
     * Da de baja una guía, con motivo.
     */
    public function anular(GuiaMovimiento $guia, string $motivo): GuiaMovimiento
    {
        if (trim($motivo) === '') {
            throw PermisoOperativoException::motivoObligatorio();
        }

        if ($guia->estado === EstadoGuia::Anulada) {
            throw PermisoOperativoException::guiaYaAnulada();
        }

        if ($guia->estado === EstadoGuia::Cerrada) {
            throw PermisoOperativoException::cerradaNoSeAnula();
        }

        return DB::transaction(function () use ($guia, $motivo): GuiaMovimiento {
            $bloqueada = GuiaMovimiento::query()->whereKey($guia->id)->lockForUpdate()->firstOrFail();

            // El motivo se deja ANTES de guardar: el trait Auditable lo lee en el
            // evento `updated`. Sin él la auditoría diría QUÉ cambió pero no POR
            // QUÉ, que en un papel anulado es lo único que sirve después.
            $bloqueada->motivoAuditoria = $motivo;
            $bloqueada->update(['estado' => EstadoGuia::Anulada]);

            return $guia->refresh();
        });
    }
}

<?php

namespace App\Services;

use App\Enums\EstadoPermiso;
use App\Exceptions\PermisoOperativoException;
use App\Models\Carnet;
use App\Models\Faena;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 *  EMISIÓN Y BAJA DE FAENAS — el permiso por salida de pesca
 * ============================================================================
 *
 * El carnet es la llave anual; la faena autoriza UN viaje. Este servicio es el
 * único lugar donde se comprueba que ese viaje se pueda autorizar.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ LAS REGLAS ESTÁN ACÁ Y NO EN EL CONTROLADOR NI EN EL MODELO
 * ----------------------------------------------------------------------------
 *
 * En el CONTROLADOR no, porque el mismo caso de uso lo van a necesitar un
 * comando que cargue el talonario viejo y las pruebas. Escrito ahí, los dos lo
 * copian y las copias quedan viejas.
 *
 * En el MODELO tampoco: `Carnet::puedeEmitirFaenas()` responde sí o no, que es
 * lo que la pantalla necesita para mostrar u ocultar el botón. Pero quien IMPIDE
 * la emisión tiene que además EXPLICAR cuál de las condiciones falló, y eso es
 * trabajo de un servicio, no de un accesor.
 *
 * Ver `SolicitudCarnetService`, que aplica el mismo reparto.
 */
class FaenaService
{
    /**
     * ========================================================================
     *  EMITIR UNA FAENA
     * ========================================================================
     *
     * Tres comprobaciones, en este orden:
     *
     *   1. que el rubro del carnet emita faenas;
     *   2. que el carnet valga hoy;
     *   3. que el número del talonario no esté usado.
     *
     * EL ORDEN NO ES CASUAL. La primera es la que más se equivoca el operador
     * —la persona tiene dos carnets en pantalla y elige el que no era—, así que
     * conviene que sea el primer mensaje que reciba. La tercera va al final
     * porque es la única que consulta la base.
     *
     * @param  array<string, mixed>  $datos
     */
    public function emitir(Carnet $carnet, array $datos): Faena
    {
        $this->comprobarCarnet($carnet);

        $numero = trim((string) ($datos['nro_permiso'] ?? ''));

        /*
         * Se comprueba ANTES de insertar y no se deja fallar al índice único.
         *
         * No es desconfianza del índice —es él quien garantiza de verdad, porque
         * entre este SELECT y el INSERT otra ventanilla puede meter el mismo
         * número—: es que esto corre dentro de una transacción, y en PostgreSQL
         * un INSERT fallido la aborta entera. Sin savepoints no se puede
         * reintentar ni dar un mensaje decente.
         */
        if (Faena::query()->where('nro_permiso', $numero)->exists()) {
            throw PermisoOperativoException::numeroRepetido('faena', $numero);
        }

        return DB::transaction(fn (): Faena => $carnet->faenas()->create([
            'nro_permiso' => $numero,
            'nro_recibo' => $datos['nro_recibo'] ?? null,
            // El monto puede no venir del formulario: la tarifa por defecto la
            // pone la base. Se pisa solo si el operador escribió otra.
            ...(isset($datos['monto']) ? ['monto' => $datos['monto']] : []),
            'embarcacion' => $datos['embarcacion'] ?? null,
            'propietario' => $datos['propietario'] ?? null,
            'comandante_barco' => $datos['comandante_barco'] ?? null,
            'matricula_naval' => $datos['matricula_naval'] ?? null,
            'nro_kardex' => $datos['nro_kardex'] ?? null,
            'region_desde' => $datos['region_desde'] ?? null,
            'region_hasta' => $datos['region_hasta'] ?? null,
            'fecha_salida' => $datos['fecha_salida'],
            'fecha_desembarque' => $datos['fecha_desembarque'],
            'cantidad_autorizada_kg' => $datos['cantidad_autorizada_kg'],
        ]));
    }

    /**
     * ========================================================================
     *  ANULAR UNA FAENA
     * ========================================================================
     *
     * NO SE BORRA, Y ESA ES LA DECISIÓN. El número salió de un talonario de
     * papel: ya se gastó, la hoja puede estar circulando por el río, y un hueco
     * en la serie no se puede explicar después. Peor todavía, borrar dejaría el
     * número libre para que el índice único lo aceptara de nuevo, y entonces dos
     * permisos distintos podrían decir ser el mismo papel.
     *
     * EL MOTIVO ES OBLIGATORIO porque es lo único que va a quedar explicando por
     * qué ese número no vale. Se escribe en `observaciones` —no hay columna
     * `motivo_anulacion`— y el trait Auditable guarda además quién y cuándo.
     */
    public function anular(Faena $faena, ?string $motivo): Faena
    {
        if ($faena->estado === EstadoPermiso::Anulado) {
            throw PermisoOperativoException::yaAnulado('faena');
        }

        $motivo = trim((string) $motivo);

        if ($motivo === '') {
            throw PermisoOperativoException::motivoObligatorio();
        }

        return DB::transaction(function () use ($faena, $motivo): Faena {
            $faena->update([
                'estado' => EstadoPermiso::Anulado,
                // Se ANTEPONE al texto que hubiera, en vez de reemplazarlo: lo
                // que el operador anotó al emitir sigue siendo parte del
                // expediente del papel.
                'observaciones' => trim("ANULADA: {$motivo}\n".(string) $faena->observaciones),
            ]);

            return $faena->refresh();
        });
    }

    /**
     * Las dos condiciones que el carnet tiene que cumplir.
     *
     * Se pregunta por `emiteFaenas()` y no por el nombre del rubro, por lo mismo
     * que `requiereCapacidad()`: el catálogo lo edita la unidad desde el panel y
     * el mismo rubro figura como «Pescador» o como «Faena» según quién lo cargó.
     */
    private function comprobarCarnet(Carnet $carnet): void
    {
        $carnet->loadMissing('rubro');

        if (! ($carnet->rubro?->emiteFaenas() ?? false)) {
            throw PermisoOperativoException::rubroNoEmite('faenas', $carnet->rubro?->nombre ?? 'sin rubro');
        }

        if (! $carnet->estaVigente()) {
            throw PermisoOperativoException::carnetNoVigente(
                $carnet->rubro?->nombre ?? 'sin rubro',
                (int) $carnet->gestion,
                $carnet->estado,
            );
        }
    }
}

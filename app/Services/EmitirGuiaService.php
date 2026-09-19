<?php

namespace App\Services;

use App\Enums\EstadoGuia;
use App\Exceptions\PermisoOperativoException;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 *  PASO 4 DEL FLUJO, RAMA COMERCIALIZADOR — la GUÍA DE MOVIMIENTO
 * ============================================================================
 *
 *     carnet (comercializador) ──▶ guía ──▶ ampara UN traslado
 *
 * Lo que la faena es para el pescador, la guía es para el comercializador: el
 * carnet habilita el año, la guía habilita el viaje.
 *
 * ----------------------------------------------------------------------------
 *  TRES DIFERENCIAS CON LAS FAENAS, Y LAS TRES IMPORTAN
 * ----------------------------------------------------------------------------
 *
 *   1. NO TOCA NINGÚN CUPO. La comercialización no se autoriza por volumen, así
 *      que acá no hay recurso escaso que bloquear ni saldo que restar. Por eso
 *      este servicio no bloquea filas al emitir: no hay nada que dos ventanillas
 *      puedan gastar dos veces.
 *
 *   2. LLEVA UN DESCUENTO. Si la carga es de piscicultura, el arancel se cobra
 *      al 50%: el pescado de criadero no sale del río y no consume el recurso
 *      que la tasa viene a proteger. La regla vive en
 *      `GuiaMovimiento::factorArancel()` y no se replica en ningún lado.
 *
 *   3. SÍ SE ANULA. `EstadoGuia` tiene ese estado y `EstadoFaena` no: una faena
 *      de más se deja vencer y libera su volumen sola, pero una guía emitida mal
 *      ampara un camión que puede estar en la ruta, y hay que poder decir que
 *      ese papel no vale.
 *
 * ----------------------------------------------------------------------------
 *  LAS FECHAS SON MOMENTOS, NO DÍAS
 * ----------------------------------------------------------------------------
 *
 * Cinco días se cuentan desde la HORA de emisión: una guía emitida a las 18:00
 * del lunes vence a las 18:00 del sábado, no a la medianoche del viernes. Con
 * fechas sin hora se le regalaría o se le quitaría casi un día al transportista.
 */
class EmitirGuiaService
{
    /**
     * Emite el amparo de un traslado.
     *
     * ------------------------------------------------------------------------
     *  EL CÓDIGO LO ESCRIBE EL OPERADOR, IGUAL QUE EL NÚMERO DE FAENA
     * ------------------------------------------------------------------------
     *
     * Sale de un TALONARIO DE PAPEL que viaja dentro del camión. El sistema no
     * lo genera: si lo generara, el número del sistema y el del papel serían dos
     * cosas distintas, y un control en ruta compara contra el papel.
     *
     * A diferencia del número de faena —que es correlativo DENTRO de un cupo—
     * este es único GLOBAL: un control lee un código y tiene que llegar a UNA
     * guía, sin preguntar antes de quién es.
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
                /*
                 * LA ASOCIACIÓN SE COPIA DEL CARNET, no se pregunta de nuevo.
                 *
                 * Es la que certificó a la persona al emitirle la credencial, y
                 * preguntarla otra vez abriría la puerta a que una guía diga un
                 * gremio y el carnet que la respalda diga otro.
                 */
                'beneficiario_com_id' => $carnet->beneficiario_id,
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
     *
     * ------------------------------------------------------------------------
     *  EL PESO SE PUEDE CORREGIR AL CERRAR, Y HAY QUE PODER
     * ------------------------------------------------------------------------
     *
     * Lo declarado al salir es lo que dijo la balanza del origen; al llegar se
     * vuelve a pesar y casi nunca coincide al kilo. Corregirlo acá es lo que
     * hace que el número que queda en el sistema sea el real y no el estimado.
     *
     * A diferencia de la faena, corregir hacia arriba NO tiene tope: no hay cupo
     * que exceder. Lo único que cambia es el arancel si el peso entrara alguna
     * vez en el cálculo — hoy no, porque la tarifa es plana.
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
     *
     * ------------------------------------------------------------------------
     *  SE ANULA Y NO SE BORRA
     * ------------------------------------------------------------------------
     *
     * El código sale de un talonario de papel que puede estar circulando dentro
     * de un camión. Borrar la fila deja un hueco en la serie que nadie puede
     * explicar y —peor— libera un código que el índice único volvería a
     * aceptar: dos traslados distintos podrían terminar diciendo ser el mismo
     * papel.
     *
     * ------------------------------------------------------------------------
     *  Y UNA GUÍA CERRADA NO SE ANULA
     * ------------------------------------------------------------------------
     *
     * Cerrar significa que la carga llegó: el traslado ocurrió y esta guía lo
     * amparó. Anularla después sería declarar que nunca amparó nada, y deja un
     * viaje real sin respaldo — justo lo contrario de para qué existe.
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

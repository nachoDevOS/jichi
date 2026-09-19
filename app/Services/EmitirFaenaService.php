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
 * ============================================================================
 *  PASO 4 DEL FLUJO — el PERMISO DE FAENA, una salida de pesca
 * ============================================================================
 *
 *     carnet (pescador) ──▶ faena ──▶ descuenta kilos de la bolsa madre
 *
 * El carnet es la llave ANUAL; con él solo no se sale a trabajar. Cada salida
 * se autoriza con una faena, que dice cuántos kilos se pueden extraer y hasta
 * cuándo vale.
 *
 * ----------------------------------------------------------------------------
 *  LA REGLA CENTRAL: LOS KILOS SALEN DE UN POZO QUE SE VACÍA
 * ----------------------------------------------------------------------------
 *
 * Emitir una faena resta del saldo de la bolsa madre, y cuando el saldo llega a
 * cero no se emiten más. Esa resta NO está en ninguna columna: es
 * `volumen_total_kg` menos los kilos de las faenas que consumen cupo, calculada
 * al leer. Ver AprovechamientoPesq::saldoKg().
 *
 * ----------------------------------------------------------------------------
 *  …SALVO EN MODO FLEXIBLE, Y ESO LO DECIDE EL .env
 * ----------------------------------------------------------------------------
 *
 * `APROVECHAMIENTO_ESTRICTO=false` apaga la comprobación del tope: las faenas se
 * emiten aunque superen el volumen otorgado. Existe para poner al día un padrón
 * donde el papel ya fue más allá del cupo, y para arrancar en una unidad que
 * todavía no tiene la escala cargada del todo.
 *
 * LO QUE EL MODO FLEXIBLE NO AFLOJA:
 *
 *   - la FECHA del cupo, que sigue mandando. Lo que se relaja es el tope en
 *     kilos, no el calendario: una faena colgada de un cupo del año pasado
 *     sería un permiso sin ninguna autorización detrás.
 *   - el CARNET, que tiene que seguir vigente y ser de pescador.
 *   - el NÚMERO del talonario, que no se puede repetir.
 *   - el DATO: el exceso se sigue midiendo en `kilosExcedidos()` y las
 *     pantallas lo muestran, así que al volver a estricto se sabe exactamente
 *     quién está por encima.
 *
 * ----------------------------------------------------------------------------
 *  SE BLOQUEA EL APROVECHAMIENTO, NO EL CARNET
 * ----------------------------------------------------------------------------
 *
 * Es la fila que contiene el recurso escaso. Dos ventanillas emitiendo faenas
 * al mismo pescador a la vez leerían las dos el mismo saldo —ninguna ve la
 * faena de la otra, que todavía no está escrita— y las dos pasarían el control:
 * el cupo terminaría excedido sin que nada lo delate, porque cada faena por
 * separado se ve correcta.
 */
class EmitirFaenaService
{
    /**
     * Emite el permiso de una salida.
     *
     * ------------------------------------------------------------------------
     *  EL NÚMERO LO ESCRIBE EL OPERADOR, Y NO SE GENERA SOLO
     * ------------------------------------------------------------------------
     *
     * Sale de un TALONARIO DE PAPEL que el pescador se lleva. El sistema PROPONE
     * el siguiente —para no hacer contar hojas— pero no lo impone: si la hoja
     * que el operador tiene en la mano dice otro número, hay algo que conviene
     * mirar antes de seguir, no autocorregir en silencio.
     *
     * Lo único que el sistema garantiza es que no se repita dentro del mismo
     * cupo, y eso lo sostiene el índice único `(aprovechamiento_id,
     * numero_faena)` además de esta comprobación.
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
             *
             * Un cupo AGOTADO no está «vigente» —su estado no habilita— pero SÍ
             * está en fecha, y lo que corresponde decirle al operador es «quedan
             * 0 kg, hay que tramitar otro cupo», no «no tiene aprovechamiento». Con
             * `estaVigente()` el mensaje mandaba a otorgar un cupo nuevo, que es
             * justo lo que la regla de una bolsa por persona iba a rechazar.
             *
             * El caso de verdad sin cupo utilizable —el vencido— sigue cayendo
             * acá, y la comprobación del saldo de abajo da el mensaje exacto
             * para el agotado.
             */
            if (! $cupo->estaEnFecha()) {
                throw PermisoOperativoException::sinCupoVigente();
            }

            /*
             * EL CUPO SIN COBRAR TIENE SU PROPIO MENSAJE, y hace falta.
             *
             * Cae acá aunque esté en fecha y con saldo entero, porque lo que
             * autoriza a pescar es la concesión PAGADA. Con el mensaje genérico
             * de «no tiene aprovechamiento vigente», el operador saldría a
             * otorgar otro —y la regla de una bolsa por persona lo rechazaría—
             * cuando lo único que falta es cobrar el que ya está cargado.
             *
             * Va DESPUÉS de la fecha: un cupo pendiente y además vencido ya no
             * se arregla cobrándolo.
             */
            if ($cupo->estado === EstadoAprovechamiento::Pendiente) {
                throw PermisoOperativoException::cupoPendienteDePago();
            }

            /*
             * EL TOPE SOLO SE HACE CUMPLIR EN MODO ESTRICTO.
             *
             * La comprobación va acá adentro —con la fila del cupo bloqueada— y
             * no en el Request, porque el saldo puede moverlo otra ventanilla en
             * el mismo segundo.
             */
            if (AprovechamientoPesq::modoEstricto()) {
                $saldo = $cupo->saldoKg();

                if ($kilos > $saldo) {
                    throw PermisoOperativoException::excedeCupo($kilos, $saldo);
                }
            }

            if ($cupo->faenas()->where('numero_faena', $numeroFaena)->exists()) {
                throw PermisoOperativoException::numeroRepetido('una faena', (string) $numeroFaena);
            }

            $faena = PermisoFaena::create([
                'aprovechamiento_id' => $cupo->id,
                'carnet_id' => $carnet->id,
                'numero_faena' => $numeroFaena,
                'kilos_extraidos' => $kilos,
                'estado' => EstadoFaena::Activo,
                'fecha_salida' => $salida->toDateString(),
                // Se GUARDA la fecha calculada en vez de derivarla al leer: si
                // mañana la resolución baja el plazo, los permisos ya emitidos
                // tienen que seguir venciendo cuando dice el papel que el
                // pescador tiene en la mano.
                'fecha_limite' => PermisoFaena::limiteDesde($salida)->toDateString(),
            ]);

            /*
             * SI ESTA FAENA DEJÓ EL CUPO EN CERO, EL CUPO PASA A `agotado`.
             *
             * El estado es redundante con el saldo —que se calcula— y aun así
             * vale la pena: permite filtrar y contar en los listados sin
             * recalcular una resta por fila, y distingue «se acabaron los kilos»
             * de «se acabó el tiempo», que se resuelven distinto. Ver
             * EstadoAprovechamiento.
             *
             * SE MARCA TAMBIÉN EN MODO FLEXIBLE, y es a propósito: que no queden
             * kilos es un hecho, lo emita o no el sistema. En ese modo el estado
             * ya no bloquea nada —`puedeEmitirFaena()` ni lo mira— pero deja el
             * listado diciendo la verdad, que es lo que hace falta el día que se
             * vuelva a estricto.
             */
            if ($cupo->fresh()->saldoKg() <= 0.0) {
                $cupo->update(['estado' => EstadoAprovechamiento::Agotado]);
            }

            return $faena;
        });
    }

    /**
     * Registra que el pescador volvió y descargó.
     *
     * ------------------------------------------------------------------------
     *  COMPLETAR NO CAMBIA EL SALDO, Y ESO SORPRENDE
     * ------------------------------------------------------------------------
     *
     * Los kilos ya estaban descontados desde que la faena se emitió: una faena
     * ACTIVA consume cupo aunque todavía no se haya descargado nada. Si solo
     * contaran las completadas, un pescador podría tener diez faenas abiertas
     * por el volumen entero cada una.
     *
     * Completar es el cierre del circuito: deja el volumen firme y saca la
     * faena de la lista de papeles que andan dando vueltas sin registrar la
     * vuelta.
     *
     * ------------------------------------------------------------------------
     *  LOS KILOS SE PUEDEN CORREGIR AL CERRAR, Y HAY QUE PODER
     * ------------------------------------------------------------------------
     *
     * Lo declarado al salir es una previsión; lo que se descargó lo dice la
     * balanza. Corregir hacia ARRIBA vuelve a comprobar el saldo —si no entra,
     * se rechaza— porque de lo contrario cerrar una faena sería la forma de
     * saltear el cupo.
     */
    public function completar(PermisoFaena $faena, ?float $kilosReales = null): PermisoFaena
    {
        if ($faena->estado !== EstadoFaena::Activo) {
            throw PermisoOperativoException::noSePuedeCompletar(
                mb_strtolower($faena->estado->etiqueta()),
            );
        }

        return DB::transaction(function () use ($faena, $kilosReales): PermisoFaena {
            $cupo = AprovechamientoPesq::query()
                ->whereKey($faena->aprovechamiento_id)
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
     *
     * Vive acá y no en el modelo porque es una ESCRITURA, y el modelo solo
     * calcula. Se llama después de cualquier movimiento de kilos: emitir,
     * corregir al cerrar, o vencer una faena desde el comando diario.
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
            : EstadoAprovechamiento::Activo;

        if ($cupo->estado !== $deberiaEstar) {
            $cupo->update(['estado' => $deberiaEstar]);
        }
    }
}

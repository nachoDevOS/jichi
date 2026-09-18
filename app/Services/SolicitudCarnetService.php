<?php

namespace App\Services;

use App\Enums\EstadoCarnet;
use App\Enums\EstadoTramite;
use App\Enums\TipoTramite;
use App\Exceptions\SolicitudInvalidaException;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\Configuracion;
use App\Models\Rubro;
use App\Models\Tramite;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * ============================================================================
 *  EL CASO DE USO CENTRAL DEL MÓDULO
 * ============================================================================
 *
 * Todo lo que hace este servicio cabe en una frase: «un beneficiario pide que
 * se le habilite un rubro». Lo que la vuelve interesante es que esa frase
 * significa dos cosas distintas según lo que ya haya en la base.
 *
 * ----------------------------------------------------------------------------
 *  REGLA A — un carnet por persona, POR RUBRO y por gestión
 * ----------------------------------------------------------------------------
 *
 *      ¿tiene carnet DE ESTE RUBRO en la gestión en curso?
 *
 *          NO ──▶ se CREA el carnet          ──▶ tipo = EMISIÓN INICIAL
 *          SÍ ──▶ se REUTILIZA el que tiene  ──▶ tipo = ACTUALIZACIÓN
 *
 * El tipo NO lo elige el operador: lo decide esta pregunta. Ver el comentario de
 * App\Enums\TipoTramite sobre por qué.
 *
 * LA PREGUNTA LLEVA EL RUBRO, y eso es lo que cambió respecto del modelo viejo.
 * Antes el carnet era uno por persona y año, y pedir otra actividad lo hacía
 * crecer. Hoy cada actividad es un documento propio: un pescador que además
 * comercializa termina con DOS carnets en 2026, cada uno con su plástico, su
 * firma de validación y su cupo autorizado.
 *
 * ----------------------------------------------------------------------------
 *  REGLA B — el trámite y sus respaldos
 * ----------------------------------------------------------------------------
 *
 * En los dos casos se escribe una fila en `tramites`, colgada del carnet y del
 * rubro, en estado PENDIENTE y con las rutas de los dos adjuntos obligatorios
 * (fotocopia de CI y certificado de la asociación).
 *
 * LO QUE NO PASA ACÁ: el carnet todavía NO habilita a nadie. La fila existe
 * —hay que crearla para poder colgarle el trámite— pero el cupo autorizado se
 * consolida recién al APROBAR, y hasta entonces ningún trámite del carnet está
 * aprobado, que es lo que `Carnet::puedeImprimirse()` exige para dejar sacar el
 * plástico. Si el carnet habilitara desde el alta, el pescador quedaría
 * autorizado por el solo hecho de haber presentado papeles.
 *
 * ----------------------------------------------------------------------------
 *  REGLA C — pagos parciales
 * ----------------------------------------------------------------------------
 *
 * El costo se copia del rubro a `tramites.monto_requerido` y se cubre con uno o
 * varios depósitos. Eso lo maneja PagoTramiteService; acá solo se registran los
 * que el pescador trajo el mismo día, dentro de la misma transacción.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ TODO ESTO NO VA EN EL CONTROLADOR
 * ----------------------------------------------------------------------------
 *
 * Porque son reglas del negocio, no de una pantalla. El mismo caso de uso lo van
 * a necesitar el formulario del panel, un comando de consola para migrar el
 * padrón en papel y las pruebas automáticas. Escrito en el controlador, el
 * comando y las pruebas tendrían que copiarlo —y ahí es donde las copias se
 * quedan viejas—.
 */
class SolicitudCarnetService
{
    /*
     * EL CARNET NO TIENE COLUMNA DE CÓDIGO, Y ESTE SERVICIO NO GENERA NINGUNO.
     *
     * Hubo una columna `codigo` —primero el correlativo CARNET-2026-0001,
     * después un valor al azar— y se retiró. Hoy el carnet se nombra de dos
     * maneras y las dos salen de columnas que ya existen:
     *
     *   - para IMPRIMIR y dictar, el `id` rellenado con ceros: Carnet::registro();
     *   - para VERIFICAR, la firma al azar: Carnet::nuevaFirma().
     *
     * Por eso lo único que este servicio pide al crear un carnet es la firma.
     * El registro no se genera: ya es el id que asigna la base.
     *
     * CorrelativoService sigue existiendo —es infraestructura genérica, con su
     * tabla y su bloqueo— pero este módulo ya no lo usa.
     */

    /** Carpeta de storage donde van los respaldos del expediente. */
    private const CARPETA_ADJUNTOS = 'tramites';

    public function __construct(
        private readonly ArchivoTramiteService $archivos,
        private readonly PagoTramiteService $pagos,
    ) {}

    // ==================================================================
    //  ALTA DE SOLICITUD — Reglas A, B y C
    // ==================================================================

    /**
     * Registra una solicitud completa. Todo o nada.
     *
     * ------------------------------------------------------------------------
     *  EL ORDEN DE LOS PASOS ES LA PARTE IMPORTANTE
     * ------------------------------------------------------------------------
     *
     *   1. Comprobaciones que no dependen del carnet (persona viva, rubro activo)
     *   2. SUBIDA DE ARCHIVOS  ← fuera de la transacción, a propósito
     *   3. Transacción: leer/crear carnet, crear trámite, registrar pagos
     *   4. Si algo del paso 3 falla: rollback automático + borrar lo del paso 2
     *
     * El paso 2 va antes y no adentro porque una transacción de base de datos NO
     * deshace escrituras en disco: subiendo adentro, un rollback dejaría los
     * adjuntos escritos para siempre sin dueño. Al revés, lo peor que puede
     * pasar es un archivo huérfano que el catch borra. Está explicado con más
     * detalle en ArchivoTramiteService.
     *
     * @param  array{ciFile: UploadedFile, certAsociacionFile: UploadedFile}  $adjuntos
     * @param  array<int, array{nro_transaccion: string, monto: float|string, comprobante: UploadedFile, fecha_pago?: string|null, observaciones?: string|null}>  $pagosIniciales
     * @param  string|null  $asociacion  La que certifica al beneficiario, según el papel adjunto.
     * @param  float|string|null  $capacidadKg  Cupo SOLICITADO en kilos. Pasa a ser el autorizado recién al aprobar. Null si todavía no se definió.
     *
     * @throws SolicitudInvalidaException
     */
    public function registrar(
        Beneficiario $beneficiario,
        Rubro $rubro,
        array $adjuntos,
        array $pagosIniciales = [],
        ?string $observaciones = null,
        ?int $gestion = null,
        ?string $asociacion = null,
        float|string|null $capacidadKg = null,
    ): Tramite {
        $gestion ??= (int) now()->format('Y');

        // Paso 1. Lo que se puede rechazar sin tocar el disco se rechaza acá:
        // no tiene sentido subir 3 MB para descubrir después que el rubro está
        // dado de baja.
        $this->verificarBeneficiario($beneficiario);
        $this->verificarRubro($rubro);

        /*
         * Paso 2. TODOS los archivos, fuera de la transacción.
         *
         * Los dos adjuntos del expediente Y la boleta de cada pago. Que las
         * boletas se suban acá y no dentro de PagoTramiteService es la parte
         * fácil de olvidar: subidas adentro de la transacción, el rollback
         * deshace las filas de `pagos` pero deja los archivos escritos en el
         * disco sin dueño y sin forma de saber después que sobran.
         *
         * Subir primero tiene además una razón práctica: mandar a s3 puede
         * tardar segundos, y sostener una transacción abierta mientras tanto
         * mantendría filas bloqueadas sin ninguna necesidad.
         */
        $rutas = $this->archivos->guardarVarios([
            'ciFile' => $adjuntos['ciFile'],
            'certAsociacionFile' => $adjuntos['certAsociacionFile'],
        ], self::CARPETA_ADJUNTOS);

        try {
            $pagosIniciales = $this->subirComprobantes($pagosIniciales, $rutas);
        } catch (Throwable $e) {
            // Falló una boleta: los adjuntos que ya subieron no le sirven a
            // nadie porque la solicitud no se va a registrar.
            $this->archivos->descartar(array_values($rutas));

            throw $e;
        }

        try {
            // Paso 3. DB::transaction() hace commit al salir bien y rollback
            // ante cualquier excepción, incluidas las de SolicitudInvalida que
            // lanzan las comprobaciones de adentro. No hay ningún
            // beginTransaction/commit escrito a mano justamente para que no
            // pueda quedar una transacción abierta por un return olvidado.
            return DB::transaction(function () use ($beneficiario, $rubro, $gestion, $rutas, $pagosIniciales, $observaciones, $asociacion, $capacidadKg): Tramite {

                /*
                 * EL CANDADO QUE SOSTIENE LA REGLA A.
                 *
                 * Sin él, dos ventanillas atendiendo al mismo pescador en el
                 * mismo segundo leerían las dos «no tiene carnet» y las dos
                 * intentarían crearlo. El índice único rechazaría el segundo
                 * INSERT —y eso ya sería suficiente para no corromper los
                 * datos—, pero el operador vería un error de base de datos
                 * incomprensible en vez de un trámite registrado.
                 *
                 * Bloqueando la fila del beneficiario, la segunda petición
                 * espera, y cuando entra ya ve el carnet que creó la primera: en
                 * vez de fallar, registra correctamente una ACTUALIZACIÓN, que
                 * es lo que corresponde.
                 *
                 * Se bloquea al beneficiario y no al carnet porque el caso a
                 * proteger es justamente cuando el carnet todavía no existe: no
                 * se puede bloquear una fila que no está.
                 */
                $beneficiario = Beneficiario::query()
                    ->whereKey($beneficiario->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * --- REGLA A ------------------------------------------------
                 *
                 * La pregunta lleva el RUBRO además de la gestión. Sin él la
                 * consulta devolvería «el primer carnet del año» y el sistema
                 * colgaría un trámite de Comercializador del carnet de Pescador
                 * —sin violar ningún índice, y por lo tanto sin que nada
                 * avisara—.
                 */
                $carnet = $beneficiario->carnetDeRubroEnGestion($rubro->id, $gestion);

                if ($carnet === null) {
                    /*
                     * EL CARNET NACE VACÍO: sin cupo y sin asociación.
                     *
                     * Los dos datos ya viajan en el trámite, y el carnet los
                     * recibe recién al APROBAR —ver consolidarCarnet()—. Es la
                     * misma idea que hace que el carnet no se pueda imprimir
                     * hasta que alguien firme: mientras el expediente está
                     * pendiente, el documento existe pero no autoriza nada, y
                     * un cupo escrito ahí sería una autorización sin firma y
                     * sin cobrar.
                     */
                    $carnet = $this->crearCarnet($beneficiario, $rubro, $gestion);
                    $tipo = TipoTramite::EmisionInicial;
                } else {
                    $this->verificarCarnetAdmiteTramites($carnet);
                    $tipo = TipoTramite::Actualizacion;
                }

                // Vale para los dos casos: en una emisión inicial el carnet
                // acaba de nacer y no puede tener trámites, así que la consulta
                // devuelve vacío sin costo.
                $this->verificarSinSolicitudEnCurso($carnet);

                // --- REGLA B -------------------------------------------------
                $tramite = $carnet->tramites()->create([
                    // El MISMO rubro del carnet, siempre. Son dos columnas que
                    // tienen que coincidir y la base no lo impide; este servicio
                    // es el único que escribe las dos, y por eso lo toma del
                    // carnet en vez de del parámetro. Ver la migración de
                    // `tramites`.
                    'rubro_id' => $carnet->rubro_id,
                    'tipo_tramite' => $tipo,
                    'estado' => EstadoTramite::Pendiente,
                    'ciFile' => $rutas['ciFile'],
                    'certAsociacionFile' => $rutas['certAsociacionFile'],

                    // La asociación viaja con el certificado que la respalda: el
                    // papel dice «fulano es socio de tal asociación», y los dos
                    // datos tienen que quedar juntos en el mismo expediente.
                    'asociacion' => $asociacion,

                    // El cupo en kilos. NO se imprime en el carnet —el plástico
                    // no lo lleva— pero se guarda porque es con lo que después
                    // se contrasta una guía de transporte. Queda en el trámite
                    // por el mismo motivo que la asociación: es lo que se
                    // declaró el día que se presentó el papel.
                    'capacidad_kg' => $capacidadKg,

                    // El costo se COPIA del rubro, no se consulta en vivo al
                    // aprobar. Si mañana sube la tarifa por ordenanza, este
                    // expediente sigue debiendo lo que decía el papel el día que
                    // se presentó. Ver la migración de `tramites`.
                    'monto_requerido' => $rubro->costo,

                    'fecha_solicitud' => now(),
                    'observaciones' => $observaciones,
                ]);

                // --- REGLA C -------------------------------------------------
                if ($pagosIniciales !== []) {
                    // Adentro de la misma transacción: si una boleta falla, el
                    // trámite tampoco queda. Un expediente con la mitad del
                    // dinero cargado y el operador convencido de que guardó las
                    // dos es peor que un error limpio.
                    //
                    // Se pasan las RUTAS, no los archivos: ya se subieron en el
                    // paso 2, y así el catch de afuera puede borrarlas todas.
                    $this->pagos->registrarVariosConRutas($tramite, $pagosIniciales);
                }

                return $tramite;
            });
        } catch (Throwable $e) {
            // Paso 4. Las filas ya las deshizo el rollback; los archivos hay que
            // borrarlos a mano porque el disco no participa de la transacción.
            $this->archivos->descartar(array_values($rutas));

            throw $this->traducir($e);
        }
    }

    // ==================================================================
    //  CIRCUITO DEL EXPEDIENTE
    // ==================================================================

    /**
     * ENVÍA el expediente a revisión: PENDIENTE ──▶ EN REVISIÓN.
     *
     * ------------------------------------------------------------------------
     *  QUÉ RESUELVE ESTE PASO
     * ------------------------------------------------------------------------
     *
     * Es el paso OBLIGATORIO del circuito: sin pasar por acá no se puede
     * aprobar. Ver EstadoTramite::siguientes() sobre por qué se sacó el atajo
     * que permitía aprobar directo desde PENDIENTE.
     *
     * Lo que significa es un cambio de manos. Mientras está PENDIENTE el
     * expediente se está armando —se cargan papeles, se registran depósitos, se
     * corrige lo que salió ilegible—. Al enviarlo, ventanilla declara que está
     * completo y pasa a quien tiene que verificarlo y firmarlo.
     *
     * La fecha que escribe no es decorativa: junto con `fecha_solicitud`
     * responde cuánto tarda un expediente en armarse, que es la pregunta que
     * hace la unidad todos los meses.
     *
     * QUIÉN lo envió no se guarda en una columna: queda en `auditorias`, con el
     * usuario, la IP y el valor anterior del estado. Ver App\Traits\Auditable.
     *
     * ------------------------------------------------------------------------
     *  ACÁ QUEDA HABILITADO EL RECIBO OFICIAL
     * ------------------------------------------------------------------------
     *
     * Pero no se escribe ninguna fila. La tabla `recibos` se retiró: el
     * comprobante se ARMA al vuelo con los datos del expediente cada vez que
     * alguien lo imprime. Ver App\Services\ReciboTramiteService.
     *
     * Lo que este paso hace es marcar `fecha_revision`, y esa fecha ES la del
     * recibo: el momento en que el pescador entregó los papeles y la plata y se
     * fue con su comprobante. Por eso una reimpresión de marzo sigue diciendo
     * marzo aunque el papel ya no esté guardado en ningún lado.
     *
     * @throws SolicitudInvalidaException
     */
    public function enviarARevision(Tramite $tramite): Tramite
    {
        $this->verificarTransicion($tramite, EstadoTramite::EnRevision);
        $this->verificarExpedienteCompleto($tramite);

        return DB::transaction(function () use ($tramite): Tramite {
            /*
             * Se relee con la fila bloqueada y se vuelve a comprobar.
             *
             * Es la misma precaución que en aprobar(): entre la comprobación de
             * arriba y esta escritura, otra persona con la ficha abierta pudo
             * haber resuelto el expediente. Sin la segunda lectura, un trámite
             * ya aprobado volvería a «en revisión» y perdería su aprobación.
             */
            $bloqueado = $this->bloquear($tramite);

            $this->verificarTransicion($bloqueado, EstadoTramite::EnRevision);

            // Se vuelve a comprobar con la fila bloqueada, igual que el estado:
            // entre la comprobación de afuera y esta, otro operador pudo haber
            // reemplazado un adjunto por uno vacío desde «Editar trámite».
            $bloqueado->loadMissing('carnet.beneficiario');
            $this->verificarExpedienteCompleto($bloqueado);

            $bloqueado->update([
                'estado' => EstadoTramite::EnRevision,
                /*
                 * LA FECHA DE REVISIÓN SE ESCRIBE UNA SOLA VEZ.
                 *
                 * Es la fecha del RECIBO OFICIAL —el comprobante se arma al
                 * vuelo y la toma de acá, ver App\Support\ReciboArmado— y ese
                 * papel ya está en manos del pescador desde el primer envío.
                 *
                 * Importa desde que un expediente rechazado se puede REABRIR y
                 * volver a enviar: pisarla dejaría el recibo diciendo una fecha
                 * distinta de la impresa, con el mismo número, y Contabilidad
                 * con dos versiones del mismo comprobante. El segundo envío no
                 * emite un recibo nuevo porque no hay una segunda cobranza: es
                 * el mismo dinero del mismo trámite.
                 */
                'fecha_revision' => $bloqueado->fecha_revision ?? now(),
            ]);

            // Se refresca el modelo que trajo QUIEN LLAMÓ, no el bloqueado. Ver
            // el comentario de bloquear() sobre por qué esa distinción importa.
            return $tramite->refresh();
        });
    }

    /**
     * ========================================================================
     *  APRUEBA EL TRÁMITE Y CONSOLIDA LO AUTORIZADO EN EL CARNET
     * ========================================================================
     *
     * Antes de acá el pescador presentó papeles; a partir de acá está
     * autorizado. Es el único momento en que el carnet pasa a habilitar.
     *
     * ------------------------------------------------------------------------
     *  QUÉ SIGNIFICA «CONSOLIDAR», Y POR QUÉ REEMPLAZÓ AL INSERT DEL PIVOTE
     * ------------------------------------------------------------------------
     *
     * Con el modelo viejo este método insertaba una fila en `carnet_rubro` con
     * el cupo y el estado «habilitado». Esa tabla ya no existe, porque el carnet
     * ES la habilitación. Lo que queda es COPIAR al carnet lo que el expediente
     * autorizó —el cupo en kilos y la asociación— y dejarlo vigente.
     *
     * ES EL ÚNICO LUGAR QUE ESCRIBE ESAS DOS COLUMNAS, y esa es la regla:
     *
     *   - en una EMISIÓN INICIAL el carnet nació con las dos en NULL, así que
     *     acá es donde se llenan por primera vez. Antes de este momento el
     *     documento existe pero no autoriza ningún cupo, que es exactamente lo
     *     que es un expediente sin aprobar;
     *
     *   - en una ACTUALIZACIÓN el carnet ya traía valores de su aprobación
     *     anterior, y acá se reemplazan por los nuevos. Hasta este momento sigue
     *     rigiendo el cupo viejo — que es lo correcto: es lo último que la
     *     unidad autorizó.
     *
     * Por eso `tramites.capacidad_kg` NO es una copia redundante de
     * `carnets.capacidad_kg`: una guarda lo PEDIDO y la otra lo AUTORIZADO, y
     * entre el registro y la aprobación valen cosas distintas.
     *
     * ------------------------------------------------------------------------
     *  SE PUEDE APROBAR DESDE «PENDIENTE» Y DESDE «EN REVISIÓN»
     * ------------------------------------------------------------------------
     *
     * Pasar por revisión es lo correcto y es lo que la pantalla ofrece primero,
     * pero obligar a dar dos clics cuando el expediente llega perfecto y pagado
     * solo agrega un paso que nadie entiende. Qué salto vale lo decide
     * EstadoTramite::siguientes(), no este método.
     *
     * NO SE APRUEBA SIN COBRAR. Es la condición de la Regla C, y se comprueba
     * consultando la suma de los pagos en el momento —no un campo guardado que
     * podría estar desfasado—.
     *
     * @throws SolicitudInvalidaException
     */
    public function aprobar(Tramite $tramite): Tramite
    {
        $this->verificarTransicion($tramite, EstadoTramite::Aprobado);

        $saldo = $this->pagos->saldoPendiente($tramite);

        if ($saldo > 0) {
            throw SolicitudInvalidaException::tramiteImpago(
                $saldo,
                (string) Configuracion::obtener('general.simbolo_moneda', 'Bs'),
            );
        }

        /*
         * QUE ESTÉ PAGADO NO ALCANZA: TIENE QUE ESTAR CONTROLADO.
         *
         * La suma de arriba sale de filas que tipeó una persona en ventanilla.
         * Hasta que alguien MÁS abra la boleta escaneada y la compare contra el
         * extracto del banco, lo que hay es una declaración, no un cobro.
         *
         * Va DESPUÉS del saldo a propósito: si falta plata, ese es el problema
         * real y el mensaje «valide los depósitos» solo confundiría.
         */
        if (! $tramite->pagosValidados()) {
            $porControlar = $tramite->pagosPorControlar();

            throw SolicitudInvalidaException::pagosSinValidar(
                $porControlar['pendientes'],
                $porControlar['observados'],
            );
        }

        try {
            return DB::transaction(function () use ($tramite): Tramite {
                /*
                 * Se relee el trámite con la fila bloqueada antes de decidir.
                 *
                 * El estado se comprobó arriba, FUERA de la transacción: entre
                 * aquella lectura y esta, otro supervisor con la misma ficha
                 * abierta puede haberlo aprobado. Sin esta segunda lectura, los
                 * dos consolidarían el carnet y el segundo pisaría al primero
                 * sin que nada lo avisara.
                 */
                $bloqueado = $this->bloquear($tramite);

                $this->verificarTransicion($bloqueado, EstadoTramite::Aprobado);

                // Lo que el expediente autorizó pasa al carnet. Reemplaza al
                // INSERT en `carnet_rubro` que hacía este mismo método antes.
                $this->consolidarCarnet($bloqueado);

                $bloqueado->update([
                    'estado' => EstadoTramite::Aprobado,
                    'fecha_aprobacion' => now(),
                ]);

                return $tramite->refresh();
            });
        } catch (Throwable $e) {
            throw $this->traducir($e, $tramite);
        }
    }

    /**
     * Rechaza el trámite con el motivo escrito.
     *
     * El motivo es obligatorio y no es burocracia: el pescador vuelve a
     * ventanilla a preguntar por qué, y sin el texto guardado nadie puede
     * responderle. Además es lo que le permite presentar de nuevo con los
     * papeles corregidos.
     *
     * @throws SolicitudInvalidaException
     */
    public function rechazar(Tramite $tramite, string $motivo): Tramite
    {
        // El motivo se comprueba ANTES que el estado, a propósito: si faltan las
        // dos cosas, el mensaje útil es «escriba el motivo» y no «el trámite ya
        // está resuelto», que no le dice al operador qué tiene que corregir.
        if (blank($motivo)) {
            throw SolicitudInvalidaException::motivoRechazoObligatorio();
        }

        $this->verificarTransicion($tramite, EstadoTramite::Rechazado);

        return DB::transaction(function () use ($tramite, $motivo): Tramite {
            $bloqueado = $this->bloquear($tramite);

            $this->verificarTransicion($bloqueado, EstadoTramite::Rechazado);

            $bloqueado->update([
                'estado' => EstadoTramite::Rechazado,
                'motivo_rechazo' => trim($motivo),
            ]);

            /*
             * EL CARNET RECIÉN CREADO NO SE BORRA.
             *
             * Cuando se rechaza una EMISIÓN INICIAL, el carnet ya existe en la
             * base y queda sin ningún rubro habilitado. Podría parecer basura,
             * pero borrarlo sería peor: su número de registro es el `id` de la
             * fila —ver Carnet::registro()— y la secuencia no lo devuelve al
             * borrar. Un registro que desaparece deja un hueco en la serie que
             * nadie puede explicar después.
             *
             * Queda vigente y sin rubros, que es exactamente lo que es: un
             * documento emitido que todavía no autoriza ninguna actividad. Si el
             * pescador vuelve con los papeles corregidos, se REABRE este mismo
             * expediente —ver reabrir()— y el carnet sigue siendo el suyo.
             */

            return $tramite->refresh();
        });
    }

    /**
     * ========================================================================
     *  REABRE UN EXPEDIENTE RECHAZADO PARA SEGUIR TRABAJÁNDOLO
     * ========================================================================
     *
     *     RECHAZADO ──[reabrir]──▶ PENDIENTE ──[enviar]──▶ EN REVISIÓN
     *
     * El pescador volvió al mostrador con lo que le faltaba. Esto devuelve el
     * expediente al BORRADOR, y desde ahí valen todas las reglas de armarlo:
     * se cambian los papeles, se agregan, corrigen y quitan depósitos, y se
     * vuelve a enviar.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ NO ALCANZABA CON PRESENTAR UN EXPEDIENTE NUEVO
     * ------------------------------------------------------------------------
     *
     * Era la salida anterior, y el problema es la PLATA: los depósitos cuelgan
     * del trámite (`pagable_id`) y no se trasladan solos. Un expediente nuevo
     * nacía con cero cobrado mientras el rechazado se quedaba con el dinero
     * cargado, así que el beneficiario figuraba debiendo todo de nuevo. Además
     * gastaba un número de trámite por cada vuelta.
     *
     * NO ES «DES-RECHAZAR». El rechazo ya ocurrió y queda en `auditorias` con su
     * motivo, su autor y su fecha. Lo que se reabre es el trabajo, no la
     * decisión — por eso `motivo_rechazo` se limpia de la fila: describe un
     * estado en el que el expediente ya no está, y dejarlo mostraría «Rechazado:
     * X» sobre algo que volvió a estar abierto.
     *
     * NO SE TOCA `fecha_revision`, y eso es lo que protege el recibo ya
     * entregado: ver el comentario de enviarARevision().
     *
     * @throws SolicitudInvalidaException
     */
    public function reabrir(Tramite $tramite): Tramite
    {
        $this->verificarTransicion($tramite, EstadoTramite::Pendiente);

        return DB::transaction(function () use ($tramite): Tramite {
            $bloqueado = $this->bloquear($tramite);

            $this->verificarTransicion($bloqueado, EstadoTramite::Pendiente);

            /*
             * QUE NO HAYA OTRO EXPEDIENTE ABIERTO PARA ESTE CARNET.
             *
             * Mientras estaba rechazado, este carnet quedó libre para recibir
             * otra solicitud —un rechazado no cuenta como «en curso»— así que
             * bien puede haberse presentado una. Reabrir sin mirar dejaría DOS
             * expedientes abiertos por la misma habilitación, y el beneficiario
             * pagando dos veces por ella.
             *
             * Es la misma comprobación que hace el alta, con el mismo mensaje:
             * la pregunta es idéntica y la respuesta también.
             */
            $this->verificarSinSolicitudEnCurso($bloqueado->carnet);

            $bloqueado->update([
                'estado' => EstadoTramite::Pendiente,
                'motivo_rechazo' => null,
            ]);

            // Se refresca el modelo de quien llamó, no el bloqueado. Ver
            // bloquear() sobre por qué esa distinción importa.
            return $tramite->refresh();
        });
    }

    // ==================================================================
    //  BORRADO DEL EXPEDIENTE
    // ==================================================================

    /**
     * ========================================================================
     *  BORRA UN EXPEDIENTE QUE NUNCA DEBIÓ EXISTIR
     * ========================================================================
     *
     * Cargado dos veces, con la persona equivocada, con el rubro equivocado.
     * Solo se puede mientras NADIE lo resolvió —pendiente o en revisión—; qué
     * estados son esos lo dice EstadoTramite::permiteEliminacion() y no este
     * método, para que la pantalla y el servidor no puedan discrepar.
     *
     * ------------------------------------------------------------------------
     *  ESTO BORRA DE VERDAD, Y NO SE PUEDE DESHACER
     * ------------------------------------------------------------------------
     *
     * No hay borrado lógico acá, a diferencia de `beneficiarios`. La diferencia
     * es de intención: dar de baja a una persona significa «ya no opera, pero
     * existió», y su historial tiene que seguir legible. Borrar un trámite mal
     * cargado significa «esto nunca pasó». Un `deleted_at` dejaría filas que
     * ensucian los contadores del tablero y la comprobación de solicitud
     * duplicada, y habría que acordarse de excluirlas en cada consulta.
     *
     * El rastro no se pierde: el trait Auditable escribe una fila «eliminado»
     * en `auditorias` con quién lo hizo y cuándo.
     *
     * ------------------------------------------------------------------------
     *  EL ORDEN DE LOS PASOS, OTRA VEZ POR LOS ARCHIVOS
     * ------------------------------------------------------------------------
     *
     *   1. Juntar las rutas de todos los adjuntos     ← ANTES de borrar filas
     *   2. Transacción: pagos, trámite, carnet huérfano
     *   3. Recién con la transacción confirmada, borrar los archivos
     *
     * Es el orden INVERSO al de registrar(), y por el mismo motivo: una
     * transacción no deshace lo que se hizo en el disco. Al registrar se sube
     * primero, porque lo peor que puede pasar es un archivo de más. Al borrar
     * se borra al final, porque lo peor que puede pasar acá es un archivo de
     * menos —y un adjunto borrado cuyo trámite sigue vivo es un enlace roto en
     * la ficha, sin forma de recuperarlo—.
     *
     * @throws SolicitudInvalidaException
     */
    public function eliminar(Tramite $tramite, string $motivo): void
    {
        /*
         * EL MOTIVO ES OBLIGATORIO, Y SE COMPRUEBA ANTES QUE EL ESTADO.
         *
         * A propósito, igual que en rechazar(): si faltan las dos cosas, el
         * mensaje útil es «escriba el motivo» y no «este trámite ya está
         * resuelto», que no le dice al operador qué tiene que corregir.
         *
         * Hace falta porque borrar es la ÚNICA operación del sistema que no deja
         * la fila. El expediente, sus pagos y sus archivos desaparecen; lo único
         * que queda es la línea de `auditorias`. Si ahí no está el porqué, no
         * queda nada que explique por qué ese trámite ya no está.
         */
        if (blank($motivo)) {
            throw SolicitudInvalidaException::motivoEliminacionObligatorio();
        }

        if (! $tramite->estado->permiteEliminacion()) {
            throw SolicitudInvalidaException::tramiteNoSePuedeEliminar($tramite->estado);
        }

        try {
            /*
             * Paso 1. Las rutas, ANTES de que las filas desaparezcan.
             *
             * Después del delete no hay de dónde leerlas: la fila de `pagos` se
             * fue y con ella la columna que dice dónde está la boleta.
             */
            $rutas = $this->rutasDeAdjuntos($tramite);

            // Paso 2. Las filas.
            DB::transaction(function () use ($tramite, $motivo): void {
                $bloqueado = $this->bloquear($tramite);

                /*
                 * Se vuelve a preguntar con la fila bloqueada.
                 *
                 * Entre la comprobación de arriba y esta, otro supervisor con la
                 * misma ficha abierta puede haberlo aprobado. Sin esta segunda
                 * lectura se borraría un expediente aprobado —con su cupo ya
                 * consolidado en el carnet— y la persona quedaría habilitada sin
                 * ningún papel que lo respalde.
                 */
                if (! $bloqueado->estado->permiteEliminacion()) {
                    throw SolicitudInvalidaException::tramiteNoSePuedeEliminar($bloqueado->estado);
                }

                $carnet = $bloqueado->carnet;

                /*
                 * Los pagos se borran UNO POR UNO con Eloquent, aunque la clave
                 * foránea ya tiene cascadeOnDelete y la base los barrería sola.
                 *
                 * El motivo es la auditoría: un DELETE en cascada lo hace el
                 * motor, no Eloquent, así que no dispara el evento `deleted` y
                 * el trait Auditable no registra nada. Los depósitos son dinero
                 * declarado —con número de transacción y boleta— y no pueden
                 * desaparecer sin dejar quién los borró.
                 */
                foreach ($bloqueado->pagos as $pago) {
                    $pago->delete();
                }

                /*
                 * El motivo viaja CON el evento de borrado, no en una fila
                 * aparte. Después del delete la fila del trámite ya no existe:
                 * si el porqué no se cuelga de este mismo evento, no hay dónde
                 * ponerlo. Ver App\Traits\Auditable::$motivoAuditoria.
                 */
                $bloqueado->motivoAuditoria = trim($motivo);

                $bloqueado->delete();

                $this->borrarCarnetSiQuedoVacio($carnet);
            });

            /*
             * Paso 3. Los archivos, con las filas ya confirmadas.
             *
             * descartar() no propaga errores: si un archivo no se deja borrar,
             * queda anotado en el log. El trámite ya no existe, y volver atrás
             * por un archivo huérfano sería peor que el huérfano.
             */
            $this->archivos->descartar($rutas);
        } catch (Throwable $e) {
            throw $this->traducir($e, $tramite);
        }
    }

    /**
     * Las rutas de TODO lo que este expediente tiene escrito en el disco.
     *
     * Los dos adjuntos propios y la boleta de cada pago. Se arma antes de
     * borrar nada; ver el paso 1 de eliminar().
     *
     * @return array<int, string|null>
     */
    private function rutasDeAdjuntos(Tramite $tramite): array
    {
        return [
            $tramite->ciFile,
            $tramite->certAsociacionFile,
            /*
             * `urlFile` Y NO `comprobante`: esa columna no existe.
             *
             * `pluck()` sobre un nombre equivocado NO FALLA —devuelve una lista
             * de nulls— así que esto se veía correcto y no borraba una sola
             * boleta: cada expediente eliminado dejaba todas sus boletas
             * tiradas en el disco, para siempre y sin ningún error. Lo que
             * confunde es que el modelo SÍ tiene `comprobante_url`, que es el
             * accesor con la dirección completa, no la columna.
             */
            ...$tramite->pagos->pluck('urlFile')->all(),
        ];
    }

    /**
     * ========================================================================
     *  EL CARNET SE VA CON EL ÚLTIMO TRÁMITE QUE LO SOSTENÍA
     * ========================================================================
     *
     * Borrar una EMISIÓN INICIAL deja atrás el carnet que ese mismo trámite
     * creó. Y un carnet sin trámites no es solo basura: OCUPA EL LUGAR de esa
     * persona en ese rubro y esa gestión. El índice único (beneficiario, rubro,
     * gestión) impediría que vuelva a presentar la solicitud ese año, y el
     * operador vería un error de «ya tiene carnet de Pescador» señalando un
     * carnet que nadie pidió.
     *
     * Por eso se borra, pero solo si quedó realmente vacío: sin otros trámites.
     * Una actualización borrada deja el carnet en pie, lo sostiene la emisión
     * inicial que sigue ahí.
     *
     * ------------------------------------------------------------------------
     *  YA NO SE CUENTAN LAS HABILITACIONES, Y NO HACE FALTA
     * ------------------------------------------------------------------------
     *
     * La versión anterior comprobaba además que el carnet no tuviera filas en
     * `carnet_rubro`, como red por si alguna vez llegaba acá un carnet con un
     * rubro habilitado. Esa tabla ya no existe, y la red la cubre el mismo
     * conteo de trámites: un carnet solo habilita cuando alguno de sus trámites
     * está APROBADO, y un trámite aprobado no puede llegar hasta acá —
     * `permiteEliminacion()` lo rechaza—. Sin trámites no hay aprobación
     * posible, así que no hay nada que perder.
     *
     * ES LA EXCEPCIÓN A «EL CARNET NO SE BORRA», no una contradicción. Al
     * RECHAZAR el carnet se conserva porque el expediente existió y se resolvió
     * que no; acá el expediente se borra porque nunca debió existir, y el carnet
     * que colgaba de él tampoco.
     *
     * El hueco en la secuencia de ids es el precio, y es aceptable: ese carnet
     * nunca se imprimió —no tenía ningún trámite aprobado— así que no hay número
     * circulando en la calle que quede sin respaldo.
     */
    private function borrarCarnetSiQuedoVacio(?Carnet $carnet): void
    {
        if ($carnet === null) {
            return;
        }

        /*
         * Se cuenta contra la BASE y no contra la relación ya cargada.
         *
         * `$carnet->tramites` en memoria todavía incluye el que se acaba de
         * borrar —la colección no se entera—, así que la comprobación diría
         * «tiene trámites» siempre y el carnet no se limpiaría nunca.
         */
        if (! $carnet->tramites()->exists()) {
            $carnet->delete();
        }
    }

    /**
     * Marca el carnet como impreso.
     *
     * Es un hecho con fecha, no un estado: ver el comentario de
     * App\Enums\EstadoTramite sobre por qué son tres estados y no cinco.
     *
     * @throws SolicitudInvalidaException
     */
    public function generar(Tramite $tramite): Tramite
    {
        if (! $tramite->puedeGenerarse()) {
            throw new SolicitudInvalidaException(
                'El carnet solo se imprime con el trámite aprobado, y una sola vez.',
            );
        }

        $tramite->update(['fecha_generacion' => now()]);

        return $tramite->refresh();
    }

    /**
     * Marca el carnet como entregado en mano.
     *
     * @throws SolicitudInvalidaException
     */
    public function entregar(Tramite $tramite): Tramite
    {
        if (! $tramite->puedeEntregarse()) {
            throw new SolicitudInvalidaException(
                'Para entregar el carnet primero hay que imprimirlo.',
            );
        }

        $tramite->update(['fecha_entrega' => now()]);

        return $tramite->refresh();
    }

    /**
     * ========================================================================
     *  EDITA UN EXPEDIENTE TODAVÍA ABIERTO
     * ========================================================================
     *
     * Los dos adjuntos, la asociación, el cupo en kilos y las observaciones.
     *
     * ------------------------------------------------------------------------
     *  LO QUE NO SE PUEDE CAMBIAR, Y POR QUÉ
     * ------------------------------------------------------------------------
     *
     * EL RUBRO Y EL BENEFICIARIO. Cambiarlos no sería corregir este expediente
     * sino convertirlo en otro:
     *
     *   - el rubro define el COSTO, que ya se copió a `monto_requerido` y
     *     posiblemente ya se cobró. Cambiarlo dejaría pagos aplicados a una
     *     tarifa que no corresponde;
     *   - el beneficiario define el CARNET del que cuelga el trámite, y con él
     *     la gestión. Moverlo de carnet podría violar la Regla A sin que nada
     *     lo impida.
     *
     * Si se pidió el rubro equivocado, lo que corresponde es rechazar —queda el
     * motivo escrito— o eliminar el expediente y presentar uno nuevo.
     *
     * ------------------------------------------------------------------------
     *  LOS ARCHIVOS VIEJOS SE BORRAN, PERO DESPUÉS DEL COMMIT
     * ------------------------------------------------------------------------
     *
     * Para no dejar huérfanos ocupando disco cada vez que alguien vuelve a
     * escanear un papel ilegible. Al revés —borrar antes— un fallo del update
     * dejaría el expediente apuntando a un archivo que ya no existe.
     *
     * @param  array{ciFile?: UploadedFile|null, certAsociacionFile?: UploadedFile|null}  $adjuntos
     * @param  array{asociacion?: string|null, capacidad_kg?: float|string|null, observaciones?: string|null}  $datos
     *
     * @throws SolicitudInvalidaException
     */
    public function actualizar(Tramite $tramite, array $adjuntos = [], array $datos = []): Tramite
    {
        if (! $tramite->estado->permiteEdicion()) {
            throw SolicitudInvalidaException::tramiteNoSePuedeEditar($tramite->estado);
        }

        $nuevos = array_filter($adjuntos, fn ($a): bool => $a instanceof UploadedFile);

        $rutas = $nuevos === []
            ? []
            : $this->archivos->guardarVarios($nuevos, self::CARPETA_ADJUNTOS);

        // Las rutas viejas se guardan ANTES de escribir las nuevas: una vez
        // hecho el update, el modelo ya no sabe qué archivo tenía.
        $anteriores = array_map(fn (string $columna): ?string => $tramite->{$columna}, array_keys($rutas));

        /*
         * Solo las columnas que llegaron. `array_intersect_key` es la lista
         * blanca: aunque quien llame mande `rubro_id` o `estado`, no se
         * escriben. `#[Fillable]` ya lo impediría, pero eso falla EN SILENCIO
         * —ver la trampa anotada en CLAUDE.md— y acá queda explícito qué se
         * puede tocar.
         */
        $editables = array_intersect_key($datos, array_flip([
            'asociacion',
            'capacidad_kg',
            'observaciones',
        ]));

        try {
            DB::transaction(function () use ($tramite, $rutas, $editables): void {
                $tramite->update([...$rutas, ...$editables]);
            });
        } catch (Throwable $e) {
            $this->archivos->descartar(array_values($rutas));

            throw $e;
        }

        // Recién con el commit hecho se borran los archivos viejos. Al revés,
        // un fallo del update dejaría el expediente apuntando a un archivo que
        // ya no existe.
        $this->archivos->descartar($anteriores);

        return $tramite->refresh();
    }

    // ==================================================================
    //  Auxiliares
    // ==================================================================

    /**
     * Sube la boleta de cada pago y la reemplaza por su ruta.
     *
     * Las rutas se van agregando a $rutas —por referencia— para que el catch de
     * registrar() las tenga todas juntas y pueda borrarlas si la transacción se
     * deshace. Es justamente lo que faltaba cuando las boletas se subían desde
     * dentro de PagoTramiteService: quedaban fuera de esa lista y sobrevivían al
     * rollback.
     *
     * @param  array<int, array<string, mixed>>  $pagos
     * @param  array<string, string|null>  $rutas
     * @return array<int, array<string, mixed>>
     */
    private function subirComprobantes(array $pagos, array &$rutas): array
    {
        foreach ($pagos as $i => $pago) {
            $comprobante = $pago['comprobante'] ?? null;

            if (! $comprobante instanceof UploadedFile) {
                continue;
            }

            $ruta = $this->archivos->guardar($comprobante, PagoTramiteService::CARPETA_COMPROBANTES);

            $rutas["pago_$i"] = $ruta;

            unset($pagos[$i]['comprobante']);
            $pagos[$i]['ruta'] = $ruta;
        }

        return $pagos;
    }

    /**
     * Crea el carnet de esta persona para ESTE rubro y esta gestión.
     *
     * No se escribe ningún número: el de registro que se imprime es el `id`
     * que asigna la base. Ver Carnet::registro().
     *
     * Se llama solo desde dentro de la transacción de registrar(), y solo
     * cuando la Regla A determinó que la persona no tiene carnet de este rubro
     * este año.
     *
     * ------------------------------------------------------------------------
     *  NACE SIN CUPO Y SIN ASOCIACIÓN, Y ESO ES LO IMPORTANTE
     * ------------------------------------------------------------------------
     *
     * Las dos columnas quedan en NULL hasta que alguien APRUEBE el trámite;
     * las escribe `consolidarCarnet()` y nadie más.
     *
     * Escribirlas acá parece inofensivo —el dato ya está en el trámite, total
     * es copiarlo— pero cambia lo que el carnet SIGNIFICA. `carnets.capacidad_kg`
     * responde «cuánto tiene autorizado HOY esta persona en esta actividad», y
     * un expediente pendiente no autorizó nada: no se pagó y nadie lo firmó. Con
     * el valor escrito desde el alta, una actualización que pide subir de 600 a
     * 850 kg dejaría al carnet diciendo 850 antes de cobrar — y la ficha del
     * panel lo mostraría como «cupo autorizado».
     *
     * Es la misma línea que ya separa `Carnet::puedeImprimirse()`: el carnet
     * existe desde PENDIENTE, pero no habilita —ni autoriza un cupo, ni se
     * imprime— hasta que hay un trámite aprobado.
     *
     * La vista previa del formulario no se resiente: muestra lo que el operador
     * está tecleando, y solo cae al valor del carnet cuando el campo está vacío
     * sobre un carnet que ya existe.
     */
    private function crearCarnet(
        Beneficiario $beneficiario,
        Rubro $rubro,
        int $gestion,
    ): Carnet {
        return $beneficiario->carnets()->create([
            // La actividad que habilita este carnet. Es la columna que define el
            // modelo: junto con el beneficiario y la gestión forma el índice
            // único que impide duplicados.
            'rubro_id' => $rubro->id,

            /*
             * La firma es lo ÚNICO que identifica al carnet: no hay columna
             * `codigo`. Se genera al azar y se comprueba contra la base antes de
             * usarla; el índice único de la columna es la garantía final. Ver
             * Carnet::nuevaFirma().
             *
             * Se genera UNA VEZ, al crear el carnet, y no se vuelve a tocar: si
             * cambiara, el QR ya impreso dejaría de funcionar y habría que
             * reimprimir el plástico. Dos carnets de la misma persona en la
             * misma gestión tienen firmas DISTINTAS, y es correcto: son dos
             * documentos.
             */
            'firma_validacion' => Carnet::nuevaFirma(),
            'gestion' => $gestion,
            'fecha_emision' => now()->toDateString(),
            // Todos los carnets de una gestión vencen el 31 de diciembre, no a
            // los 365 días de emitidos. Ver Carnet::vencimientoDeGestion().
            'fecha_vencimiento' => Carnet::vencimientoDeGestion($gestion)->toDateString(),
            'estado' => EstadoCarnet::Vigente,
        ]);
    }

    /**
     * ========================================================================
     *  PASA AL CARNET LO QUE EL EXPEDIENTE AUTORIZÓ
     * ========================================================================
     *
     * Se llama al aprobar, dentro de la transacción y con el trámite ya
     * bloqueado. Es lo que reemplazó al INSERT en `carnet_rubro`: el carnet ES
     * la habilitación, así que consolidar es escribirle el cupo y la asociación
     * que el trámite trae, y dejarlo vigente.
     *
     * ------------------------------------------------------------------------
     *  LO QUE VIENE EN BLANCO NO PISA LO QUE YA HABÍA
     * ------------------------------------------------------------------------
     *
     * Un trámite de actualización presentado solo para corregir la asociación
     * no tiene por qué traer el cupo, y si lo trae vacío no significa «borralo»
     * sino «no lo estoy tocando». Borrar el cupo autorizado por no repetirlo
     * dejaría al carnet sin el número contra el que se contrastan las guías de
     * transporte, y nadie se enteraría hasta un control en el río.
     *
     * ------------------------------------------------------------------------
     *  EL ESTADO SOLO SE TOCA SI EL CARNET ESTÁ «SANO»
     * ------------------------------------------------------------------------
     *
     * Suspender y anular son decisiones de un supervisor SOBRE EL DOCUMENTO, y
     * no le corresponde deshacerlas a la aprobación de un trámite. No deberían
     * llegar acá —verificarCarnetAdmiteTramites() los rechaza al registrar—
     * pero entre el registro y la aprobación pueden pasar días, y en el medio
     * alguien pudo haber suspendido el carnet.
     *
     * Vencido tampoco se toca: un carnet de la gestión pasada no vuelve a
     * vigente porque se apruebe un expediente atrasado.
     *
     * Se escribe con `update()` sobre la relación y no con `save()` sobre una
     * instancia suelta para que el trait Auditable registre el cambio: quién
     * consolidó qué cupo tiene que quedar en `auditorias`.
     */
    private function consolidarCarnet(Tramite $tramite): void
    {
        $carnet = $tramite->carnet;

        if ($carnet === null) {
            return;
        }

        $datos = [];

        if (filled($tramite->capacidad_kg)) {
            $datos['capacidad_kg'] = $tramite->capacidad_kg;
        }

        if (filled($tramite->asociacion)) {
            $datos['asociacion'] = $tramite->asociacion;
        }

        // Solo desde vigente hacia vigente, o desde nada. Ver el comentario.
        if ($carnet->estado === EstadoCarnet::Vigente) {
            $datos['estado'] = EstadoCarnet::Vigente;
        }

        if ($datos === []) {
            return;
        }

        $carnet->update($datos);
    }

    /**
     * ========================================================================
     *  RELEE EL TRÁMITE CON LA FILA BLOQUEADA
     * ========================================================================
     *
     * Todo cambio de estado hace esto como primer paso dentro de su
     * transacción, y por dos motivos distintos:
     *
     *   1. BLOQUEA LA FILA. Desde acá hasta el commit, ninguna otra conexión
     *      puede tocar ese trámite: espera. Sin eso, dos personas con la misma
     *      ficha abierta pueden aprobar y rechazar el mismo expediente a la vez,
     *      y el resultado depende de cuál escriba último.
     *
     *   2. TRAE EL ESTADO DE VERDAD. El modelo que llegó por parámetro se leyó
     *      antes —a veces segundos antes, cuando el operador abrió la pantalla—
     *      y puede estar desactualizado. La guarda de transición tiene que
     *      correr contra lo que hay en la base AHORA, no contra lo que había.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ DEVUELVE UNA INSTANCIA NUEVA EN VEZ DE PISAR LA QUE LLEGÓ
     * ------------------------------------------------------------------------
     *
     * Porque son dos objetos distintos apuntando a la misma fila, y confundirlos
     * costó un error real: escribir sobre la copia bloqueada dejaba la instancia
     * de quien llamó con el estado viejo en memoria, y cualquier comprobación
     * posterior sobre esa variable respondía como si el cambio no hubiera
     * ocurrido.
     *
     * Por eso la convención de este servicio es fija: se ESCRIBE sobre la copia
     * bloqueada, y se DEVUELVE `$tramite->refresh()` —la instancia original, ya
     * releída—. Así quien llamó se queda con un modelo que dice la verdad, use
     * el valor de retorno o su propia variable.
     */
    private function bloquear(Tramite $tramite): Tramite
    {
        return Tramite::query()
            ->whereKey($tramite->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * ========================================================================
     *  LA GUARDA DE TRANSICIÓN — la usa TODO cambio de estado
     * ========================================================================
     *
     * Es una sola línea de lógica, pero está en un método propio por dos
     * motivos concretos:
     *
     *   1. NO DECIDE NADA POR SU CUENTA. Le pregunta a
     *      EstadoTramite::puedePasarA(), que es la única fuente de verdad del
     *      flujo. Agregar un estado mañana significa tocar el enum y nada más;
     *      si cada método tuviera su propio `if`, habría que cazarlos uno por
     *      uno y el que se olvide queda permitiendo un salto ilícito.
     *
     *   2. SE LLAMA DOS VECES POR OPERACIÓN, y tiene que decir lo mismo las dos:
     *      una antes de abrir la transacción —para fallar barato— y otra
     *      adentro, con la fila bloqueada, porque entre una y otra otra persona
     *      con la misma ficha abierta pudo haber resuelto el expediente.
     *
     * El mensaje distingue el caso más frecuente —el expediente ya está
     * resuelto— del salto raro, porque en ventanilla son dos situaciones
     * distintas: la primera se explica sola, la segunda casi siempre es un botón
     * que quedó a la vista cuando no correspondía.
     *
     * @throws SolicitudInvalidaException
     */
    private function verificarTransicion(Tramite $tramite, EstadoTramite $destino): void
    {
        if ($tramite->estado->puedePasarA($destino)) {
            return;
        }

        if ($tramite->estado->esFinal()) {
            throw SolicitudInvalidaException::tramiteYaResuelto($tramite->estado->etiqueta());
        }

        throw SolicitudInvalidaException::transicionInvalida(
            $tramite->estado->etiqueta(),
            $destino->etiqueta(),
        );
    }

    /**
     * ========================================================================
     *  NO SE REVISA UN EXPEDIENTE AL QUE LE FALTAN PAPELES
     * ========================================================================
     *
     * Tomar para revisión es el momento en que el operador dice «esto está
     * completo». Dejarlo pasar sin los adjuntos corre el problema hasta el final
     * del circuito: el expediente se aprueba, se emite el recibo, y el papel que
     * falta aparece recién cuando alguien va a archivarlo.
     *
     * SON LOS DOS ADJUNTOS DEL EXPEDIENTE. La fotografía del beneficiario NO
     * entra: hace falta para imprimir el carnet, no para revisar los papeles, y
     * bloquear la revisión por algo que se resuelve más adelante frena la
     * ventanilla sin necesidad. Ver `Tramite::faltantesParaRevision()`.
     *
     * Qué falta lo decide `Tramite::faltantesParaRevision()`, que además lo
     * devuelve escrito para poder nombrarlo en el mensaje. La pantalla usa esa
     * misma lista para mostrar el aviso antes de que nadie apriete nada, así que
     * el operador se entera al abrir la ficha y no al recibir un rechazo.
     *
     * @throws SolicitudInvalidaException
     */
    private function verificarExpedienteCompleto(Tramite $tramite): void
    {
        $faltantes = $tramite->faltantesParaRevision();

        if ($faltantes !== []) {
            throw SolicitudInvalidaException::expedienteIncompleto($faltantes);
        }
    }

    /**
     * @throws SolicitudInvalidaException
     */
    private function verificarBeneficiario(Beneficiario $beneficiario): void
    {
        // Una ficha dada de baja se sigue pudiendo leer —el historial no se
        // rompe— pero no puede iniciar trámites nuevos.
        if ($beneficiario->trashed()) {
            throw SolicitudInvalidaException::beneficiarioDadoDeBaja();
        }
    }

    /**
     * @throws SolicitudInvalidaException
     */
    private function verificarRubro(Rubro $rubro): void
    {
        if (! $rubro->estaActivo()) {
            throw SolicitudInvalidaException::rubroInactivo($rubro->nombre);
        }
    }

    /**
     * ¿Se le puede presentar un trámite a este carnet?
     *
     * Anulado, suspendido o vencido, no. Lo del vencimiento casi no pasa dentro
     * de la misma gestión —vence el 31 de diciembre—, pero un trámite cargado el
     * 31 a las 23:50 entraría en ese caso, y es correcto que no se le pueda
     * presentar nada.
     *
     * OJO CON LO QUE ESTO NO RESUELVE: un carnet anulado sigue ocupando su lugar
     * en el índice único (beneficiario, rubro, gestión), así que la persona
     * tampoco puede sacar otro del mismo rubro ese año. Es deliberado —anular es
     * una sanción— pero el mensaje tiene que decirlo, o el operador busca la
     * forma de emitir uno nuevo y no la encuentra.
     *
     * @throws SolicitudInvalidaException
     */
    private function verificarCarnetAdmiteTramites(Carnet $carnet): void
    {
        if (! $carnet->admiteTramites()) {
            throw SolicitudInvalidaException::carnetNoAdmiteTramites(
                $carnet->rubro?->nombre ?? 'solicitado',
                $carnet->gestion,
                $carnet->estado,
            );
        }
    }

    /**
     * Un carnet no puede tener dos expedientes abiertos a la vez.
     *
     * El pescador pagaría dos veces por una sola autorización. Ya no hace falta
     * filtrar por rubro dentro del carnet —todos los trámites de un carnet son
     * del mismo rubro, por construcción— así que la comprobación se simplificó
     * a «¿tiene algo abierto?».
     *
     * Esto NO está como índice en la base y es una decisión, no un olvido: un
     * trámite RECHAZADO sí se puede volver a presentar con los papeles
     * corregidos, y un carnet puede recibir varias actualizaciones a lo largo
     * del año, así que la combinación se repite legítimamente. Expresarlo en SQL
     * exigiría un índice parcial sobre los estados abiertos, y el mensaje que
     * necesita ventanilla no lo puede dar la base de todos modos.
     *
     * @throws SolicitudInvalidaException
     */
    private function verificarSinSolicitudEnCurso(Carnet $carnet): void
    {
        // abiertos() y no pendientes(): un expediente que alguien tomó para
        // revisar sigue en curso, y dejar presentar otro haría que el
        // beneficiario pague dos veces por una sola autorización.
        $enCurso = $carnet->tramites()->abiertos()->exists();

        if ($enCurso) {
            throw SolicitudInvalidaException::solicitudEnCurso(
                $carnet->rubro?->nombre ?? 'solicitado',
            );
        }
    }

    /**
     * Convierte un error de índice único en un mensaje que ventanilla entienda.
     *
     * Las comprobaciones de arriba dan buenos mensajes, pero no resisten dos
     * peticiones simultáneas: entre el SELECT y el INSERT, otra conexión puede
     * haber escrito la misma fila. Quien garantiza de verdad es el índice de la
     * base; lo que llega de él es «duplicate key value violates unique
     * constraint carnets_beneficiario_rubro_gestion_unique», que no le dice nada
     * a nadie.
     *
     * El 23505 es el SQLSTATE estándar de «unique_violation» y lo usan tanto
     * PostgreSQL como SQLite vía PDO, así que la traducción funciona igual en
     * producción y en las pruebas en memoria.
     */
    private function traducir(Throwable $e, ?Tramite $tramite = null): Throwable
    {
        if (! $e instanceof QueryException) {
            return $e;
        }

        $mensaje = strtolower($e->getMessage());
        $esUnico = $e->getCode() === '23505' || str_contains($mensaje, 'unique');

        if (! $esUnico) {
            return $e;
        }

        /*
         * El índice de `carnets` se llama carnets_beneficiario_rubro_gestion_unique
         * y es el único que puede saltar acá por una carrera entre dos
         * ventanillas: las dos leyeron «no tiene carnet de este rubro» y las dos
         * intentaron crearlo.
         *
         * El mensaje dice qué hacer —volver a intentar— porque el segundo
         * intento SÍ va a funcionar: cuando entre, ya va a ver el carnet que
         * creó el primero y va a registrar una actualización, que es lo que
         * corresponde.
         */
        if (str_contains($mensaje, 'beneficiario_rubro_gestion')) {
            return new SolicitudInvalidaException(
                'El beneficiario ya tiene un carnet de este rubro en esta gestión. '.
                'Vuelva a intentarlo: la solicitud se registrará como actualización.',
            );
        }

        // La firma de validación se comprueba antes de usarla —ver
        // Carnet::nuevaFirma()— pero entre ese SELECT y el INSERT otra conexión
        // pudo meter la misma. Es astronómicamente improbable y por eso el
        // mensaje solo pide reintentar: no hay nada que el operador pueda
        // corregir.
        if (str_contains($mensaje, 'firma_validacion')) {
            return new SolicitudInvalidaException(
                'No se pudo generar la firma del carnet. Vuelva a intentarlo.',
            );
        }

        return $e;
    }
}

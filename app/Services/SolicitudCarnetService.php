<?php

namespace App\Services;

use App\Enums\EstadoCarnet;
use App\Enums\EstadoHabilitacion;
use App\Enums\EstadoTramite;
use App\Enums\TipoTramite;
use App\Exceptions\SolicitudInvalidaException;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\CarnetRubro;
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
 *  REGLA A — un carnet por persona y por gestión
 * ----------------------------------------------------------------------------
 *
 *      ¿tiene carnet de la gestión en curso?
 *
 *          NO ──▶ se CREA el carnet          ──▶ tipo = EMISIÓN INICIAL
 *          SÍ ──▶ se REUTILIZA el que tiene  ──▶ tipo = ADICIÓN DE RUBRO
 *
 * El tipo NO lo elige el operador: lo decide esta pregunta. Ver el comentario de
 * App\Enums\TipoTramite sobre por qué.
 *
 * ----------------------------------------------------------------------------
 *  REGLA B — el trámite y sus respaldos
 * ----------------------------------------------------------------------------
 *
 * En los dos casos se escribe una fila en `tramites`, colgada del carnet y del
 * rubro, en estado PENDIENTE y con las rutas de los dos adjuntos obligatorios
 * (fotocopia de CI y certificado de la asociación).
 *
 * LO QUE NO PASA ACÁ: el rubro NO se escribe todavía en `carnet_rubro`. Esa
 * fila nace recién al aprobar. Si se escribiera al solicitar, el pescador
 * quedaría habilitado por el solo hecho de haber presentado papeles.
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
        private readonly ReciboTramiteService $recibos,
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
     * @param  float|string|null  $capacidadKg  Cupo autorizado en kilos. Null si todavía no se definió.
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
                 * vez de fallar, registra correctamente una ADICIÓN DE RUBRO,
                 * que es lo que corresponde.
                 *
                 * Se bloquea al beneficiario y no al carnet porque el caso a
                 * proteger es justamente cuando el carnet todavía no existe: no
                 * se puede bloquear una fila que no está.
                 */
                $beneficiario = Beneficiario::query()
                    ->whereKey($beneficiario->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                // --- REGLA A -------------------------------------------------
                $carnet = $beneficiario->carnetDeGestion($gestion);

                if ($carnet === null) {
                    // La asociación se le pasa al carnet: es lo que se imprime en
                    // la tarjeta, y queda congelada con la que certificó ESTA
                    // emisión. Ver la migración de `carnets`.
                    $carnet = $this->crearCarnet($beneficiario, $gestion, $asociacion);
                    $tipo = TipoTramite::EmisionInicial;
                } else {
                    $this->verificarCarnetAdmiteAdiciones($carnet);
                    $this->verificarRubroNoRepetido($carnet, $rubro);
                    $tipo = TipoTramite::AdicionRubro;
                }

                // Vale para los dos casos: en una emisión inicial el carnet
                // acaba de nacer y no puede tener trámites, así que la consulta
                // devuelve vacío sin costo.
                $this->verificarSinSolicitudEnCurso($carnet, $rubro);

                // --- REGLA B -------------------------------------------------
                $tramite = $carnet->tramites()->create([
                    'rubro_id' => $rubro->id,
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
     *  ACÁ NACE EL RECIBO OFICIAL
     * ------------------------------------------------------------------------
     *
     * Y es el único lugar donde nace. El motivo es de mostrador, no de código:
     * este es el momento en que el pescador ya entregó los papeles y la plata, y
     * se tiene que ir con un comprobante en la mano mientras la unidad revisa.
     *
     * Va DENTRO de la misma transacción para que las dos cosas sean una sola: si
     * el envío se deshace, el recibo tampoco queda y el número vuelve al
     * contador. Un recibo numerado colgando de un trámite que figura sin
     * presentar sería un papel entregado que el sistema no puede explicar.
     *
     * Ver App\Services\ReciboTramiteService.
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
                'fecha_revision' => now(),
            ]);

            // El comprobante que se lleva el pescador. Se emite sobre la copia
            // bloqueada, con el carnet y el rubro cargados: el recibo copia el
            // nombre, la cédula y el concepto, y los lee de ahí.
            $bloqueado->loadMissing(['carnet.beneficiario', 'rubro']);

            $this->recibos->emitir($bloqueado);

            // Se refresca el modelo que trajo QUIEN LLAMÓ, no el bloqueado. Ver
            // el comentario de bloquear() sobre por qué esa distinción importa.
            return $tramite->refresh();
        });
    }

    /**
     * Aprueba el trámite y HABILITA el rubro en el carnet.
     *
     * Este es el único momento en que nace una fila en `carnet_rubro`. Antes de
     * acá el pescador presentó papeles; a partir de acá está autorizado.
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

        try {
            return DB::transaction(function () use ($tramite): Tramite {
                /*
                 * Se relee el trámite con la fila bloqueada antes de decidir.
                 *
                 * El estado se comprobó arriba, FUERA de la transacción: entre
                 * aquella lectura y esta, otro supervisor con la misma ficha
                 * abierta puede haberlo aprobado. Sin esta segunda lectura, los
                 * dos escribirían la habilitación y el segundo se estrellaría
                 * contra el índice único de `carnet_rubro` con un error de base
                 * de datos en vez de un mensaje entendible.
                 */
                $bloqueado = $this->bloquear($tramite);

                $this->verificarTransicion($bloqueado, EstadoTramite::Aprobado);

                // La habilitación. Se crea como modelo y no con attach() porque
                // attach() no dispara eventos de Eloquent, y sin eventos el
                // trait Auditable no registra nada: quedaría sin rastro de quién
                // habilitó el rubro. Ver App\Models\CarnetRubro.
                CarnetRubro::create([
                    'carnet_id' => $bloqueado->carnet_id,
                    'rubro_id' => $bloqueado->rubro_id,
                    // La fecha de habilitación es HOY y no la de emisión del
                    // carnet: una adición de junio se habilita en junio sobre un
                    // carnet emitido en febrero.
                    'fecha_habilitacion' => now()->toDateString(),

                    /*
                     * El cupo se COPIA del trámite a la habilitación.
                     *
                     * Duplicarlo tiene sentido acá porque son dos preguntas
                     * distintas: el trámite responde «cuánto se pidió y se
                     * autorizó en este expediente», la habilitación responde
                     * «cuánto tiene autorizado HOY para este rubro». Leerlo
                     * siempre del trámite obligaría a remontar cuál de todos
                     * habilitó el rubro, y a corregir el histórico cada vez que
                     * la unidad ajuste un cupo.
                     *
                     * Es por rubro: el mismo carnet puede tener Pescador con un
                     * cupo y Comercializador con otro.
                     */
                    'capacidad_kg' => $bloqueado->capacidad_kg,

                    'estado' => EstadoHabilitacion::Habilitado,
                ]);

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
             * pescador vuelve con los papeles corregidos, su nueva solicitud se
             * registra como ADICIÓN sobre este mismo carnet —Regla A— y no se
             * gasta otro registro.
             */

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
                 * lectura se borraría un expediente aprobado —con su habilitación
                 * ya escrita en `carnet_rubro`— y la persona quedaría habilitada
                 * sin ningún papel que lo respalde.
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
            ...$tramite->pagos->pluck('comprobante')->all(),
        ];
    }

    /**
     * ========================================================================
     *  EL CARNET SE VA CON EL ÚLTIMO TRÁMITE QUE LO SOSTENÍA
     * ========================================================================
     *
     * Borrar una EMISIÓN INICIAL deja atrás el carnet que ese mismo trámite
     * creó. Y un carnet sin trámites no es solo basura: OCUPA EL LUGAR de la
     * persona en esa gestión. El índice único (beneficiario, gestión) impediría
     * que vuelva a presentar la solicitud ese año, y el operador vería un error
     * de «ya tiene carnet» señalando un carnet que nadie pidió.
     *
     * Por eso se borra, pero solo si quedó realmente vacío:
     *
     *   - sin otros trámites — una adición borrada deja el carnet en pie, lo
     *     sostiene la emisión inicial que sigue ahí;
     *   - sin habilitaciones — no debería haberlas, porque solo nacen al
     *     aprobar y un trámite aprobado no llega hasta acá. Se comprueba igual:
     *     si alguna vez existiera, borrar el carnet se llevaría en cascada una
     *     habilitación vigente y dejaría a alguien sin su rubro.
     *
     * ES LA EXCEPCIÓN A «EL CARNET NO SE BORRA», no una contradicción. Al
     * RECHAZAR el carnet se conserva porque el expediente existió y se resolvió
     * que no; acá el expediente se borra porque nunca debió existir, y el carnet
     * que colgaba de él tampoco.
     *
     * El hueco en la secuencia de ids es el precio, y es aceptable: ese carnet
     * nunca se imprimió —no tenía ningún rubro habilitado— así que no hay
     * número circulando en la calle que quede sin respaldo.
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
        $tieneTramites = $carnet->tramites()->exists();
        $tieneHabilitaciones = $carnet->habilitaciones()->exists();

        if (! $tieneTramites && ! $tieneHabilitaciones) {
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
     * Crea el carnet de la gestión: firma de validación y vencimiento.
     *
     * No se escribe ningún número: el de registro que se imprime es el `id`
     * que asigna la base. Ver Carnet::registro().
     *
     * Se llama solo desde dentro de la transacción de registrar(), y solo
     * cuando la Regla A determinó que la persona no tiene carnet este año.
     */
    private function crearCarnet(Beneficiario $beneficiario, int $gestion, ?string $asociacion = null): Carnet
    {
        return $beneficiario->carnets()->create([
            'asociacion' => $asociacion,
            /*
             * La firma es lo ÚNICO que identifica al carnet: no hay columna
             * `codigo`. Se genera al azar y se comprueba contra la base antes de
             * usarla; el índice único de la columna es la garantía final. Ver
             * Carnet::nuevaFirma().
             *
             * Se genera UNA VEZ, al crear el carnet. Las adiciones de rubro
             * posteriores no la tocan: si cambiara, el QR ya impreso dejaría de
             * funcionar y habría que reimprimir el plástico.
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
     * @throws SolicitudInvalidaException
     */
    private function verificarCarnetAdmiteAdiciones(Carnet $carnet): void
    {
        // Anulado o vencido. Lo segundo casi no pasa dentro de la misma gestión
        // —vence el 31 de diciembre—, pero un trámite cargado el 31 a las 23:50
        // entraría en ese caso, y es correcto que no se le pueda agregar nada.
        if (! $carnet->admiteAdiciones()) {
            throw SolicitudInvalidaException::carnetNoAdmiteAdiciones($carnet->gestion);
        }
    }

    /**
     * @throws SolicitudInvalidaException
     */
    private function verificarRubroNoRepetido(Carnet $carnet, Rubro $rubro): void
    {
        $habilitacion = $carnet->habilitaciones()->where('rubro_id', $rubro->id)->first();

        if ($habilitacion === null) {
            return;
        }

        /*
         * SUSPENDIDO NO ES LO MISMO QUE HABILITADO, aunque los dos bloqueen.
         *
         * Se distinguen porque al operador le llegan dos mensajes distintos, y
         * uno de ellos le dice qué hacer. «Ya está habilitado» a quien ve el
         * rubro cortado en pantalla es contradictorio y lo manda a buscar el
         * problema donde no está.
         *
         * Lo que bloquea en los dos casos es lo mismo: la habilitación EXISTE, y
         * volver a tramitarla sería cobrar dos veces por una sola.
         */
        if (! $habilitacion->estaHabilitado()) {
            throw SolicitudInvalidaException::rubroSuspendido($rubro->nombre, $carnet->gestion);
        }

        throw SolicitudInvalidaException::rubroYaHabilitado($rubro->nombre, $carnet->gestion);
    }

    /**
     * @throws SolicitudInvalidaException
     */
    private function verificarSinSolicitudEnCurso(Carnet $carnet, Rubro $rubro): void
    {
        /*
         * Un mismo rubro no puede tener dos expedientes pendientes a la vez: el
         * pescador pagaría dos veces por una sola habilitación.
         *
         * Esto NO está como índice en la base y es una decisión, no un olvido:
         * un rubro RECHAZADO sí se puede volver a pedir con los papeles
         * corregidos, así que la combinación (carnet, rubro) se repite
         * legítimamente a lo largo del tiempo. Expresarlo en SQL exigiría un
         * índice parcial sobre los estados abiertos, y el mensaje que necesita
         * ventanilla no lo puede dar la base de todos modos.
         */
        $enCurso = $carnet->tramites()
            ->where('rubro_id', $rubro->id)
            // abiertos() y no pendientes(): un expediente que alguien tomó para
            // revisar sigue en curso, y dejar presentar otro por el mismo rubro
            // haría que el beneficiario pague dos veces por una sola
            // habilitación.
            ->abiertos()
            ->exists();

        if ($enCurso) {
            throw SolicitudInvalidaException::solicitudEnCurso($rubro->nombre);
        }
    }

    /**
     * Convierte un error de índice único en un mensaje que ventanilla entienda.
     *
     * Las comprobaciones de arriba dan buenos mensajes, pero no resisten dos
     * peticiones simultáneas: entre el SELECT y el INSERT, otra conexión puede
     * haber escrito la misma fila. Quien garantiza de verdad es el índice de la
     * base; lo que llega de él es «duplicate key value violates unique
     * constraint carnet_rubro_unico», que no le dice nada a nadie.
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

        if (str_contains($mensaje, 'carnet_rubro')) {
            return SolicitudInvalidaException::rubroYaHabilitado(
                $tramite?->rubro?->nombre ?? 'solicitado',
                $tramite?->carnet?->gestion ?? (int) now()->format('Y'),
            );
        }

        if (str_contains($mensaje, 'gestion')) {
            return new SolicitudInvalidaException(
                'El beneficiario ya tiene un carnet en esta gestión. Vuelva a intentarlo: '.
                'la solicitud se registrará como adición de rubro.',
            );
        }

        return $e;
    }
}

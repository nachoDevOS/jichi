<?php

namespace App\Services;

use App\Enums\EstadoValidacionPago;
use App\Exceptions\SolicitudInvalidaException;
use App\Models\Pago;
use App\Models\Tramite;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 *  REGLA C — pagos parciales de un trámite (1 a N)
 * ============================================================================
 *
 * Un trámite tiene un costo (`monto_requerido`, copiado del rubro al
 * registrarlo) y se cubre con uno o varios depósitos. Este servicio es el único
 * lugar del sistema que escribe en `pagos`.
 *
 * ----------------------------------------------------------------------------
 *  LOS MÉTODOS VIENEN DE A PARES, Y NO ES DUPLICACIÓN
 * ----------------------------------------------------------------------------
 *
 * Cada operación existe dos veces: una que recibe el UploadedFile y sube el
 * archivo, y otra —`...ConRuta`— que recibe la ruta ya subida y solo escribe en
 * la base.
 *
 * El motivo es el de siempre: una transacción de base de datos NO deshace
 * escrituras en disco. Cuando SolicitudCarnetService registra una solicitud con
 * pagos, necesita que TODOS los archivos —los dos adjuntos del expediente y
 * cada boleta— estén subidos ANTES de abrir la transacción, para poder
 * borrarlos todos si algo falla adentro. Si este servicio subiera sus propios
 * archivos desde dentro de esa transacción, el rollback deshace las filas pero
 * las boletas quedan tiradas en el disco sin dueño.
 *
 * La versión con UploadedFile es la que usa ventanilla cuando carga UNA boleta
 * suelta sobre un trámite existente: ahí no hay transacción de afuera, y sube y
 * escribe en el mismo paso.
 *
 * ----------------------------------------------------------------------------
 *  QUÉ NO HACE ESTE SERVICIO, Y POR QUÉ
 * ----------------------------------------------------------------------------
 *
 *   - No aprueba el trámite al completarse el monto. Cobrar y aprobar son dos
 *     actos distintos y los hacen dos personas distintas; unirlos acá le daría
 *     a ventanilla la capacidad de aprobar expedientes con solo cargar la
 *     última boleta.
 *
 *   - No impide pagar de más. Un depósito puede venir por unos bolivianos de
 *     más y rechazarlo dejaría al pescador sin poder tramitar por un error del
 *     cajero del banco. El excedente queda a la vista en la ficha.
 */
class PagoTramiteService
{
    /** Carpeta de storage donde van las boletas escaneadas. */
    public const CARPETA_COMPROBANTES = 'pagos';

    public function __construct(private readonly ArchivoTramiteService $archivos) {}

    /**
     * Registra UN pago, subiendo la boleta.
     *
     * Es la entrada que usa el formulario de ventanilla. Sube primero y escribe
     * después; si la escritura falla, borra el archivo que acaba de subir.
     *
     * @param  array{nro_transaccion: string, monto: float|string, fecha_pago?: string|null, observaciones?: string|null}  $datos
     *
     * @throws SolicitudInvalidaException
     */
    public function registrar(Tramite $tramite, array $datos, UploadedFile $comprobante): Pago
    {
        $this->verificarQueAdmitePagos($tramite);
        $this->verificarTransaccionNueva($datos['nro_transaccion']);

        $ruta = $this->archivos->guardar($comprobante, self::CARPETA_COMPROBANTES);

        try {
            return DB::transaction(fn (): Pago => $this->registrarConRuta($tramite, $datos, $ruta));
        } catch (\Throwable $e) {
            // La fila no se escribió: el archivo que se subió para ella no le
            // sirve a nadie.
            $this->archivos->descartar([$ruta]);

            throw $e;
        }
    }

    /**
     * Escribe UN pago cuya boleta ya está subida. No toca el disco.
     *
     * La usa SolicitudCarnetService desde dentro de su transacción, con las
     * rutas que subió antes de abrirla.
     *
     * @param  array{nro_transaccion: string, monto: float|string, fecha_pago?: string|null, observaciones?: string|null}  $datos
     *
     * @throws SolicitudInvalidaException
     */
    public function registrarConRuta(Tramite $tramite, array $datos, string $ruta): Pago
    {
        $this->verificarQueAdmitePagos($tramite);
        $this->verificarTransaccionNueva($datos['nro_transaccion']);

        try {
            return $tramite->pagos()->create([
                'nro_transaccion' => $datos['nro_transaccion'],
                'monto' => $datos['monto'],
                'urlFile' => $ruta,
                // Si no se indica, se toma ahora. La fecha del depósito no es la
                // de carga —una boleta del viernes se registra el lunes—, pero
                // cuando ventanilla no la escribe, la de hoy es la mejor
                // aproximación disponible y deja la fila ordenable.
                'fecha_pago' => $datos['fecha_pago'] ?? now(),
                'observaciones' => $datos['observaciones'] ?? null,
                /*
                 * QUIÉN LO CARGÓ, para que después no pueda validarlo él mismo.
                 *
                 * Sale de la sesión y no de los datos del formulario: el
                 * navegador no puede decidir a nombre de quién se registra un
                 * cobro. Queda null cuando esto corre desde un comando de
                 * consola, y ahí `Pago::puedeValidarlo()` deja pasar a cualquiera
                 * —no hay con quién comparar—.
                 */
                'registrado_por' => auth()->id(),
            ]);
        } catch (QueryException $e) {
            // El índice único de `nro_transaccion` es la defensa que sí resiste
            // dos peticiones simultáneas, cuando la comprobación de arriba ya
            // pasó en las dos. Se traduce a un mensaje que ventanilla entienda.
            if ($this->esViolacionDeUnicidad($e)) {
                throw SolicitudInvalidaException::transaccionRepetida($datos['nro_transaccion']);
            }

            throw $e;
        }
    }

    /**
     * Escribe varios pagos ya subidos, todo o nada.
     *
     * Lo usa el formulario de solicitud, donde el pescador puede llegar con dos
     * boletas del mismo día. Si la segunda falla, la primera tampoco queda: un
     * trámite con la mitad del dinero cargado y el operador convencido de que
     * guardó las dos es peor que un error limpio.
     *
     * DB::transaction() anidado usa savepoints, así que llamarlo desde
     * SolicitudCarnetService::registrar() —que ya tiene una transacción
     * abierta— deshace hasta el savepoint y deja que la excepción siga subiendo
     * para que la transacción de afuera también se deshaga.
     *
     * @param  array<int, array{nro_transaccion: string, monto: float|string, ruta: string, fecha_pago?: string|null, observaciones?: string|null}>  $pagos
     * @return array<int, Pago>
     */
    public function registrarVariosConRutas(Tramite $tramite, array $pagos): array
    {
        return DB::transaction(function () use ($tramite, $pagos): array {
            $registrados = [];

            foreach ($pagos as $datos) {
                $ruta = $datos['ruta'];
                unset($datos['ruta']);

                $registrados[] = $this->registrarConRuta($tramite, $datos, $ruta);
            }

            return $registrados;
        });
    }

    /**
     * ========================================================================
     *  CORREGIR UN DEPÓSITO YA CARGADO
     * ========================================================================
     *
     * Es la única salida cuando la boleta se tipeó mal: la tabla `pagos` no
     * tiene borrado lógico y no hay anulación, porque un pago «anulado» que
     * sigue en la lista solo invita a sumarlo por error. Lo que había queda
     * registrado por el trait Auditable —valor anterior, quién y cuándo—.
     *
     * LA BOLETA ES OPCIONAL, igual que los adjuntos al editar el expediente: lo
     * más común es corregir el monto o el número y dejar el archivo como está.
     * Cuando sí viene una nueva, se sube ANTES de abrir la transacción y la
     * vieja se borra DESPUÉS del commit — el mismo orden que
     * SolicitudCarnetService::actualizar(), y por el mismo motivo: una
     * transacción de base de datos no deshace escrituras en disco.
     *
     * ------------------------------------------------------------------------
     *  CORREGIR UN DEPÓSITO OBSERVADO LO DEVUELVE A «SIN CONTROLAR»
     * ------------------------------------------------------------------------
     *
     * Es el circuito de la observación cerrándose: quien revisa escribió «la
     * boleta dice 120 y está cargado 150», ventanilla corrige, y lo que hay
     * ahora es un dato nuevo que nadie miró todavía. Dejarlo observado
     * mostraría un problema ya resuelto; darlo por validado sería validarlo sin
     * que nadie lo comparara contra el extracto. Sin controlar es lo que es.
     *
     * El motivo viejo se borra con él: habla de un número que ya no está.
     *
     * @param  array{nro_transaccion: string, monto: float|string, fecha_pago?: string|null, observaciones?: string|null}  $datos
     *
     * @throws SolicitudInvalidaException
     */
    public function corregir(Pago $pago, array $datos, ?UploadedFile $comprobante = null): Pago
    {
        $this->verificarQueAdmiteCorreccion($pago);
        $this->verificarTransaccionNueva($datos['nro_transaccion'], $pago->id);

        $ruta = $comprobante instanceof UploadedFile
            ? $this->archivos->guardar($comprobante, self::CARPETA_COMPROBANTES)
            : null;

        // La ruta vieja se guarda ANTES del update: después, el modelo ya no
        // sabe qué archivo tenía.
        $anterior = $ruta === null ? null : $pago->urlFile;

        /*
         * SOLO LAS COLUMNAS QUE SE PUEDEN CORREGIR.
         *
         * Es una lista blanca explícita y no un `$datos` a secas: `#[Fillable]`
         * ya impediría que llegara `estado_validacion` desde el formulario,
         * pero lo hace EN SILENCIO —ver la trampa anotada en CLAUDE.md— y acá
         * queda escrito qué se puede tocar y qué no.
         */
        $editables = [
            'nro_transaccion' => $datos['nro_transaccion'],
            'monto' => $datos['monto'],
            /*
             * LO QUE NO LLEGÓ SE CONSERVA, NO SE BORRA.
             *
             * `?? null` acá borraba en silencio: el formulario de corrección
             * tiene cuatro campos y no manda `observaciones`, así que cada
             * corrección de monto se llevaba puesto el texto que alguien había
             * escrito. `array_key_exists` distingue «lo mandó vacío» —y ahí sí
             * se borra, porque es lo que el operador pidió— de «no lo mandó».
             */
            'fecha_pago' => $datos['fecha_pago'] ?? $pago->fecha_pago,
            'observaciones' => array_key_exists('observaciones', $datos)
                ? $datos['observaciones']
                : $pago->observaciones,
        ];

        if ($ruta !== null) {
            $editables['urlFile'] = $ruta;
        }

        // Ver el docblock: el dato cambió, así que la observación que lo
        // señalaba ya no corresponde y el control vuelve a empezar.
        $control = $pago->estaObservado() ? [
            'estado_validacion' => EstadoValidacionPago::Pendiente,
            'validado_por' => null,
            'validado_at' => null,
            'motivo_observacion' => null,
        ] : [];

        try {
            DB::transaction(function () use ($pago, $editables, $control): void {
                $pago->update([...$editables, ...$control]);
            });
        } catch (QueryException $e) {
            $this->archivos->descartar([$ruta]);

            // Igual que en el alta: entre la comprobación de arriba y este
            // UPDATE otra petición pudo meter el mismo número. Quien garantiza
            // es el índice único; acá solo se traduce su error.
            if ($this->esViolacionDeUnicidad($e)) {
                throw SolicitudInvalidaException::transaccionRepetida($datos['nro_transaccion']);
            }

            throw $e;
        } catch (\Throwable $e) {
            $this->archivos->descartar([$ruta]);

            throw $e;
        }

        // Recién con el commit hecho se borra la boleta vieja. Al revés, un
        // fallo del update dejaría la fila apuntando a un archivo que ya no
        // existe.
        $this->archivos->descartar([$anterior]);

        return $pago->refresh();
    }

    /**
     * ========================================================================
     *  QUITAR UN DEPÓSITO DEL EXPEDIENTE
     * ========================================================================
     *
     * El caso real: la misma boleta cargada dos veces, o la de otra persona
     * pegada en el expediente equivocado. Corregirla no alcanza, porque no hay
     * ningún dato correcto que poner — ese depósito no va acá.
     *
     * ES LA SEGUNDA OPERACIÓN DEL SISTEMA QUE NO DEJA LA FILA, después de
     * eliminar un expediente, y por eso se parece a aquella en todo:
     *
     *   EL MOTIVO ES OBLIGATORIO y se comprueba ANTES que el estado. Si faltan
     *   las dos cosas, el mensaje útil es «escriba el motivo» y no «este
     *   depósito ya está validado», que no le dice al operador qué corregir.
     *   Después del delete la fila ya no existe: el número de transacción, el
     *   monto y el vínculo con la boleta se van con ella, así que si el porqué
     *   no viaja colgado del evento no queda dónde ponerlo.
     *
     *   EL ARCHIVO SE BORRA DESPUÉS DEL COMMIT, y su ruta se lee ANTES: hecha
     *   la baja, el modelo ya no tiene de dónde sacarla. Al revés —borrar el
     *   archivo primero— un fallo de la transacción dejaría la fila viva
     *   apuntando a una boleta que ya no está, que es peor que un huérfano.
     *
     * @throws SolicitudInvalidaException
     */
    public function eliminar(Pago $pago, string $motivo): void
    {
        if (blank($motivo)) {
            throw SolicitudInvalidaException::motivoEliminacionPagoObligatorio();
        }

        if (! $pago->admiteEliminacion()) {
            throw SolicitudInvalidaException::pagoNoSePuedeEliminar(
                $pago->motivoSinEliminacion() ?? 'ya no se puede quitar.',
            );
        }

        $ruta = $pago->urlFile;

        DB::transaction(function () use ($pago, $motivo): void {
            // El motivo viaja CON el evento de borrado. Ver
            // App\Traits\Auditable::$motivoAuditoria.
            $pago->motivoAuditoria = trim($motivo);

            $pago->delete();
        });

        // descartar() no propaga errores: la fila ya no está, y volver atrás
        // por un archivo que no se dejó borrar sería peor que el huérfano.
        $this->archivos->descartar([$ruta]);
    }

    /**
     * Lo que falta cobrar, leído de la base en el momento.
     *
     * Se consulta la suma y no se usa lo que el modelo tenga cargado: si se
     * acaba de insertar un pago, la colección en memoria puede estar vieja y el
     * saldo saldría mal justo cuando más importa —al decidir si se puede
     * aprobar—.
     */
    public function saldoPendiente(Tramite $tramite): float
    {
        $pagado = (float) $tramite->pagos()->sum('monto');

        return max(0, (float) $tramite->monto_requerido - $pagado);
    }

    public function estaCubierto(Tramite $tramite): bool
    {
        return $this->saldoPendiente($tramite) <= 0;
    }

    // ------------------------------------------------------------------
    //  Comprobaciones
    // ------------------------------------------------------------------

    /**
     * @throws SolicitudInvalidaException
     */
    private function verificarQueAdmitePagos(Tramite $tramite): void
    {
        // Solo mientras el expediente esté ABIERTO. Aprobado no: se aprueba con
        // el monto ya cubierto, así que un depósito más no pertenece a este
        // trámite. Rechazado tampoco: hay que reabrirlo primero. El porqué
        // largo de las dos está en EstadoTramite::permitePagos().
        if (! $tramite->estado->permitePagos()) {
            // Se le pasa el estado para que el mensaje diga cuál de los dos
            // casos es: la salida de un rechazado es reabrirlo, la de un
            // aprobado es que ese dinero no va acá.
            throw SolicitudInvalidaException::pagoNoAdmitido($tramite->estado);
        }
    }

    /**
     * @throws SolicitudInvalidaException
     */
    private function verificarTransaccionNueva(string $nroTransaccion, ?int $exceptoId = null): void
    {
        // Esta comprobación existe para dar un mensaje entendible, no para
        // garantizar nada: entre este SELECT y el INSERT otra petición puede
        // meter la misma boleta. Quien garantiza es el índice único, y el catch
        // de registrarConRuta() traduce su error.
        //
        // `$exceptoId` es para la CORRECCIÓN: al corregir un depósito sin
        // tocarle el número, la fila que se está editando es su propia
        // coincidencia, y sin esta salvedad el sistema se acusaría a sí mismo
        // de repetir la transacción.
        $repetida = Pago::where('nro_transaccion', $nroTransaccion)
            ->when($exceptoId, fn ($q, $id) => $q->whereKeyNot($id))
            ->exists();

        if ($repetida) {
            throw SolicitudInvalidaException::transaccionRepetida($nroTransaccion);
        }
    }

    /**
     * @throws SolicitudInvalidaException
     */
    private function verificarQueAdmiteCorreccion(Pago $pago): void
    {
        // El motivo lo arma el modelo: es el MISMO texto que la pantalla
        // muestra en lugar del botón. Ver Pago::motivoSinCorreccion().
        if (! $pago->admiteCorreccion()) {
            throw SolicitudInvalidaException::pagoNoSePuedeCorregir(
                $pago->motivoSinCorreccion() ?? 'ya no admite cambios.',
            );
        }
    }

    /**
     * ¿El error de la base es una violación de índice único?
     *
     * El código SQLSTATE 23505 es el estándar para «unique_violation» y lo usan
     * tanto PostgreSQL como SQLite a través del driver PDO, así que la misma
     * comprobación sirve en producción y en las pruebas en memoria.
     */
    private function esViolacionDeUnicidad(QueryException $e): bool
    {
        return $e->getCode() === '23505'
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}

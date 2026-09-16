<?php

namespace App\Services;

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
        // Pendiente y aprobado sí: el pescador puede estar juntando el monto, y
        // un trámite aprobado puede terminar de cobrarse después. Rechazado no:
        // ese expediente ya no cobra nada. Ver EstadoTramite::permitePagos().
        if (! $tramite->estado->permitePagos()) {
            throw SolicitudInvalidaException::pagoNoAdmitido();
        }
    }

    /**
     * @throws SolicitudInvalidaException
     */
    private function verificarTransaccionNueva(string $nroTransaccion): void
    {
        // Esta comprobación existe para dar un mensaje entendible, no para
        // garantizar nada: entre este SELECT y el INSERT otra petición puede
        // meter la misma boleta. Quien garantiza es el índice único, y el catch
        // de registrarConRuta() traduce su error.
        if (Pago::where('nro_transaccion', $nroTransaccion)->exists()) {
            throw SolicitudInvalidaException::transaccionRepetida($nroTransaccion);
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

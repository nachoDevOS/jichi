/**
 * Tipos del libro de caja.
 *
 * Lo arma App\Http\Controllers\Panel\PagoController.
 */

/**
 * Espejo de App\Enums\EstadoValidacionPago.
 *
 * NO es el estado del pago —el dinero entró o no entró— sino el de su CONTROL:
 * si alguien abrió la boleta y la comparó contra el extracto del banco.
 */
export type EstadoValidacionPago = 'pendiente' | 'validado' | 'observado';

/**
 * El control de un depósito, tal como lo arma App\Support\ControlDePago.
 *
 * Lo comparten el libro de caja y la ficha del trámite, que es donde quien
 * revisa hace el trabajo.
 */
export interface ControlDePago {
    validacion: EstadoValidacionPago;
    validacion_etiqueta: string;
    validacion_color: string;

    /** Quién lo cargó en ventanilla. Null en lo migrado de antes. */
    registrado_por: string | null;
    /** Quién lo controló. Se llena también al observar: observar es revisar. */
    validado_por: string | null;
    validado_at: string | null;
    /** Qué no cuadra. Solo cuando está observado. */
    motivo_observacion: string | null;

    /**
     * Si el usuario que está mirando puede controlar ESTE depósito.
     *
     * Lo decide el servidor —`Pago::puedeValidarlo()`— e incluye la regla de que
     * quien cargó la boleta no puede darla por buena. Es solo la mitad: la otra
     * es el permiso `pagos.validar`, que se consulta con usePermisos().
     */
    puede_validarse: boolean;

    /**
     * Si ESTE es el momento de controlarlo.
     *
     * Es otra pregunta que `puede_validarse`: una mira el ESTADO de lo que se
     * paga —el control es parte de la revisión, así que un trámite pendiente o
     * ya resuelto no admite— y la otra mira QUIÉN.
     */
    puede_controlarse: boolean;

    /**
     * Por qué no se puede controlar, ya escrito para mostrar. Null cuando sí se
     * puede.
     *
     * Lo arma el servidor porque el texto cambia según el caso, y reescribirlo
     * en React sería tener dos versiones del mismo mensaje.
     */
    motivo_sin_control: string | null;
}

/** Una fila del libro de caja. */
export interface PagoFila extends ControlDePago {
    id: number;
    nro_transaccion: string;
    monto: number;
    fecha_pago: string | null;
    comprobante_url: string | null;

    /**
     * De qué es el depósito, ya escrito para leer: «Emisión inicial»,
     * «Faena 002190», «Guía 000308».
     *
     * Desde que la tabla `pagos` es polimórfica, una fila del libro de caja
     * puede venir de un trámite, de una faena o de una guía. El rótulo lo arma
     * el servidor —PagoController::origen()— y no React, porque depende de a
     * qué apunte la fila y eso el frontend no lo sabe.
     */
    concepto: string;

    /**
     * Null cuando el pago NO es de un trámite.
     *
     * Es lo único que habilita el enlace a la ficha del expediente: faenas y
     * guías todavía no tienen pantalla propia, así que esas filas se muestran
     * sin enlace en vez de llevar a una ruta que no existe.
     */
    tramite_id: number | null;

    carnet_registro: string | null;
    beneficiario: string | null;
    rubro: string | null;
}

/**
 * Lo que manda el formulario de carga de un depósito.
 *
 * El monto va como texto porque el <input> devuelve texto; la conversión a
 * número la hace Laravel al validar con la regla `numeric`.
 */
export interface FormularioPago {
    nro_transaccion: string;
    monto: string;
    comprobante: File | null;
    fecha_pago: string;
    observaciones: string;
}

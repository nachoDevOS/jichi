/**
 * Tipos del libro de caja.
 *
 * Lo arma App\Http\Controllers\Panel\PagoController.
 */

/** Una fila del libro de caja. */
export interface PagoFila {
    id: number;
    nro_transaccion: string;
    monto: number;
    fecha_pago: string | null;
    comprobante_url: string | null;
    tramite_id: number;
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

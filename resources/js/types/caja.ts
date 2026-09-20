/**
 * Tipos del módulo Caja y Recibos — el circuito del dinero.
 */

/** Un abono, en el listado de caja. */
export interface PagoFila {
    id: number;
    /**
     * NULL mientras el depósito no tiene papel.
     */
    recibo_id: number | null;
    numero_recibo: string | null;
    /** A nombre de quién salió el comprobante. Puede ser un tercero. */
    a_nombre_de: string | null;
    /** «Credencial Pescador», «Guía de movimiento»… */
    concepto: string;
    /** De quién es el trámite que este abono paga. */
    titular: string | null;
    monto_parcial: number;

    /**
     * La boleta del banco. Nunca faltan: TODO pago es un depósito bancario —no
     * hay efectivo ni QR—, así que cada fila lleva su número, su fecha y su
     * archivo.
     */
    nro_transaccion: string;
    comprobante_url: string | null;
    /** Un DÍA —lo que dice la boleta—: se muestra con fecha(). */
    fecha_deposito: string | null;
    /** Un MOMENTO —cuándo entró la plata—: se muestra con fechaHora(). */
    cobrado_en: string | null;
}

/**
 * Lo cobrado hoy, repartido por método.
 */
export interface ArqueoDelDia {
    fecha: string;
    total: number;
    cantidad: number;
    /**
     * Lo DEPOSITADO hoy según la boleta, que es otra pregunta que lo cargado:
     * un depósito del viernes registrado el lunes entra en uno y no en el otro.
     * El primero cuadra el trabajo del día; este se cruza contra el extracto.
     */
    total_depositado: number;
    cantidad_depositada: number;
}

/**
 * Una deuda de la persona, lista para cobrar.
 */
export interface DeudaCobrable {
    tipo: 'carnet' | 'cupo' | 'guia';
    id: number;
    titulo: string;
    detalle: string;
    /** Lo que cuesta el trámite entero. */
    monto: number;
    /** Lo que falta. Se corta en cero: pagar de más no da saldo a favor. */
    saldo: number;
    /** Lo ya abonado, para que se vea que es una cuota y no el total. */
    pagado: number;
}

/** Una línea del formulario de cobro. */
export interface LineaCobro {
    tipo: DeudaCobrable['tipo'];
    id: number;
    monto: number | string;
}

/** Lo que el formulario de cobro manda de vuelta. */
export interface FormularioCobro {
    /** Siempre obligatorios: todo pago es un depósito bancario. */
    nro_transaccion: string;
    fecha_deposito: string;
    comprobante: File | null;
    lineas: LineaCobro[];
    concepto: string;
}

/** Un recibo, en el listado. */
export interface ReciboFila {
    id: number;
    /** Correlativo de caja: «REC-2026-0016». Lo audita Contabilidad. */
    numero_recibo: string;
    /** A nombre de quién sale. Se lee del padrón, no está copiado en el recibo. */
    beneficiario_id: number;
    beneficiario: string | null;
    /** Su cédula como se escribe en el papel: «1234567-1A BN». */
    documento: string | null;
    concepto: string;
    /**
     * Lo que se IMPRIMIÓ, congelado al emitir.
     *
     * No se recalcula al leer: el papel entregado no puede cambiar porque
     * después se corrija un abono.
     */
    monto_total: number;
    /** Lo que HAY hoy en el detalle. Si difiere de lo impreso, no cuadra. */
    monto_actual: number;
    /** Con un céntimo de tolerancia, por el redondeo. Llega resuelto. */
    cuadra: boolean;
    pagos_count: number;
    emitido_en: string | null;
}

/** El recibo con su detalle, en la ficha. */
export interface ReciboFicha extends Omit<ReciboFila, 'pagos_count'> {
    pagos: {
        id: number;
        concepto: string;
        /** El trámite concreto: código, escala o ruta, más el titular. */
        detalle: string | null;
        monto_parcial: number;
        /** La boleta del banco. Ver PagoFila. */
        nro_transaccion: string;
        comprobante_url: string | null;
        fecha_deposito: string | null;
    }[];
}

/**
 * Tipos del libro de recibos: uno por documento, emitido cuando SIREB confirmó el pago.
 */

/** Un recibo, en el listado. */
export interface ReciboFila {
    id: number;
    /** Correlativo continuo: «000016». Lo audita Contabilidad. */
    numero_recibo: string;
    /** A nombre de quién sale. Se lee del padrón, no está copiado en el recibo. */
    beneficiario_id: number;
    beneficiario: string | null;
    /** Su cédula como se escribe en el papel: «1234567-1A BN». */
    documento: string | null;
    concepto: string;
    /** Lo pagado en SIREB, congelado al emitir. */
    monto_total: number;
    /** La boleta tal como la validó SIREB. */
    numero_boleta: string | null;
    emitido_en: string | null;
}

/** El recibo en la ficha: la boleta completa y el documento que pagó. */
export interface ReciboFicha extends ReciboFila {
    entidad_bancaria: string | null;
    /** Un DÍA, el de la boleta: se muestra con fecha(). */
    fecha_pago: string | null;
    documento_pagado: { nombre: string; url: string } | null;
}

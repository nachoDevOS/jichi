import type { EstadoGuia } from '@/types';

/**
 * Tipos del módulo Guías — el amparo de UN traslado de producto.
 */

/** Una guía, tal como la pintan el listado y la ficha. */
export interface GuiaFila {
    id: number;
    /** El código de la hoja del talonario. Único GLOBAL, en mayúsculas. */
    codigo_guia: string;

    beneficiario_id: number;
    comercializador: string | null;
    /** La sigla, copiada del carnet al emitir. */
    asociacion: string | null;

    origen: string;
    destino: string;
    /** «Trinidad → Santa Cruz», armado por el servidor. */
    ruta: string;
    peso_total_kg: number;

    /** Marcado, el arancel se cobra al 50%: el criadero no saca del río. */
    es_piscicultura: boolean;
    /**
     * Por cuánto se multiplicó el arancel: 1 o 0.5.
     */
    factor_arancel: number;

    estado: EstadoGuia;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    /** Se pasó de hora y sigue activa: un camión sin papel válido en la ruta. */
    caducada: boolean;
    /** Negativo si ya venció. Null si no tiene fecha. */
    horas_restantes: number | null;

    puede_cerrarse: boolean;
    /**
     * Solo una guía EN CURSO se anula.
     *
     * Una CERRADA no: cerrar significa que la carga llegó, así que anularla
     * declararía que nunca amparó nada y dejaría un viaje real sin respaldo.
     */
    puede_anularse: boolean;

    monto: number;
    saldo_pendiente: number;
    pagado: boolean;

    /** ISO 8601 con hora. Se muestra con fechaHora(). */
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
}

/** La guía con el detalle que solo pinta la ficha. */
export interface GuiaFicha extends GuiaFila {
    asociacion_nombre: string | null;
    documento: string | null;
}

/** Lo que el formulario de emisión manda de vuelta. */
export interface FormularioGuia {
    carnet_id: number | null;
    codigo_guia: string;
    origen: string;
    destino: string;
    peso_total_kg: number | string;
    es_piscicultura: boolean;
    fecha_emision: string;
}

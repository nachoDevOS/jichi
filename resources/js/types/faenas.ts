import type { EstadoFaena } from '@/types';

/**
 * Tipos del módulo Faenas — el permiso de UNA salida de pesca.
 */

/** Una faena, tal como la pintan el listado y la ficha. */
export interface FaenaFila {
    id: number;
    /** Correlativo GLOBAL y continuo del talonario. Lo genera el sistema. */
    numero_faena: number;
    /** El mismo, con los seis ceros del papel: «002190». */
    numero_legible: string;
    /** «Faena N° 002190», armado por el servidor. */
    etiqueta: string;

    carnet_id: number;
    /** En grupos de cuatro: «PES2 6K7R J2M». */
    carnet_codigo: string | null;
    beneficiario_id: number | null;
    beneficiario: string | null;

    /**
     * Los kilos que esta salida compromete contra la bolsa madre.
     *
     * Lo declarado al salir es una previsión; al COMPLETAR se puede corregir
     * contra lo que dijo la balanza.
     */
    kilos_extraidos: number;

    /**
     * LOS RENGLONES DEL TALONARIO DE PAPEL. Null porque el formulario se llena
     * a mano y llega incompleto: no hay padrón de embarcaciones ni de
     * comandantes, así que son texto libre.
     */
    embarcacion: string | null;
    propietario: string | null;
    comandante_barco: string | null;
    matricula_naval: string | null;
    nro_kardex: string | null;
    /** La región amparada: «desde» y «hasta» del papel. */
    region_desde: string | null;
    region_hasta: string | null;

    estado: EstadoFaena;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    consume_cupo: boolean;
    /** Se pasó de fecha y sigue activa: trabajo sin cerrar, no una previsión. */
    caducada: boolean;
    puede_completarse: boolean;
    /** Por qué todavía no autoriza a salir. `null` cuando sí autoriza. */
    motivo_sin_autorizar: string | null;
    /** Si pasó por la firma. Hasta entonces el permiso no vale. */
    ya_fue_aprobada: boolean;
    /** Si se le pueden cargar depósitos hoy. Solo mientras está pendiente. */
    admite_pagos: boolean;

    /** El arancel de la salida y cómo va cobrado. */
    monto: number;
    saldo_pendiente: number;
    pagado: boolean;

    /** El recibo del trámite. Existe desde el ENVÍO; null mientras es borrador. */
    recibo_id: number | null;
    recibo_numero: string | null;

    /** Cuándo se cargó la fila. Un MOMENTO: se muestra con fechaHora() y hace(). */
    registrado_en: string | null;

    /** Un DÍA, no un instante: llega como 'AAAA-MM-DD' y se muestra con fecha(). */
    fecha_solicitud: string | null;
    fecha_salida: string | null;
    /** La del papel: cuándo vuelve. La escribe el operador. */
    fecha_desembarque: string | null;
    /** El techo que calcula el sistema: salida + DIAS_VIGENCIA. */
    fecha_limite: string | null;
    /** La escribe la aprobación. Null mientras es una solicitud. */
    fecha_emision: string | null;
}

/** La faena con el detalle que solo pinta la ficha. */
export interface FaenaFicha extends FaenaFila {
    asociacion: string | null;

    /**
     * LAS DEL CIRCUITO DE REVISIÓN, resueltas en el servidor. React no vuelve
     * a evaluar el estado: pregunta por estas.
     */
    puede_enviarse: boolean;
    puede_revisarse: boolean;
    puede_aprobarse: boolean;
    /** Cuántas boletas quedan sin controlar. Bloquean la aprobación. */
    pagos_sin_validar: number;

    /**
     * El cupo del que salieron los kilos.
     *
     * Va en la ficha porque es la pregunta que sigue: «¿le queda para otra
     * salida?». Sin esto habría que ir al módulo de cupos a buscarlo.
     */
    cupo: {
        id: number;
        escala: number | null;
        volumen_total_kg: number;
        saldo_kg: number;
        porcentaje_usado: number;
    } | null;
}

/** Lo que el formulario de emisión manda de vuelta. */
export interface FormularioFaena {
    carnet_id: number | null;
    kilos_extraidos: number | string;
    fecha_salida: string;
    fecha_desembarque: string;

    /** Los renglones del talonario: opcionales, el papel llega incompleto. */
    embarcacion: string;
    propietario: string;
    comandante_barco: string;
    matricula_naval: string;
    nro_kardex: string;
    region_desde: string;
    region_hasta: string;
}

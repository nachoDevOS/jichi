import type { EstadoCarnet, TipoActor } from '@/types';

/**
 * Tipos del panel principal.
 */

/** Los números grandes del encabezado. */
export interface ResumenDelDia {
    fecha: string;
    gestion: number;

    /**
     * Cuánta gente está habilitada HOY. El servidor lo calcula mirando el
     * estado Y la fecha de vencimiento: la columna de estado sola puede estar
     * desfasada, porque `vencido` lo escribe un comando diario.
     */
    carnets_vigentes: number;

    /** Emitidos en la gestión, vigentes o no. La diferencia alimenta el desglose. */
    carnets_gestion: number;

    /** Bolsas madre con fecha vigente: de ellas salen los kilos de las faenas. */
    cupos_activos: number;

    faenas_vigentes: number;
    guias_vigentes: number;

    /** Lo emitido hoy sumando carnets, faenas y guías: el pulso del día. */
    documentos_hoy: number;

    recaudado_hoy: number;
    recaudado_mes: number;

    /** Lo que falta cobrar, sumando los tres trámites que se cobran. */
    por_cobrar: number;
}

/**
 * Una jornada de la serie de los últimos catorce días.
 */
export interface ActividadDia {
    /** Clave ordenable: '2026-09-16'. */
    dia: string;
    /** Lo que se muestra al pasar el mouse: '16 sep.'. */
    etiqueta: string;
    /** Carnets + faenas + guías emitidas ese día. */
    documentos: number;
    recaudado: number;
}

/** Una barra del gráfico de carnets vigentes por tipo del catálogo. */
export interface CarnetsPorTipo {
    tipo: string;
    cantidad: number;
}

/**
 * Pescadores contra comercializadores, entre los carnets vigentes.
 */
export interface CarnetsPorActor {
    tipo: TipoActor;
    etiqueta: string;
    color: string;
    cantidad: number;
}

/** Un punto de la línea de recaudación de los últimos 12 meses. */
export interface RecaudacionMes {
    /** Clave ordenable: '2026-09'. */
    periodo: string;
    /** Lo que se muestra en el eje: 'Sep 26'. */
    etiqueta: string;
    total: number;
}

/** Una fila de la tabla de últimas credenciales emitidas. */
export interface UltimoCarnet {
    id: number;
    /** En grupos de cuatro: «PES2 6000 0017». Se guarda sin separadores. */
    codigo: string;
    beneficiario: string | null;
    asociacion: string | null;
    tipo_actor: TipoActor;
    tipo_actor_etiqueta: string;
    tipo_actor_color: string;
    estado: EstadoCarnet;
    estado_etiqueta: string;
    estado_color: string;
    monto: number;
    saldo_pendiente: number;
    /** Un DÍA, no un instante: llega como 'AAAA-MM-DD' y se muestra con fecha(). */
    fecha_emision: string | null;
}

/**
 * Lo que está por caducar o ya caducó sin cerrarse.
 */
export interface Avisos {
    gestion: number;
    /** Con cuántos días de anticipación se avisa. Lo fija el servidor. */
    dias_aviso: number;
    carnets_por_vencer: number;
    cupos_por_vencer: number;
    /** Sin kilos, aunque la fecha no haya llegado: hay que tramitar otro cupo. */
    cupos_agotados: number;
    faenas_vencidas: number;
    guias_vencidas: number;
}

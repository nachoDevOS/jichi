import type { EstadoCarnet, TipoActor } from '@/types';

/**
 * Tipos del panel principal.
 */

/** Lo que espera el pago en SIREB, de un tipo de documento. La URL llega con el filtro puesto. */
export interface Pendiente {
    documento: string;
    por_pagar: number;
    /** null = el rol no ve ese módulo: el número va sin enlace. */
    url_por_pagar: string | null;
}

/** Los cuatro números del tablero. «Vigente» lo calcula el servidor mirando estado y fecha. */
export interface Resumen {
    pescadores: number;
    comercializadores: number;
    autorizaciones_vigentes: number;
    faenas_vigentes: number;
    guias_vigentes: number;
    cobrado_hoy: number;
    cobrado_mes: number;
}

/** Un punto de la recaudación de los últimos 12 meses. */
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
    /** En grupos de cuatro, con guion: «EFGT-96R4-CJ42-AHYJ». */
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

/** Lo que vence pronto o ya venció y todavía pide una acción. */
export interface Avisos {
    /** Con cuántos días de anticipación se avisa. Lo fija el servidor. */
    dias_aviso: number;
    carnets_por_vencer: number;
    autorizaciones_por_vencer: number;
    /** Los listados ya filtrados; los arma el servidor con los enums. */
    urls: { carnets: string | null; autorizaciones: string | null };
}

/** La pesca de las autorizaciones vigentes. Solo la guía descuenta: vendido = autorizado − disponible. */
export interface Pesca {
    autorizaciones: number;
    autorizado: number;
    disponible: number;
    vendido: number;
}

/** Lo cobrado en la gestión por trámite. */
export interface CobroPorConcepto {
    clave: 'aprovechamientos' | 'carnets' | 'faenas' | 'guias';
    etiqueta: string;
    total: number;
    cantidad: number;
}

/** Una autorización vigente con el 20 % o menos disponible. */
export interface CasiAgotada {
    id: number;
    beneficiario: string | null;
    foto: string | null;
    disponible: number;
    total: number;
}

/** Un pescador por lo que vendió con su autorización vigente. */
export interface TopPescador {
    id: number;
    beneficiario_id: number;
    beneficiario: string | null;
    foto: string | null;
    vendido: number;
    total: number;
}

export interface Destino {
    destino: string;
    kilos: number;
    guias: number;
}

/** Un recibo emitido: un trámite que SIREB dio por pagado y Jichi aprobó. */
export interface Actividad {
    id: number;
    tipo: CobroPorConcepto['clave'] | null;
    documento_id: number;
    concepto: string | null;
    beneficiario: string | null;
    monto: number;
    /** Un MOMENTO. */
    cuando: string | null;
}

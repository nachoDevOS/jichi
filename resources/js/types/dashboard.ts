import type { EstadoCarnet, TipoActor } from '@/types';

/**
 * Tipos del panel principal.
 *
 * Cada uno describe, campo por campo, lo que arma
 * App\Http\Controllers\Panel\DashboardController. Si allá se agrega o se
 * renombra una clave, hay que reflejarlo acá: TypeScript avisa en el editor y
 * no hay que esperar a que reviente en el navegador.
 *
 * CONVENCIÓN DEL PROYECTO
 *   - tipos compartidos por todo el sistema  -> types/index.d.ts
 *   - tipos de un módulo concreto            -> types/<modulo>.ts  (este archivo)
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
 *
 * Es lo que dibujan las líneas chicas al pie de los indicadores. Cada fila trae
 * los DOS valores del día porque las dos series salen del mismo recorrido en
 * PHP: separarlas obligaría a mandar el calendario dos veces.
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
 *
 * Sale del enum y no de la base para que los dos aparezcan aunque uno esté en
 * cero: un valor que no vuelve en la consulta haría que el bloque mienta por
 * omisión.
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
 *
 * Las dos últimas cifras no son avisos de vencimiento sino de TRABAJO SIN
 * CERRAR: una faena o una guía que se pasó de fecha y sigue activa es un papel
 * que alguien se llevó y del que nadie registró la vuelta.
 */
export interface Avisos {
    gestion: number;
    /** Con cuántos días de anticipación se avisa. Lo fija el servidor. */
    dias_aviso: number;
    carnets_por_vencer: number;
    cupos_por_vencer: number;
    /** Sin kilos, aunque la fecha no haya llegado: se resuelve con una ampliación. */
    cupos_agotados: number;
    faenas_vencidas: number;
    guias_vencidas: number;
}

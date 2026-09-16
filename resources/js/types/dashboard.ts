import type { EstadoTramite, TipoTramite } from '@/types';

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
    tramites_hoy: number;
    /**
     * Pendientes MÁS en revisión, de todas las gestiones. Un expediente que
     * alguien tomó para revisar sigue sin resolverse, y un expediente viejo sin
     * resolver sigue siendo trabajo.
     */
    tramites_abiertos: number;
    /** De esos, cuántos ya tiene alguien en la mano. */
    tramites_en_revision: number;
    /** De los abiertos, los que ya están cobrados y solo esperan la firma. */
    listos_para_aprobar: number;
    carnets_gestion: number;
    carnets_vigentes: number;
    recaudado_hoy: number;
    recaudado_mes: number;
}

/**
 * Una jornada de la serie de los últimos catorce días.
 *
 * Es lo que dibujan las líneas chicas al pie de los cuatro indicadores. Cada
 * fila trae los DOS valores del día porque las dos series salen del mismo
 * recorrido en PHP: separarlas obligaría a mandar el calendario dos veces.
 */
export interface ActividadDia {
    /** Clave ordenable: '2026-09-16'. */
    dia: string;
    /** Lo que se muestra al pasar el mouse: '16 sep.'. */
    etiqueta: string;
    tramites: number;
    recaudado: number;
}

/** Una barra del gráfico de carnets por rubro. */
export interface CarnetsPorRubro {
    rubro: string;
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

/**
 * Emisiones iniciales contra adiciones de rubro.
 *
 * Es el número que muestra cómo está funcionando la Regla A: cuánta gente saca
 * carnet por primera vez este año y cuánta ya lo tenía.
 */
export interface TramitesPorTipo {
    tipo: TipoTramite;
    etiqueta: string;
    color: string;
    cantidad: number;
}

/** Una fila de la tabla de últimos expedientes. */
export interface UltimoTramite {
    id: number;
    carnet_registro: string | null;
    beneficiario: string | null;
    rubro: string | null;
    tipo_etiqueta: string;
    tipo_color: string;
    estado: EstadoTramite;
    estado_etiqueta: string;
    estado_color: string;
    monto_requerido: number;
    saldo_pendiente: number;
    fecha_solicitud: string | null;
}

/**
 * El aviso de fin de gestión.
 *
 * TODOS los carnets vigentes vencen el mismo día —el 31 de diciembre—, así que
 * esto no es una lista de casos sueltos como en un sistema de vencimientos
 * escalonados: es cuánta gente va a tener que renovar de golpe, y con cuánto
 * tiempo.
 */
export interface AvisoCierreGestion {
    fecha_vencimiento: string;
    dias_restantes: number;
    cantidad: number;
    /** Aprobados cuyo carnet todavía no se imprimió: trabajo de ventanilla. */
    sin_imprimir: number;
    /** Impresos y sin entregar: carnets esperando en el cajón. */
    sin_entregar: number;
}

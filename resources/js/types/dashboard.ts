/**
 * Tipos del panel principal.
 *
 * Cada uno describe, campo por campo, lo que arma
 * App\Http\Controllers\Panel\DashboardController. Si allá se agrega o se
 * renombra una clave, hay que reflejarlo acá: TypeScript avisa en el editor
 * y no hay que esperar a que reviente en el navegador.
 *
 * CONVENCIÓN DEL PROYECTO
 *   - tipos compartidos por todo el sistema  -> types/index.d.ts
 *   - tipos de un módulo concreto            -> types/<modulo>.ts  (este archivo)
 */

/** Números grandes del encabezado: lo del día y lo del mes. */
export interface ResumenDelDia {
    fecha: string;
    tramites_recibidos: number;
    tramites_pendientes: number;
    documentos_emitidos: number;
    recaudado_hoy: number;
    recaudado_mes: number;
    solicitantes_atendidos: number;
}

/** Una barra del gráfico de recaudación por área departamental. */
export interface RecaudacionArea {
    area: string;
    icono: string | null;
    color: string | null;
    total: number;
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

/** Cantidad de documentos emitidos este año, por categoría. */
export interface DocumentosPorTipo {
    tipo: string;
    etiqueta: string;
    cantidad: number;
}

/** Un documento que está por vencer dentro del plazo de alerta. */
export interface DocumentoPorVencer {
    id: number;
    codigo_verificacion: string;
    solicitante: string;
    tipo: string;
    fecha_vencimiento: string | null;
    /** Negativo si ya venció. */
    dias_para_vencer: number | null;
}

/** Todo lo que necesita atención del operador. */
export interface AlertasOperativas {
    /** Cuántos días de anticipación se avisan. Sale de `configuraciones`. */
    dias_alerta: number;
    documentos_por_vencer: DocumentoPorVencer[];
    documentos_vencidos: number;
    tramites_sin_pago: number;
    pendientes_aprobacion: number;
}

/** Una fila de la tabla de últimos trámites registrados. */
export interface UltimoTramite {
    id: number;
    codigo: string;
    solicitante: string;
    ci_nit: string;
    tipo: string;
    area: string;
    icono: string | null;
    estado: string;
    estado_etiqueta: string;
    /** Nombre del color que devuelve EstadoTramite::color() en PHP. */
    estado_color: string;
    monto_total: number;
    creado: string | null;
}

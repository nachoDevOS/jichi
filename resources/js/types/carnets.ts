import type { EstadoCarnet, EstadoHabilitacion, EstadoTramite } from '@/types';

/**
 * Tipos del módulo Carnets.
 *
 * Lo arma App\Http\Controllers\Panel\CarnetController.
 */

/** Una fila del listado de carnets emitidos. */
export interface CarnetFila {
    id: number;
    /** El número impreso en el carnet: 000013. */
    registro: string;
    gestion: number;
    beneficiario_id: number | null;
    beneficiario: string | null;
    documento_identidad: string | null;
    estado: EstadoCarnet;
    estado_etiqueta: string;
    estado_color: string;
    /** Calculado contra la fecha, no leído del estado. */
    vigente: boolean;
    rubros_count: number;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
}

/**
 * La ficha del carnet.
 *
 * Trae las DOS cosas, y cumplen papeles distintos:
 *
 *   REGISTRO  el número corto impreso en el plástico. Es público, no abre nada,
 *             y sirve para nombrar el carnet en ventanilla.
 *   FIRMA     el secreto que abre la verificación pública. NO se imprime: viaja
 *             solo dentro del QR.
 *
 * La firma se muestra en ESTA pantalla y en ninguna otra del panel, por un
 * motivo concreto: si el QR de alguien queda ilegible, esta es la única forma
 * de recuperarla y dictársela para que pueda verificar su carnet.
 */
export interface CarnetFicha {
    id: number;
    gestion: number;
    estado: EstadoCarnet;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    admite_adiciones: boolean;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
    /** El número impreso en el carnet: 000013. */
    registro: string;
    /** En grupos de cuatro. Solo para dictarla cuando el QR no se puede leer. */
    firma: string;
    /** La dirección completa que se codifica en el QR impreso. */
    url_verificacion: string;
    /**
     * Si el plástico se puede sacar. Lo decide Carnet::puedeImprimirse() y no
     * esta pantalla: hace falta que el carnet no esté anulado y que tenga al
     * menos un rubro habilitado, que es algo que nace recién al APROBAR.
     */
    puede_imprimirse: boolean;
}

/** El titular, tal como lo muestra la ficha del carnet. */
export interface TitularCarnet {
    id: number | null;
    nombreCompleto: string | null;
    documento_identidad: string | null;
    foto_url: string | null;
    fechaNacimiento: string | null;
}

/**
 * Un rubro habilitado en el carnet: la fila de `carnet_rubro`.
 *
 * La fila NO se borra al suspender. Así se conserva el dato de que la actividad
 * estuvo autorizada hasta tal fecha, que es lo que un inspector necesita saber
 * al revisar una infracción del mes pasado.
 */
export interface Habilitacion {
    id: number;
    rubro: string | null;
    descripcion: string | null;
    estado: EstadoHabilitacion;
    estado_etiqueta: string;
    estado_color: string;
    fecha_habilitacion: string | null;

    /**
     * Cupo autorizado para ESTE rubro, en kilos. Copia de la del trámite que lo
     * habilitó. No sale impreso en el plástico; se consulta desde el panel.
     *
     * `null` es «sin definir», distinto de cero.
     */
    capacidad_kg: number | null;
}

/** Un expediente del carnet, en la pestaña de historial. */
export interface TramiteDelCarnet {
    id: number;
    rubro: string | null;
    tipo_etiqueta: string;
    estado: EstadoTramite;
    estado_etiqueta: string;
    estado_color: string;
    monto_requerido: number;
    fecha_solicitud: string | null;
}

import type { EstadoCarnet, EstadoTramite } from '@/types';

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
    /** La actividad del carnet. Es lo que distingue dos filas del mismo titular. */
    rubro: string | null;
    /** El cupo ya escrito como va impreso: «600 KG». Null si no se cargó. */
    capacidad: string | null;
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
    /** Si se le puede presentar un trámite de actualización. */
    admite_tramites: boolean;

    /**
     * LA ACTIVIDAD DEL CARNET Y SU CUPO.
     *
     * Ocupan el lugar que tenía la lista `habilitaciones`: con un carnet por
     * rubro no hay lista que mostrar, hay UN rubro y UN cupo, y los dos van
     * impresos en el plástico.
     */
    rubro: string | null;
    rubro_descripcion: string | null;
    /** En kilos, para mostrar o comparar. `null` es «sin definir», no cero. */
    capacidad_kg: number | null;
    /** El mismo dato ya escrito como va impreso: «600 KG». */
    capacidad: string | null;
    /**
     * Si esta actividad se autoriza por volumen.
     *
     * La ficha esconde el bloque del cupo cuando es `false`: mostrar «Cupo
     * autorizado: sin definir» en un carnet de Comercializador no informa nada
     * y sugiere que falta cargar un dato que no existe.
     */
    requiere_capacidad: boolean;

    /**
     * QUÉ PERMISO OPERATIVO PUEDE EMITIR ESTE CARNET.
     *
     * Lo decide el servidor —Carnet::puedeEmitirFaenas()— e incluye la vigencia,
     * así que un carnet suspendido devuelve `false` y el botón no aparece.
     */
    puede_emitir_faenas: boolean;
    puede_emitir_guias: boolean;

    /**
     * Si el RUBRO emite ese papel, sin mirar la vigencia.
     *
     * Es lo que decide si la sección se muestra: un carnet vencido ya no puede
     * emitir, pero sigue teniendo que mostrar lo que emitió en su momento.
     */
    emite_faenas: boolean;
    emite_guias: boolean;

    /** El total, que puede ser mayor que las 10 filas que trae la ficha. */
    total_faenas: number;
    total_guias: number;

    /** Si el botón de suspender corresponde: solo desde vigente. */
    puede_suspenderse: boolean;
    /** Si corresponde el de levantar la suspensión: solo desde suspendido. */
    puede_rehabilitarse: boolean;

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
     * menos un trámite APROBADO — el carnet nace con el trámite pendiente, y
     * hasta que alguien lo firme no autoriza a nada.
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

/** Una faena del carnet, en la ficha. Las últimas 10. */
export interface FaenaDelCarnet {
    id: number;
    nro_permiso: string;
    estado_etiqueta: string;
    estado_color: string;
    embarcacion: string | null;
    fecha_salida: string | null;
    fecha_desembarque: string | null;
    /** Ya escrito como va en el papel: «450 KG». */
    cantidad: string | null;
}

/** Una guía del carnet, en la ficha. Las últimas 10. */
export interface GuiaDelCarnet {
    id: number;
    nro_guia: string;
    estado_etiqueta: string;
    estado_color: string;
    transporte_etiqueta: string;
    destino_lugar: string | null;
    total_kg: number;
    fecha: string | null;
}

import type { EstadoCarnet, TipoActor } from '@/types';

/**
 * Tipos del módulo Carnets — la credencial anual.
 */

/** Un carnet, tal como lo pintan el listado y la ficha. */
export interface CarnetFila {
    id: number;
    beneficiario_id: number;
    beneficiario: string | null;

    /** En grupos de cuatro: «PES2 6K7R J2M». Se guarda sin separadores. */
    codigo: string;

    /**
     * El número del libro, «00001»: correlativo por gestión y compartido entre
     * las dos actividades. Null hasta que el carnet se aprueba.
     */
    registro: string | null;
    gestion: number | null;

    /** El nombre del catálogo: «Carnet de Pescador». Es texto, no una regla. */
    tipo: string | null;
    /** La regla: de acá cuelga qué puede emitir y si lleva cupo. */
    tipo_actor: TipoActor;
    tipo_actor_etiqueta: string;
    tipo_actor_color: string;
    /** El nombre completo del gremio. La sigla va aparte: es para lo angosto. */
    asociacion: string | null;
    asociacion_sigla: string | null;

    /** Van juntos en la celda del nombre, como en el listado de cupos. */
    foto_url: string | null;
    documento: string | null;

    /** Los kilos impresos en el carnet. Null en un comercializador. */
    cupo_kg: number | null;

    /** Cuándo se cargó la fila. Un MOMENTO: se muestra con fechaHora() y hace(). */
    registrado_en: string | null;

    /** El recibo del trámite. Existe desde el ENVÍO; null mientras es borrador. */
    recibo_id: number | null;
    recibo_numero: string | null;

    estado: EstadoCarnet;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    /** Si se le pueden cargar depósitos hoy. Solo mientras está pendiente. */
    admite_pagos: boolean;
    /** Corregir y eliminar: solo sobre el borrador, y sin plata cargada. */
    puede_editarse: boolean;
    puede_eliminarse: boolean;
    /** Si pasó por la firma. Es lo que habilita imprimir el carnet. */
    ya_fue_aprobado: boolean;
    /** Negativo si ya venció. Null si no tiene fecha. */
    dias_para_vencer: number | null;

    puede_emitir_faenas: boolean;
    puede_emitir_guias: boolean;
    /** Por qué no habilita todavía. `null` cuando sí habilita. */
    motivo_sin_permisos: string | null;

    monto: number;
    saldo_pendiente: number;
    pagado: boolean;

    /** Un DÍA, no un instante: llega como 'AAAA-MM-DD' y se muestra con fecha(). */
    /** El día que se pidió. La emisión la escribe la aprobación. */
    fecha_solicitud: string | null;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
}

/** El carnet con el detalle que solo pinta la ficha. */
export interface CarnetFicha extends CarnetFila {
    /**
     * LAS DEL CIRCUITO DE REVISIÓN, resueltas en el servidor. React no vuelve
     * a evaluar el estado: pregunta por estas.
     */
    puede_enviarse: boolean;
    puede_revisarse: boolean;
    puede_aprobarse: boolean;
    /** Cuántas boletas quedan sin controlar. Bloquean la aprobación. */
    pagos_sin_validar: number;

    /** Los dos respaldos de la emisión, listos para abrir. */
    archivo_ci_url: string | null;
    archivo_asociacion_url: string | null;

    documento_identidad: string | null;
    ciudad: string | null;
    provincia: string | null;
    /** El nombre completo de la asociación; en la tira del carnet va la sigla. */
    asociacion_nombre: string | null;

    /**
     * La bolsa madre que respalda el cupo impreso. Null en un comercializador.
     */
    cupo: {
        id: number;
        escala: number | null;
        descripcion: string | null;
        volumen_total_kg: number;
        kilos_consumidos: number;
        saldo_kg: number;
        porcentaje_usado: number;
        vigente: boolean;
        estado_etiqueta: string;
        estado_color: string;
        /** Sin firma no hay saldo que mostrar: es un volumen pedido. */
        ya_fue_aprobado: boolean;
        fecha_solicitud: string | null;
        fecha_emision: string | null;
        fecha_vencimiento: string | null;
    } | null;
}

/** El carnet tal como lo abre el formulario de CORRECCIÓN. */
export interface CarnetEnCorreccion {
    id: number;
    codigo: string;

    /** Solo para mostrar: el titular y la actividad no se corrigen. */
    beneficiario: string | null;
    documento: string | null;
    foto_url: string | null;
    tipo_actor: TipoActor;
    tipo_actor_etiqueta: string;

    asociacion_id: number;
    tipo_carnet_id: number;
    aprovechamiento_id: number | null;
    fecha_solicitud: string | null;

    /** Lo ya adjuntado, para poder mirarlo antes de reemplazarlo. */
    archivo_ci_url: string | null;
    archivo_asociacion_url: string | null;

    cupos_elegibles: CupoVigente[];
}

/** Una asociación elegible en el formulario de emisión. */
export interface AsociacionElegible {
    id: number;
    nombre: string;
    sigla: string | null;
}

/** Un tipo de carnet elegible, con su arancel. */
export interface TipoElegible {
    id: number;
    nombre: string;
    /** Con qué actividad es coherente: la lista se filtra con esto. */
    tipo_actor: TipoActor;
    precio_bs: number;
}

/**
 * El cupo vigente de la persona elegida, si lo tiene.
 */
export interface CupoVigente {
    id: number;
    /** El tramo: «3». */
    escala: number | null;
    /** El texto de la resolución: «201 Kg Hasta 300 Kg». */
    descripcion: string | null;
    volumen_total_kg: number;
    saldo_kg: number;
    fecha_vencimiento: string | null;
    estado_etiqueta: string;
    estado_color: string;
    /** Si además ya autoriza a pescar. Un pendiente respalda el carnet igual. */
    habilita_faenas: boolean;
}

/** Lo que el formulario de emisión manda de vuelta. */
export interface FormularioCarnet {
    beneficiario_id: number | null;
    asociacion_id: number | string;
    tipo_carnet_id: number | string;
    /**
     * La bolsa madre que respalda el carnet. Se manda EXPLÍCITA para que quede
     * dicho de cuál cuelga; en un comercializador va en null.
     */
    aprovechamiento_id: number | null;
    fecha_solicitud: string;
    /** Los dos respaldos. Suben con el formulario: el post va con FormData. */
    archivo_ci: File | null;
    archivo_asociacion: File | null;
}

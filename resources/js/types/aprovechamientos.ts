import type { EstadoAprovechamiento, EstadoFaena, ModalidadAprovechamiento } from '@/types';

/**
 * Tipos del módulo Aprovechamientos — la BOLSA MADRE del pescador.
 */

/** Un cupo, tal como lo pintan el listado y la ficha. */
export interface CupoFila {
    id: number;
    beneficiario_id: number;
    beneficiario: string | null;
    documento: string | null;
    /** Puede faltar: mucha gente del padrón todavía no tiene foto cargada. */
    foto_url: string | null;

    /** El tramo de la escala bajo el que se otorgó: 1 a 7. */
    escala: number | null;
    /** El texto literal de la resolución: «201 Kg Hasta 500 Kg». */
    descripcion: string | null;

    /**
     * Los kilos OTORGADOS, copiados del techo del tramo al otorgar.
     */
    volumen_total_kg: number;

    /** Lo que el pescador declaró que navega: «canoa», «peque-peque», «bote». */
    tipo_embarcacion: string;

    /** Lo comprometido por las faenas que consumen cupo (todas menos las vencidas). */
    kilos_consumidos: number;
    saldo_kg: number;
    porcentaje_usado: number;

    /**
     * El régimen, COPIADO del tramo al otorgar.
     *
     * No se lee de la escala en vivo a propósito: reclasificar un tramo en el
     * catálogo no puede cambiarle la clasificación a un cupo ya otorgado.
     */
    modalidad: ModalidadAprovechamiento;
    modalidad_etiqueta: string;
    modalidad_color: string;

    /** Cuándo se cargó la fila. Un MOMENTO: se muestra con fechaHora() y hace(). */
    registrado_en: string | null;

    estado: EstadoAprovechamiento;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;

    /**
     * Los kilos que se PASARON del volumen otorgado.
     */
    kilos_excedidos: number;
    excedido: boolean;
    /** Vigente, con saldo y sin agotar: las tres condiciones juntas. */
    puede_emitir_faena: boolean;
    /** Por qué no puede emitir faenas. `null` cuando sí puede. */
    motivo_sin_faena: string | null;
    /**
     * Si todavía se puede corregir, y si se puede borrar la fila entera.
     */
    puede_editarse: boolean;
    puede_eliminarse: boolean;

    /**
     * Las tres del circuito de revisión, resueltas en el servidor.
     */
    admite_pagos: boolean;
    puede_enviarse: boolean;
    puede_revisarse: boolean;
    /** Si pasó por la firma: es lo que habilita la autorización en papel. */
    ya_fue_aprobado: boolean;

    /** El recibo del trámite. Existe desde el ENVÍO; null mientras es borrador. */
    recibo_id: number | null;
    recibo_numero: string | null;

    monto: number;
    saldo_pendiente: number;
    pagado: boolean;

    /** Un DÍA, no un instante: llega como 'AAAA-MM-DD' y se muestra con fecha(). */
    fecha_solicitud: string | null;
    /** El día que lo firmaron. `null` mientras el cupo no esté aprobado. */
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
}

/** El cupo con el detalle que solo pinta la ficha. */
export interface CupoFicha extends CupoFila {
    /** El código de verificación, en grupos de cuatro. */
    codigo: string | null;
    escala_descripcion: string | null;
    /** [piso, techo] del tramo, para contrastarlo con lo otorgado. */
    escala_rango: [number, number] | null;

    /**
     * Cuántas boletas quedan sin dar por buenas —sin validar u observadas—.
     */
    pagos_sin_validar: number;
    puede_aprobarse: boolean;
}

/**
 * Un depósito que pagó este cupo, en la ficha.
 */
export interface PagoDelCupo {
    id: number;
    monto_parcial: number;
    nro_transaccion: string;
    comprobante_url: string | null;
    /** Un DÍA —lo que dice la boleta—: se muestra con fecha(). */
    fecha_deposito: string | null;
    /** Un MOMENTO —cuándo entró la plata—: se muestra con fechaHora(). */
    cobrado_en: string | null;

    /**
     *  EL CONTROL DE LA BOLETA, QUE NO ES EL ESTADO DEL PAGO
     */
    estado_validacion: 'pendiente' | 'validado' | 'observado';
    estado_validacion_etiqueta: string;
    estado_validacion_color: string;
    /** Qué se le objetó. Es lo único que le dice a ventanilla qué corregir. */
    observacion: string | null;

    /** Quién lo cargó y quién lo controló: dos personas, dos columnas. */
    registrado_por: string | null;
    validado_por: string | null;
    /** Un MOMENTO —cuándo se miró la boleta—: se muestra con fechaHora(). */
    validado_en: string | null;

    /**
     * Las dos llegan RESUELTAS del servidor, y no se recalculan acá: dependen
     * del estado del TRÁMITE además del estado del pago —controlar solo corre
     * en revisión— y una copia de esa regla en la pantalla se queda vieja sola.
     */
    puede_validarse: boolean;
    puede_corregirse: boolean;
}

/**
 * EL RECIBO DEL TRÁMITE: uno solo, con todos los depósitos adentro.
 */
export interface ReciboDelCupo {
    id: number;
    numero_recibo: string;
    monto_total: number;
    /** Un MOMENTO —cuándo se emitió—: se muestra con fechaHora(). */
    emitido_en: string | null;
    /** Cuántos depósitos ampara. El papel es UNO por trámite. */
    pagos_count?: number;
}

/** Una cédula que se apoya en este cupo, en la ficha. */
export interface CarnetDelCupo {
    id: number;
    codigo: string;
    /** El número del libro, «00001». Null mientras no esté aprobado. */
    registro: string | null;
    tipo: string | null;
    tipo_actor_etiqueta: string;
    tipo_actor_color: string;
    estado_etiqueta: string;
    estado_color: string;
    ya_fue_aprobado: boolean;
    /** Firmado y no revocado: el mismo corte que la impresión. */
    puede_imprimirse: boolean;
    /** Solo el aprobado: reponer empieza por revocarlo. */
    puede_reponerse: boolean;
    fecha_solicitud: string | null;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
}

/** Una faena colgada del cupo, en la ficha. */
export interface FaenaDelCupo {
    id: number;
    numero_faena: number;
    /** Con los seis ceros del talonario: «000001». Lo arma el servidor. */
    numero_legible: string;
    /** De qué carnet cuelga: un cupo puede respaldar más de uno. */
    carnet_id: number | null;
    carnet_registro: string | null;
    kilos_extraidos: number;
    estado: EstadoFaena;
    estado_etiqueta: string;
    estado_color: string;
    /**
     * Si sus kilos pesan contra el saldo.
     */
    consume_cupo: boolean;
    /** Solo la que pasó por la firma. */
    puede_imprimirse: boolean;
    fecha_salida: string | null;
    fecha_desembarque: string | null;
}

/** Un tramo elegible en el formulario de otorgamiento. */
export interface TramoElegible {
    id: number;
    nro_escala: number;
    descripcion_kg: string;
    kilos_min: number;
    /**
     * El techo del rango, que es EL VOLUMEN QUE SE VA A OTORGAR.
     */
    kilos_max: number;
    valor_bs: number;
    /** El régimen del tramo, visible antes de otorgar. */
    modalidad: ModalidadAprovechamiento;
    modalidad_etiqueta: string;
    modalidad_descripcion: string;
}

/** Lo que el formulario de otorgamiento manda de vuelta. */
export interface FormularioCupo {
    beneficiario_id: number | null;
    categoria_aprov_id: number | string;
    fecha_solicitud: string;
    /** Opcional: el renglón del talonario tampoco es obligatorio. */
    tipo_embarcacion: string;
}

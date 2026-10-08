import type { EstadoAprovechamiento, EstadoFaena, ModalidadAprovechamiento } from '@/types';

/**
 * Tipos del módulo Aprovechamientos — la BOLSA MADRE del pescador.
 */

/** Un cupo, tal como lo pintan el listado y la ficha. */
export interface CupoFila {
    id: number;
    /** El N° de la autorización impresa, «000001». */
    numero: string;
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
    /** Apartado por faenas pendientes o en revisión: no descuenta, pero no está libre. */
    kilos_reservados: number;
    /** Lo que una faena nueva puede pedir: saldo menos reservado. */
    libre_kg: number;
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

    /** Si pasó por la firma. Revocado también cuenta: se firmó, después se dio de baja. */
    ya_fue_aprobado: boolean;
    /** Firmada y no revocada: el mismo corte que la impresión del carnet. */
    puede_imprimirse: boolean;
    /** Aprobada o agotada: lo único que se revoca. */
    puede_revocarse: boolean;

    /** El recibo. Existe desde que SIREB confirmó el pago; null mientras está pendiente. */
    recibo_id: number | null;
    recibo_numero: string | null;

    monto: number;
    /** El cobro está en SIREB: su liquidación, y si se puede preguntar por el pago. */
    sireb: VentaSireb | null;
    puede_verificar_pago: boolean;
    /** Pendiente, con la liquidación registrada y sin pago cargado. SIREB lo confirma al cargar. */
    puede_cargar_pago: boolean;

    /** Un DÍA, no un instante: llega como 'AAAA-MM-DD' y se muestra con fecha(). */
    fecha_solicitud: string | null;
    /** El día que lo firmaron. `null` mientras el cupo no esté aprobado. */
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
}

/** La liquidación del documento en SIREB, donde se paga. La comparten los cuatro documentos. */
export interface VentaSireb {
    estado: 'por_enviar' | 'registrada' | 'anulada';
    estado_etiqueta: string;
    estado_color: string;
    /** Con el que se consulta y paga en SIREB. Null hasta que SIREB responde. */
    codigo_publico: string | null;
    /** La boleta que informó SIREB en la última verificación. Sin imagen: SIREB no la expone. */
    pago: PagoSireb | null;
    /** false = todavía no se verificó: sin pago no se sabe si se cargó. */
    pago_consultado: boolean;
    /** La última verificación la dio por perdida: ya no se paga. */
    caida: 'vencida' | 'anulada' | null;
    /** Vencida o anulada: se ofrece «Generar nueva liquidación». */
    puede_renovar: boolean;
    /** Las liquidaciones ya cerradas, de la más vieja a la más nueva. */
    historial: LiquidacionHistorial[];
}

/** Lo que cobraría una nueva liquidación con la tarifa vigente. Prop `cotizacion_renovacion` de las fichas. */
export interface CotizacionRenovacion {
    /** Null si no se puede cobrar hoy: el motivo dice por qué. */
    monto: number | null;
    anterior: number;
    motivo: string | null;
}

/** Una liquidación pedida a SIREB, tal como quedó en `sireb_historial`. */
export interface LiquidacionHistorial {
    liquidacion_id: string;
    codigo_publico: string | null;
    items: { tarifa_id: string; cantidad: number; precio: number; producto?: string }[];
    monto: number;
    /** Instantes: se muestran con fechaHora(). */
    solicitada_en: string;
    vence_en: string | null;
    estado: 'registrada' | 'vencida' | 'anulada' | 'pagada';
    cerrada_en: string | null;
    motivo: string | null;
}

export interface PagoSireb {
    estado: 'pendiente' | 'confirmado' | null;
    monto_pagado: number;
    numero_boleta: string | null;
    entidad_bancaria: string | null;
    /** Un DÍA: se muestra con fecha(). */
    fecha_pago: string | null;
    /** Un instante: se muestra con fechaHora(). */
    fecha_validacion: string | null;
}

/** El cupo con el detalle que solo pinta la ficha. */
export interface CupoFicha extends CupoFila {
    /** El código de verificación, en grupos de cuatro. */
    codigo: string | null;
    escala_descripcion: string | null;
    /** [piso, techo] del tramo, para contrastarlo con lo otorgado. */
    escala_rango: [number, number] | null;

    /** Firmado y en fecha: puede respaldar una cédula nueva. */
    puede_emitir_carnet: boolean;
}

/** El recibo de un documento: uno, emitido cuando SIREB confirmó el pago. Lo comparten los cuatro. */
export interface ReciboDelCupo {
    id: number;
    numero_recibo: string;
    monto_total: number;
    /** La boleta tal como la validó SIREB. */
    numero_boleta: string | null;
    entidad_bancaria: string | null;
    /** Un DÍA, el de la boleta: se muestra con fecha(). */
    fecha_pago: string | null;
    /** Un MOMENTO —cuándo se emitió—: se muestra con fechaHora(). */
    emitido_en: string | null;
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
    nro: number;
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
    servicio_sireb: string;
    tarifa_sireb: string;
    /** Precio de referencia de SIREB; null si no respondió. El que vale se congela al guardar. */
    monto: number | null;
    /** false = tarifa inactiva en SIREB: se muestra deshabilitada. null = SIREB no respondió. */
    liquidable: boolean | null;
    /** El régimen del tramo, visible antes de otorgar. */
    modalidad: ModalidadAprovechamiento;
    modalidad_etiqueta: string;
    modalidad_descripcion: string;
}


import type { EstadoGuia } from '@/types';

/**
 * Tipos del módulo Guías — el amparo de UN traslado de producto.
 */

/** Una opción de enum tal como la manda el servidor. */
export interface OpcionGuia {
    value: string;
    label: string;
}

/** Las diez columnas de tilde del cuadro D. */
export interface OpcionCondicion extends OpcionGuia {
    /** Lo que va adentro del grupo: «Entero» bajo «Congelado». */
    corta: string;
    /** El encabezado de grupo del papel. Vacío en las columnas sueltas. */
    grupo: string;
}

/** Un renglón del cuadro D, como lo devuelve el servidor. */
export interface DetalleGuia {
    id: number;
    producto_id: number;
    /** Copia del nombre del producto al emitir. */
    especie: string;
    condicion: string;
    condicion_etiqueta: string;
    cantidad_kg: number;
    precio_kg: number;
    importe_total: number;
}

/** Un renglón del cuadro D mientras se está escribiendo. */
export interface FilaDetalle {
    /** `key` estable de React: sin ella, quitar la del medio remonta las de abajo. */
    key: string;
    /** Del catálogo: nombre y precio los pone el servidor. */
    producto_id: string;
    condicion: string;
    cantidad_kg: string;
}

/** Un producto elegible en el cuadro D. */
export interface ProductoGuia {
    id: number;
    nombre: string;
    precio_kg: number;
    /** false: fuera de uso, solo aparece porque la guía ya lo tenía. */
    estado: boolean;
}

/** Los renglones del papel, compartidos por el formulario y la ficha. */
export interface RenglonesGuia {
    origen: string;
    origen_departamento: string | null;
    origen_provincia: string | null;
    origen_distrito: string | null;

    destino: string;
    destino_departamento: string | null;
    destino_provincia: string | null;
    destino_distrito: string | null;

    medio_transporte: string | null;
    medio_transporte_etiqueta: string | null;
    tipo_transporte: string | null;
    tipo_transporte_etiqueta: string | null;
    transporte_nombre: string | null;
    transporte_placa: string | null;
    transporte_capacidad_kg: number | null;

    /** Marcado, el arancel se cobra al 50%: el criadero no saca del río. */
    es_piscicultura: boolean;
    observaciones: string | null;
}

/** Una guía, tal como la pintan el listado y la ficha. */
export interface GuiaFila extends RenglonesGuia {
    id: number;
    numero_guia: number;
    /** Con los seis ceros del talonario: «000308». */
    numero_legible: string;
    /** «Guía N° 000308», armado por el servidor. */
    etiqueta: string;

    carnet_id: number | null;
    carnet_codigo: string | null;
    carnet_registro: string | null;
    beneficiario_id: number | null;
    comercializador: string | null;
    documento: string | null;
    foto_url: string | null;
    /** La sigla, copiada del carnet al emitir. */
    asociacion: string | null;

    /** «Trinidad → Santa Cruz», armado por el servidor. */
    ruta: string;
    /** La suma del cuadro D, guardada al emitir. */
    peso_total_kg: number;

    /** Por cuánto se multiplicó el arancel: 1 o 0.5. */
    factor_arancel: number;

    estado: EstadoGuia;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    /** Firmada y fuera de sus 5 días: ya no ampara el traslado. */
    caducada: boolean;
    /** Negativo si ya venció. Null mientras nadie la firmó. */
    horas_restantes: number | null;

    /** Las dos puertas del borrador: PENDIENTE y sin un depósito cargado. */
    puede_editarse: boolean;
    puede_eliminarse: boolean;
    /** Solo una guía FIRMADA se anula: el borrador se elimina y la presentada se rechaza. */
    puede_anularse: boolean;
    /** Ya pasó por la firma: es lo que habilita a imprimir el papel. */
    ya_fue_aprobada: boolean;
    admite_pagos: boolean;
    /** Por qué todavía no ampara. Null cuando sí ampara. */
    motivo_sin_amparar: string | null;

    monto: number;
    saldo_pendiente: number;
    pagado: boolean;

    /** Existe desde el ENVÍO; null mientras es borrador. */
    recibo_id: number | null;
    recibo_numero: string | null;

    /** Un DÍA: se muestra con fecha(). */
    fecha_solicitud: string | null;
    /** ISO 8601 con hora. Se muestra con fechaHora(). */
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
    registrado_en: string | null;
}

/** La guía con el detalle que solo pinta la ficha. */
export interface GuiaFicha extends GuiaFila {
    asociacion_nombre: string | null;
    detalles: DetalleGuia[];

    /** Las del circuito, resueltas en el servidor. */
    puede_enviarse: boolean;
    puede_revisarse: boolean;
    puede_aprobarse: boolean;
    pagos_sin_validar: number;
}

/** La guía como la abre el formulario de corrección. */
export interface GuiaEditable extends RenglonesGuia {
    id: number;
    numero_legible: string;
    beneficiario: string | null;
    documento: string | null;
    foto_url: string | null;
    carnet_codigo: string | null;
    carnet_registro: string | null;
    fecha_solicitud: string | null;
    detalles: DetalleGuia[];
}

/**
 * Los campos sueltos del formulario: todo menos el cuadro D.
 *
 * `CamposGuia` nunca toca `detalles` —eso lo hace `TablaDetalle`— y dejarlo
 * fuera del tipo del setter evita tener que castear en cada `onChange`.
 */
export type CampoGuia = Exclude<keyof FormularioGuia, 'detalles'>;

/** Los catálogos que los dos formularios reciben del servidor. */
export interface CatalogosGuia {
    productos: ProductoGuia[];
    medios: OpcionGuia[];
    tiposTransporte: OpcionGuia[];
    condiciones: OpcionCondicion[];
    diasVigencia: number;
    descuentoPiscicultura: number;
}

/** Lo que el formulario manda de vuelta. */
export interface FormularioGuia {
    origen: string;
    origen_departamento: string;
    origen_provincia: string;
    origen_distrito: string;

    destino: string;
    destino_departamento: string;
    destino_provincia: string;
    destino_distrito: string;

    medio_transporte: string;
    tipo_transporte: string;
    transporte_nombre: string;
    transporte_placa: string;
    transporte_capacidad_kg: string;

    es_piscicultura: boolean;
    observaciones: string;
    fecha_solicitud: string;

    detalles: FilaDetalle[];
}

import type { CarnetDeFaena, EstadoPermiso, PagoDePermiso } from '@/types/faenas';

/**
 * Tipos del módulo Guías — la Guía Única de Transporte.
 *
 * Lo arma App\Http\Controllers\Panel\GuiaController.
 *
 * TRES TIPOS SE REUSAN DE `faenas.ts`: el estado, el carnet de la ficha y el
 * pago. No es pereza — son exactamente los mismos datos, y duplicarlos
 * garantiza que algún día uno de los dos quede viejo. El enum de PHP tomó la
 * misma decisión con `EstadoPermiso`.
 */
export type { CarnetDeFaena, EstadoPermiso, PagoDePermiso };

/** Espejo de App\Enums\TipoTransporte. */
export type TipoTransporte = 'fluvial' | 'aerea' | 'terrestre';

/** Espejo de App\Enums\CondicionProducto. */
export type CondicionProducto = 'fresco' | 'congelado' | 'seco' | 'salado';

/** Una fila del listado de guías. También es la base de la ficha. */
export interface GuiaFila {
    id: number;
    /** El número del talonario de papel. */
    nro_guia: string;
    estado: EstadoPermiso;
    estado_etiqueta: string;
    estado_color: string;
    tipo_transporte: TipoTransporte;
    transporte_etiqueta: string;
    transporte_color: string;
    transporte_nombre: string | null;
    origen_lugar: string | null;
    destino_lugar: string | null;
    /** La suma de los kilos del detalle. No es una columna: se calcula. */
    total_kg: number;
    /**
     * Lo que hay que cobrar, sumando el detalle.
     *
     * NULL EN EL LISTADO, y no es un dato faltante: calcularlo exige recorrer
     * las líneas, y hacerlo por fila sería una consulta por fila. La ficha, que
     * ya carga el detalle, sí lo trae.
     */
    monto_requerido: number | null;
    monto_pagado: number;
    fecha: string | null;
    carnet_id: number | null;
    carnet_registro: string | null;
    beneficiario: string | null;
}

/** Origen o destino, con sus cuatro campos y la versión ya armada para leer. */
export interface UbicacionGuia {
    lugar: string | null;
    depto: string | null;
    provincia: string | null;
    distrito: string | null;
    /** «Trinidad, Cercado, Beni» — sin los vacíos. Lo arma el servidor. */
    completo: string;
}

/** La ficha de una guía. */
export interface GuiaFicha extends GuiaFila {
    nro_recibo: string | null;
    origen: UbicacionGuia;
    destino: UbicacionGuia;
    transporte_placa: string | null;
    /** «Placa» o «Matrícula» según el medio. A una canoa no se le pide placa. */
    rotulo_identificacion: string;
    /** Del VEHÍCULO, no del permiso. Null cuando no se conoce. */
    capacidad_maxima: number | null;
    /** Null cuando no hay capacidad cargada: ahí no hay nada que avisar. */
    excede_capacidad: boolean | null;
    observaciones: string | null;
    vigente: boolean;
    puede_anularse: boolean;
    /** El detalle se corrige mientras la guía valga: el peso sale de la balanza. */
    puede_editar_detalle: boolean;
}

/** Una línea de la carga. */
export interface DetalleGuia {
    id: number;
    especie: string;
    condicion: CondicionProducto;
    condicion_etiqueta: string;
    condicion_color: string;
    cantidad_kg: number;
    precio_unitario: number | null;
    /**
     * La base de cálculo tal como figura en el papel.
     *
     * NO se recalcula contra cantidad × precio: con una rebaja o un redondeo no
     * coinciden, y la que tiene razón es la guía firmada.
     */
    imponible: number | null;
    /** Lo que vale la línea: `imponible` si está, si no la multiplicación. */
    importe: number;
}

/** Una fila de la grilla mientras se edita. Todo texto: sale de <input>. */
export interface FilaDetalleFormulario {
    especie: string;
    condicion: CondicionProducto;
    cantidad_kg: string;
    precio_unitario: string;
    imponible: string;
}

/** Lo que manda el formulario de emisión. */
export interface FormularioGuia {
    carnet_id: number | null;
    nro_guia: string;
    nro_recibo: string;
    origen_lugar: string;
    origen_depto: string;
    origen_provincia: string;
    origen_distrito: string;
    destino_lugar: string;
    destino_depto: string;
    destino_provincia: string;
    destino_distrito: string;
    tipo_transporte: TipoTransporte;
    transporte_nombre: string;
    transporte_placa: string;
    capacidad_maxima: string;
    observaciones: string;
    detalles: FilaDetalleFormulario[];
}

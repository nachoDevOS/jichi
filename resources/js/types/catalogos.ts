import type { EstadoAsociacion, ModalidadAprovechamiento, TipoActor } from '@/types';

/**
 * Tipos de los tres CATÁLOGOS: asociaciones, escala de aprovechamiento y tipos
 * de carnet.
 */

/** Una fila del catálogo de gremios. */
export interface AsociacionFila {
    id: number;
    nombre: string;
    /** Puede faltar: muchas asociaciones chicas no tienen una registrada. */
    sigla: string | null;
    /**
     * La ficha del gremio, guardada en una columna JSON. Llega SIEMPRE con
     * todas las claves de `Asociacion::CAMPOS`; las que no se cargaron, en
     * null.
     */
    datos: FichaAsociacion;
    estado: EstadoAsociacion;
    estado_etiqueta: string;
    estado_color: string;
    /** Carnets emitidos con esta asociación. Por eso no se borra. */
    carnets_count: number;
    /** Guías emitidas con esta asociación. */
    guias_count: number;
}

/**
 * La ficha del gremio. Las claves las fija `Asociacion::CAMPOS` en el
 * servidor, y el formulario las dibuja a partir de los rótulos que manda.
 */
export type FichaAsociacion = Record<string, string | null>;

/** Un tramo de la escala oficial de aprovechamiento. */
export interface EscalaFila extends ReferenciaSireb {
    id: number;
    /** El orden oficial: 1, 2, 3… No tiene tope fijo en código a propósito. */
    nro_escala: number;
    /**
     * El RÉGIMEN del tramo, y de él depende si el cupo se va a poder ampliar.
     */
    modalidad: ModalidadAprovechamiento;
    /**
     * El texto literal de la resolución.
     */
    descripcion_kg: string;
    kilos_min: number;
    kilos_max: number;
    /** Id del servicio en SIREB, compartido por los tramos. */
    servicio_sireb: string;
    /** Id de la tarifa del tramo en SIREB: de ahí sale el precio. */
    tarifa_sireb: string;
    /** false = tramo derogado: desaparece del formulario de cupo. */
    estado: boolean;
    /** Cupos otorgados bajo este tramo. Por eso no se borra. */
    aprovechamientos_count: number;
}

/** Un par servicio/tarifa de SIREB que tuvo un tramo, en la vista de historial. */
export interface TarifaHistorial {
    servicio_sireb: string | null;
    tarifa_sireb: string | null;
    /** Instantes ISO: se muestran con fechaHora(). */
    desde: string | null;
    /** Etiqueta y precio de HOY en SIREB; null si ya no está o SIREB no responde. */
    tarifa_etiqueta: string | null;
    tarifa_monto: number | null;
}

export interface TarifaAnterior extends TarifaHistorial {
    hasta: string | null;
    /** Nombre de quien la cambió; null si ya no existe el usuario. */
    cambiado_por: string | null;
}

/** Lo que la pantalla de edición necesita de un tramo. */
export type TramoFormulario = Pick<
    EscalaFila,
    'id' | 'modalidad' | 'descripcion_kg' | 'kilos_min' | 'kilos_max' | 'servicio_sireb' | 'tarifa_sireb' | 'estado'
>;

/** Un servicio de SIREB, recortado a lo que usa el select de tarifa. */
export interface ServicioSireb {
    id: string;
    codigo: string | null;
    nombre: string;
    /** false = dado de baja en SIREB: no se puede elegir. */
    activo: boolean;
    /** Solo las liquidables: el servidor descarta el resto. */
    tarifas: { id: string; etiqueta: string; monto: number }[];
}

/**
 * Un rango de kilos que no cae en ningún tramo.
 */
export interface HuecoEscala {
    desde: number;
    hasta: number;
}

/** Una fila del catálogo de credenciales. */
export interface TipoCarnetFila extends ReferenciaSireb {
    id: number;
    nombre: string;
    /** Para qué actividad sirve: es lo que tiene que coincidir con el carnet. */
    tipo_actor: TipoActor;
    tipo_actor_etiqueta: string;
    tipo_actor_color: string;
    /** Ids de SIREB, de donde sale el precio; null hasta que alguien elige la tarifa. */
    servicio_sireb: string | null;
    tarifa_sireb: string | null;
    estado: boolean;
    carnets_count: number;
}

/** Lo que la pantalla de edición necesita de un tipo de carnet. */
export type TipoCarnetFormulario = Pick<
    TipoCarnetFila,
    'id' | 'nombre' | 'tipo_actor' | 'servicio_sireb' | 'tarifa_sireb' | 'estado'
>;

/** Una fila del catálogo de productos hidrobiológicos. */
export interface ProductoFila extends ReferenciaSireb {
    id: number;
    nombre: string;
    /** Ids de SIREB, de donde sale el precio por kilo; null hasta que alguien elige la tarifa. */
    servicio_sireb: string | null;
    tarifa_sireb: string | null;
    estado: boolean;
    /** Renglones de guía que lo usan: por eso no hay papelera. */
    detalles_count: number;
}

/** Cómo se llama HOY en SIREB la tarifa de una fila; todo null si no está o SIREB no responde. */
export interface ReferenciaSireb {
    sireb_servicio: string | null;
    sireb_etiqueta: string | null;
    /** Estado de la tarifa tal como lo manda SIREB: `activo` / `inactivo`. */
    sireb_estado: string | null;
    /** Precio de referencia: el que vale lo congela cada documento al emitirse. */
    precio: number | null;
}

/** Una fila de Aranceles de SIREB: un cobro que no cuelga de un catálogo. */
export interface ArancelFila extends ReferenciaSireb {
    id: number;
    concepto: string;
    etiqueta: string;
    descripcion: string;
    servicio_sireb: string | null;
    tarifa_sireb: string | null;
}

/** Lo que la pantalla de alta/edición necesita de un producto. */
export type ProductoFormulario = Pick<ProductoFila, 'id' | 'nombre' | 'servicio_sireb' | 'tarifa_sireb' | 'estado'>;

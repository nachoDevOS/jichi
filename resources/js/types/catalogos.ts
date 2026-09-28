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
export interface EscalaFila {
    id: number;
    /** El orden oficial: 1, 2, 3… No tiene tope fijo en código a propósito. */
    nro_escala: number;
    /**
     * El RÉGIMEN del tramo, y de él depende si el cupo se va a poder ampliar.
     */
    modalidad: ModalidadAprovechamiento;
    modalidad_etiqueta: string;
    modalidad_color: string;
    /**
     * El texto literal de la resolución.
     */
    descripcion_kg: string;
    kilos_min: number;
    kilos_max: number;
    /** Lo que se cobra por ese cupo. */
    valor_bs: number;
    /** false = tramo derogado: desaparece del formulario de cupo. */
    estado: boolean;
    /** Cupos otorgados bajo este tramo. Por eso no se borra. */
    aprovechamientos_count: number;
}

/**
 * Un rango de kilos que no cae en ningún tramo.
 */
export interface HuecoEscala {
    desde: number;
    hasta: number;
}

/** Una fila del catálogo de credenciales. */
export interface TipoCarnetFila {
    id: number;
    nombre: string;
    /** Para qué actividad sirve: es lo que tiene que coincidir con el carnet. */
    tipo_actor: TipoActor;
    tipo_actor_etiqueta: string;
    tipo_actor_color: string;
    /**
     * El arancel de HOY, para armar un cobro nuevo.
     */
    precio_bs: number;
    estado: boolean;
    carnets_count: number;
}

/** Una fila del catálogo de productos hidrobiológicos. */
export interface ProductoFila {
    id: number;
    nombre: string;
    /** La tasa por kilo: la guía cobra kilos × precio de su cuadro D. */
    precio_kg: number;
    estado: boolean;
    /** Renglones de guía que lo usan: por eso no hay papelera. */
    detalles_count: number;
}

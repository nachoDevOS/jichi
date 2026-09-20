import type { EstadoAsociacion, ModalidadAprovechamiento } from '@/types';

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
    estado: EstadoAsociacion;
    estado_etiqueta: string;
    estado_color: string;
    /** Carnets emitidos con esta asociación. Por eso no se borra. */
    carnets_count: number;
    /** Guías emitidas con esta asociación. */
    guias_count: number;
}

/** Lo que el formulario de asociación manda de vuelta. */
export interface FormularioAsociacion {
    nombre: string;
    sigla: string;
    estado: EstadoAsociacion;
}

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

/** Lo que el formulario de escala manda de vuelta. */
export interface FormularioEscala {
    nro_escala: number | string;
    modalidad: ModalidadAprovechamiento;
    descripcion_kg: string;
    kilos_min: number | string;
    kilos_max: number | string;
    valor_bs: number | string;
    estado: boolean;
}

/** Una fila del catálogo de credenciales. */
export interface TipoCarnetFila {
    id: number;
    nombre: string;
    /**
     * El arancel de HOY, para armar un cobro nuevo.
     */
    precio_bs: number;
    estado: boolean;
    carnets_count: number;
}

/** Lo que el formulario de tipo de carnet manda de vuelta. */
export interface FormularioTipoCarnet {
    nombre: string;
    precio_bs: number | string;
    estado: boolean;
}

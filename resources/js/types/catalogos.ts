import type { EstadoAsociacion, ModalidadAprovechamiento } from '@/types';

/**
 * Tipos de los tres CATÁLOGOS: asociaciones, escala de aprovechamiento y tipos
 * de carnet.
 *
 * Cada interfaz describe, campo por campo, lo que arman
 * App\Http\Controllers\Panel\{Asociacion,CategoriaAprovechamiento,TipoCarnet}Controller.
 * Si allá se renombra una clave y acá no, el editor lo marca en rojo al
 * instante en vez de descubrirlo con una pantalla en blanco.
 *
 * ============================================================================
 *  LOS TRES TRAEN UN CONTADOR DE USO, Y NO ES DECORACIÓN
 * ============================================================================
 *
 * `carnets_count`, `guias_count`, `aprovechamientos_count`: es lo que explica
 * por qué una fila no se puede borrar. Sin el número, «solo se puede desactivar»
 * suena a capricho del sistema; con él se entiende que hay documentos emitidos
 * colgando de esa fila.
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
     *
     * Se declara acá, en el catálogo, y no al otorgar: la fija la resolución al
     * definir el tramo. Puesta en el otorgamiento, dos cupos del mismo tramo
     * podrían terminar con reglas distintas.
     */
    modalidad: ModalidadAprovechamiento;
    modalidad_etiqueta: string;
    modalidad_color: string;
    /**
     * El texto literal de la resolución.
     *
     * Se guarda aparte de los kilos porque no siempre es su lectura: el tramo
     * más alto dice «1001 kg Hasta 2000 Kg PAICHE», y ese «PAICHE» no está en
     * ningún número.
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
 *
 * Lo calcula el servidor mirando la escala ENTERA y ordenando por KILOS, no por
 * número de escala: nada impide cargar la escala 7 con el rango más bajo, y
 * recorriendo por número un catálogo así daría huecos inventados.
 *
 * El hueco no rompe nada visible —simplemente hay volúmenes que no caen en
 * ninguna escala y el formulario de cupo no ofrece nada— así que sin este aviso
 * se descubre en ventanilla, con alguien enfrente.
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
     *
     * NO sirve para leer lo que salió un carnet ya emitido: lo cobrado de
     * verdad está en `pagos` y no se recalcula. Lo que SÍ cambia al subir este
     * número es el saldo pendiente de los carnets que todavía no están
     * cubiertos.
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

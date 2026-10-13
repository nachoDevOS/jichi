import type { EstadoAsociacion, TarifaSireb } from '@/types';

/**
 * Tipos de los CATÁLOGOS: asociaciones y aranceles de SIREB.
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

/** Un servicio de SIREB, tal como lo resume VistaSireb::servicios(). */
export interface ServicioSireb {
    id: string;
    codigo: string | null;
    nombre: string;
    /** false = dado de baja en SIREB: no se puede elegir. */
    activo: boolean;
    /** Cuántas de sus tarifas se pueden cobrar hoy. */
    tarifas: number;
}

/** El servicio que tuvo una fila de Aranceles, en la vista de historial. */
export interface ServicioHistorial {
    servicio_sireb: string | null;
    /** Cómo se llama HOY en SIREB; null si ya no está o SIREB no responde. */
    servicio_nombre: string | null;
    /** Instante ISO: se muestra con fechaHora(). */
    desde: string | null;
}

export interface ServicioAnterior extends ServicioHistorial {
    hasta: string | null;
    /** Nombre de quien lo cambió; null si ya no existe el usuario. */
    cambiado_por: string | null;
}

/** Una fila de Aranceles de SIREB: el servicio que cobra un trámite. */
export interface ArancelFila {
    id: number;
    /** El trámite que lo usa; null si lo registró la unidad. */
    concepto: string | null;
    etiqueta: string;
    descripcion: string;
    servicio_sireb: string | null;
    /** null si no tiene servicio, ya no está en SIREB o SIREB no responde. */
    servicio: ServicioSireb | null;
    /** Las tarifas del servicio, para «Ver tarifas». Vacío sin servicio o sin SIREB. */
    tarifas: TarifaSireb[];
    /** Si lleva «Definir tarifas»: el carnet y los que registra la unidad. */
    usa_tarifas_definidas: boolean;
    tarifas_definidas: number;
}

/** Una tarifa definida de un arancel: su etiqueta y la tarifa de SIREB que la cobra. */
export interface TarifaDefinida {
    /** null = todavía no guardada. */
    id: number | null;
    /** Interna: solo en el carnet (pescador / comercializador). No se muestra. */
    clave: string | null;
    etiqueta: string;
    sireb_tarifa_id: string | null;
}

/** Lo que la pantalla de alta/edición necesita de una asociación. */
export type AsociacionFormulario = Pick<AsociacionFila, 'id' | 'nombre' | 'sigla' | 'datos' | 'estado'>;


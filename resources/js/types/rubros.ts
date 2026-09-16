import type { EstadoRubro } from '@/types';

/**
 * Tipos del catálogo de rubros.
 *
 * Lo arma App\Http\Controllers\Panel\RubroController.
 */

/** Una fila del catálogo. */
export interface RubroFila {
    id: number;
    nombre: string;
    descripcion: string | null;
    /** La tarifa VIGENTE. No es la que tienen los trámites ya registrados. */
    costo: number;
    estado: EstadoRubro;
    estado_etiqueta: string;
    estado_color: string;
    /** Cuántos carnets lo tienen habilitado: qué tan grave sería desactivarlo. */
    habilitaciones_count: number;
    tramites_count: number;
}

/** Lo que manda el formulario de alta y edición. */
export interface FormularioRubro {
    nombre: string;
    descripcion: string;
    /** Se maneja como texto porque el <input> devuelve texto. */
    costo: string;
    estado: EstadoRubro;
    _method?: 'put';
}

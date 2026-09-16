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
    /**
     * ¿Esta actividad se autoriza por volumen (kilos)?
     *
     * La pesca sí —el carnet lleva el cupo impreso y se contrasta contra las
     * guías de transporte—; la comercialización no. Sale del catálogo y no de
     * una lista de nombres en React: el mismo rubro figura como «Pescador» o
     * como «Faena» según quién lo cargó.
     *
     * De esto depende que el formulario pida el cupo y que la ficha y el
     * plástico lo muestren. El servidor lo vuelve a comprobar al guardar:
     * esconder un campo es comodidad, no regla.
     */
    requiere_capacidad: boolean;
    estado: EstadoRubro;
    estado_etiqueta: string;
    estado_color: string;
    /** Cuántos carnets se emitieron para esta actividad: qué tan grave sería desactivarlo. */
    carnets_count: number;
    tramites_count: number;
}

/** Lo que manda el formulario de alta y edición. */
export interface FormularioRubro {
    nombre: string;
    descripcion: string;
    /** Se maneja como texto porque el <input> devuelve texto. */
    costo: string;
    /** Si la actividad se autoriza por volumen. Casilla, así que booleano. */
    requiere_capacidad: boolean;
    estado: EstadoRubro;
    _method?: 'put';
}

/** Un paso del tutorial: el segundo del video donde empieza. */
export interface CapituloTutorial {
    segundo: number;
    titulo: string;
}

/** Un video del manual, tal como lo arma ManualController::index(). */
export interface Tutorial {
    clave: string;
    titulo: string;
    descripcion: string;
    modulo: string;
    capitulos: CapituloTutorial[];
    video: string;
    portada: string;
    /** Clip corto en bucle para la tarjeta; null si no se generó. */
    previa: string | null;
    /** El trámite que sigue en el recorrido; la pantalla final lo ofrece. */
    siguiente: { clave: string; titulo: string } | null;
}

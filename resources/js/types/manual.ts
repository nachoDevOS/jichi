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
}

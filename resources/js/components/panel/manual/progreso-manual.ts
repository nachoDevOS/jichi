/** Hasta dónde vio cada tutorial esta computadora: para ofrecer «Continuar» y marcar lo visto. */
export interface ProgresoTutorial {
    segundo: number;
    duracion: number;
    visto: boolean;
}

const clave = (tutorial: string) => `jichi.manual.${tutorial}`;

export function leerProgreso(tutorial: string): ProgresoTutorial | null {
    try {
        const crudo = localStorage.getItem(clave(tutorial));
        return crudo ? (JSON.parse(crudo) as ProgresoTutorial) : null;
    } catch {
        // Navegación privada o almacenamiento lleno: sin progreso, el manual anda igual.
        return null;
    }
}

export function guardarProgreso(tutorial: string, progreso: ProgresoTutorial): void {
    try {
        localStorage.setItem(clave(tutorial), JSON.stringify(progreso));
    } catch {
        /* ver leerProgreso() */
    }
}

/** Se ofrece continuar solo si quedó a la mitad: ni recién empezado ni casi terminado. */
export function puntoParaContinuar(p: ProgresoTutorial | null): number | null {
    return p && p.segundo > 5 && p.duracion - p.segundo > 8 ? p.segundo : null;
}

export function minutos(segundos: number): string {
    return `${Math.floor(segundos / 60)}:${String(Math.floor(segundos % 60)).padStart(2, '0')}`;
}

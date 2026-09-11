/**
 * Ajustes compartidos por todos los gráficos del sistema.
 *
 * Están acá y no dentro de cada gráfico para que el día que cambie la paleta
 * institucional se toque UN archivo y no seis. Los valores no son colores
 * escritos a mano: son variables CSS definidas en resources/css/app.css, así
 * que los gráficos cambian solos entre el modo claro y el oscuro.
 */

/** Serie de colores para categorías (áreas departamentales, tipos de documento...). */
export const SERIE_GRAFICOS = [
    'var(--grafico-1)',
    'var(--grafico-2)',
    'var(--grafico-3)',
    'var(--grafico-4)',
    'var(--grafico-5)',
    'var(--grafico-6)',
] as const;

/**
 * Devuelve un color de la serie por posición, dando la vuelta cuando se acaban.
 * Con 8 áreas y 6 colores, la séptima vuelve a usar el primero.
 */
export function colorSerie(indice: number): string {
    return SERIE_GRAFICOS[indice % SERIE_GRAFICOS.length];
}

/** Estilo del cuadrito que aparece al pasar el mouse sobre un gráfico. */
export const ESTILO_TOOLTIP = {
    background: 'var(--popover)',
    border: '1px solid var(--border)',
    borderRadius: '0.5rem',
    fontSize: '12px',
    color: 'var(--popover-foreground)',
} as const;

/** Estilo de los números de los ejes X e Y. */
export const ESTILO_EJE = {
    fontSize: 11,
    fill: 'var(--muted-foreground)',
} as const;

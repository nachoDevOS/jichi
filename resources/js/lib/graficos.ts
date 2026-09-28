/**
 * Ajustes compartidos por todos los gráficos del sistema.
 */

/** Estilo del cuadrito que aparece al pasar el mouse sobre un gráfico. */
export const ESTILO_TOOLTIP = {
    background: 'var(--popover)',
    border: '1px solid var(--border)',
    borderRadius: '0.5rem',
    fontSize: '12px',
    color: 'var(--popover-foreground)',
} as const;

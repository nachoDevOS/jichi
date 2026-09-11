import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Formatea un monto en bolivianos: 1.250,50 Bs
 * Se usa el locale es-BO para que el separador de miles sea el punto.
 */
export function bs(monto: number | string | null | undefined, simbolo = 'Bs'): string {
    const valor = typeof monto === 'string' ? Number.parseFloat(monto) : (monto ?? 0);

    return `${new Intl.NumberFormat('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(valor) ? valor : 0)} ${simbolo}`;
}

export function fecha(valor: string | Date | null | undefined): string {
    if (!valor) return '—';

    return new Intl.DateTimeFormat('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(valor));
}

export function fechaHora(valor: string | Date | null | undefined): string {
    if (!valor) return '—';

    return new Intl.DateTimeFormat('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(valor));
}

export function iniciales(nombre: string): string {
    return nombre
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((parte) => parte[0]?.toUpperCase() ?? '')
        .join('');
}

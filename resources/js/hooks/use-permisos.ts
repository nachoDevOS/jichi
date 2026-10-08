import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

/**
 * Saber qué puede hacer el usuario conectado. Se pregunta siempre por PERMISO,
 * nunca por rol: los roles los arma la unidad y cambian de nombre.
 */
export function usePermisos() {
    const permisos = usePage<PageProps>().props.auth.user?.permisos ?? [];

    return {
        puede: (permiso: string) => permisos.includes(permiso),
    } as const;
}

import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

/**
 * Saber qué puede hacer el usuario conectado.
 */
export function usePermisos() {
    const { auth } = usePage<PageProps>().props;

    const permisos = auth.user?.permisos ?? [];
    const roles = auth.user?.roles ?? [];

    return {
        /** ¿Tiene este permiso concreto? */
        puede: (permiso: string) => permisos.includes(permiso),

        /** ¿Tiene al menos uno de estos permisos? */
        puedeAlguno: (...lista: string[]) => lista.some((p) => permisos.includes(p)),

        /** ¿Tiene este rol? */
        tieneRol: (rol: string) => roles.includes(rol),

        permisos,
        roles,
    } as const;
}

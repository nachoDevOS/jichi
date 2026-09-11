import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

/**
 * Saber qué puede hacer el usuario conectado.
 *
 * Los permisos los calcula spatie/laravel-permission en PHP y los manda en
 * cada página el middleware HandleInertiaRequests, dentro de
 * auth.user.permisos. Este hook solo los envuelve para no repetir
 * `usePage().props.auth.user?.permisos?.includes(...)` en cada archivo.
 *
 * CÓMO SE USA
 *
 *   const { puede } = usePermisos();
 *
 *   {puede('solicitantes.crear') && <Button>Nuevo solicitante</Button>}
 *
 * ADVERTENCIA IMPORTANTE
 *
 * Esto sirve para NO MOSTRAR botones que el usuario no puede usar. No es
 * seguridad: el navegador es del usuario y cualquiera puede escribir la URL a
 * mano o modificar el JavaScript. Quien realmente bloquea el acceso es el
 * middleware 'permiso:...' declarado en routes/panel.php. Los dos tienen que
 * estar: el de React por comodidad, el de Laravel por seguridad.
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

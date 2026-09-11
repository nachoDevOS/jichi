import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, X } from 'lucide-react';
import { MarcaJichi } from '@/components/comunes/logo-jichi';
import { NAVEGACION, type ItemNavegacion } from '@/components/panel/layout/navegacion';
import { ToggleApariencia } from '@/components/comunes/toggle-apariencia';
import { cn, iniciales } from '@/lib/utils';
import type { PageProps } from '@/types';

/**
 * Barra lateral azul institucional: marca, menú, usuario y salida.
 *
 * En pantallas grandes está siempre visible. En celular se esconde fuera de la
 * pantalla y entra deslizándose cuando se toca el botón del menú; por eso
 * recibe `abierto` y `onCerrar` desde el layout, que es quien guarda ese estado.
 */
export function BarraLateral({
    abierto,
    onCerrar,
}: {
    abierto: boolean;
    onCerrar: () => void;
}) {
    /*
     * usePage() da acceso a las props COMPARTIDAS: las que el middleware
     * HandleInertiaRequests manda en todas las páginas (usuario, institución,
     * flash, ziggy). No hay que pasarlas de componente en componente.
     */
    const { auth, institucion, ziggy } = usePage<PageProps>().props;

    const permisos = auth.user?.permisos ?? [];

    // Filtro 1: se descartan los módulos para los que el usuario no tiene permiso.
    const items = NAVEGACION.filter((item) => !item.permiso || permisos.includes(item.permiso));

    // Filtro 2: Ziggy solo conoce las rutas ya declaradas en routes/. Los
    // módulos que todavía no existen se pintan en gris en vez de romper el
    // render al intentar generar su URL.
    const rutaExiste = (nombre: string) =>
        Boolean((ziggy?.routes as Record<string, unknown>)?.[nombre]);

    // Marca el ítem activo comparando la URL actual con el prefijo de la ruta:
    // 'solicitantes.index' -> 'solicitantes' -> ¿la URL contiene 'solicitantes'?
    const esRutaActual = (nombre: string) =>
        ziggy?.location?.includes(nombre.split('.')[0] ?? '');

    return (
        <aside
            className={cn(
                'fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-sidebar text-sidebar-foreground transition-transform lg:translate-x-0',
                abierto ? 'translate-x-0' : '-translate-x-full',
            )}
        >
            <div className="flex items-center justify-between border-b border-sidebar-border px-4 py-4">
                <MarcaJichi sigla={institucion?.sigla} />

                <button
                    type="button"
                    className="lg:hidden"
                    onClick={onCerrar}
                    aria-label="Cerrar menú"
                >
                    <X className="size-5" />
                </button>
            </div>

            <nav className="flex-1 space-y-1 overflow-y-auto p-3">
                {items.map((item) => (
                    <ItemMenu
                        key={item.ruta}
                        item={item}
                        habilitado={rutaExiste(item.ruta)}
                        activo={Boolean(esRutaActual(item.ruta))}
                    />
                ))}
            </nav>

            <PiePerfil nombre={auth.user?.name ?? ''} cargo={auth.user?.cargo ?? null} />
        </aside>
    );
}

/**
 * Un renglón del menú. Si el módulo todavía no existe se dibuja apagado.
 */
function ItemMenu({
    item,
    habilitado,
    activo,
}: {
    item: ItemNavegacion;
    habilitado: boolean;
    activo: boolean;
}) {
    const Icono = item.icono;

    if (!habilitado) {
        return (
            <span
                title="Módulo aún no habilitado"
                className="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium opacity-40"
            >
                <Icono className="size-4 shrink-0" />
                {item.titulo}
            </span>
        );
    }

    return (
        <Link
            // route() es la función global de Ziggy: convierte el nombre de la
            // ruta de Laravel en su URL. Si mañana la URL cambia en
            // routes/panel.php, acá no hay que tocar nada.
            href={route(item.ruta)}
            className={cn(
                'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                activo
                    ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                    : 'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
            )}
        >
            <Icono className="size-4 shrink-0" />
            {item.titulo}
        </Link>
    );
}

/**
 * Pie de la barra: quién está conectado, el selector de tema y el botón salir.
 */
function PiePerfil({ nombre, cargo }: { nombre: string; cargo: string | null }) {
    return (
        <div className="border-t border-sidebar-border p-3">
            <div className="mb-3 flex items-center gap-3 px-1">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-sidebar-primary text-sm font-semibold text-sidebar-primary-foreground">
                    {iniciales(nombre)}
                </div>

                <div className="min-w-0 leading-tight">
                    <p className="truncate text-sm font-medium">{nombre}</p>
                    <p className="truncate text-xs opacity-70">{cargo}</p>
                </div>
            </div>

            <div className="flex items-center justify-between gap-2">
                <ToggleApariencia />

                {/*
                    Cerrar sesión tiene que ser POST, no un enlace GET. Si fuera
                    un enlace, cualquier sitio externo podría hacer que el
                    navegador lo visite y cerrarte la sesión sin querer. Además
                    router.post() adjunta el token CSRF automáticamente.
                */}
                <button
                    type="button"
                    onClick={() => router.post(route('logout'))}
                    className="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm hover:bg-sidebar-accent"
                >
                    <LogOut className="size-4" />
                    Salir
                </button>
            </div>
        </div>
    );
}

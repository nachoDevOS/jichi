import { Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, LogOut, Menu, PanelLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { LogoJichi } from '@/components/comunes/logo-jichi';
import { ToggleApariencia } from '@/components/comunes/toggle-apariencia';
import { moduloActual, nombreDe } from '@/components/panel/layout/navegacion';
import { iniciales } from '@/lib/utils';
import type { PageProps } from '@/types';

/**
 *  EL ENCABEZADO DEL PANEL, EN DOS FRANJAS
 */
export function BarraSuperior({
    titulo,
    descripcion,
    acciones,
    onAbrirMenu,
    onAlternarAngosto,
}: {
    titulo: string;
    descripcion?: string;
    /** Botones del lado derecho. Los define cada página. */
    acciones?: ReactNode;
    /** Celular: despliega la barra lateral encima del contenido. */
    onAbrirMenu: () => void;
    /** Escritorio: achica la barra lateral a solo iconos. */
    onAlternarAngosto: () => void;
}) {
    const { auth, institucion } = usePage<PageProps>().props;

    return (
        <header className="sticky top-0 z-20 bg-card">
            {/* ------------------------------------------- Franja de sesión */}
            <div className="flex h-14 items-center gap-3 border-b border-border px-4 sm:px-6">
                {/*
                    DOS BOTONES Y NO UNO. El mismo dibujo hace cosas distintas
                    según el tamaño de la pantalla —en celular despliega la barra,
                    en escritorio la angosta— y averiguar el tamaño desde
                    JavaScript para decidir cuál llamar obliga a escuchar el
                    redimensionado y a mantener ese dato sincronizado. Dos
                    botones que se muestran por CSS no pueden desincronizarse.
                */}
                <button
                    type="button"
                    className="-ml-1 rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground lg:hidden"
                    onClick={onAbrirMenu}
                    aria-label="Abrir menú"
                >
                    <Menu className="size-5" />
                </button>

                <button
                    type="button"
                    className="-ml-1 hidden rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground lg:block"
                    onClick={onAlternarAngosto}
                    aria-label="Angostar o ensanchar el menú"
                >
                    <PanelLeft className="size-5" />
                </button>

                {/* En celular la barra lateral está escondida, así que la marca
                    desaparecería de la pantalla: se repite acá. */}
                <span className="flex items-center gap-2 lg:hidden">
                    <LogoJichi className="size-7" />
                    <span className="text-base font-bold tracking-tight">Jichi</span>
                </span>

                <div className="flex-1" />

                <ToggleApariencia />

                <span aria-hidden className="h-6 w-px bg-border" />

                <div className="flex items-center gap-2.5">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">
                        {iniciales(auth.user?.name ?? '')}
                    </span>

                    {/* El nombre se esconde en pantallas chicas: con el título de
                        la página abajo, es lo primero que se puede sacrificar. */}
                    <div className="hidden min-w-0 leading-tight sm:block">
                        <p className="truncate text-sm font-medium">{auth.user?.name}</p>
                        <p className="truncate text-xs text-muted-foreground">
                            {auth.user?.cargo ?? institucion?.sigla}
                        </p>
                    </div>
                </div>

                {/*
                    Cerrar sesión tiene que ser POST, no un enlace GET. Si fuera
                    un enlace, cualquier sitio externo podría hacer que el
                    navegador lo visite y cerrarte la sesión sin querer. Además
                    router.post() adjunta el token CSRF automáticamente.
                */}
                <button
                    type="button"
                    onClick={() => router.post(route('logout'))}
                    title="Cerrar sesión"
                    aria-label="Cerrar sesión"
                    className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-destructive"
                >
                    <LogOut className="size-4" />
                </button>
            </div>

            {/* ------------------------------------------- Franja de página */}
            <div className="border-b border-border px-4 py-3 sm:px-6">
                <Migas titulo={titulo} />

                <div className="mt-1 flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        {/* truncate corta con «...» los títulos largos en vez de
                            empujar los botones fuera de la pantalla. */}
                        <h1 className="truncate text-xl font-semibold tracking-tight">{titulo}</h1>

                        {descripcion && (
                            <p className="truncate text-sm text-muted-foreground">{descripcion}</p>
                        )}
                    </div>

                    {acciones && <div className="flex shrink-0 items-center gap-2">{acciones}</div>}
                </div>
            </div>
        </header>
    );
}

/**
 * Las migas de pan: «Inicio / Trámites / Nuevo trámite».
 */
function Migas({ titulo }: { titulo: string }) {
    const { ziggy } = usePage<PageProps>().props;

    const modulo = moduloActual(ziggy?.location);
    const enElTablero = modulo?.ruta === 'dashboard';

    // Ziggy solo conoce las rutas ya declaradas: un módulo todavía sin rutas se
    // muestra como texto y no como enlace, en vez de romper el render.
    const enlazable =
        modulo && Boolean((ziggy?.routes as Record<string, unknown>)?.[modulo.ruta]);

    return (
        <nav aria-label="Ruta de navegación" className="flex items-center gap-1 text-xs text-muted-foreground">
            <Link href={route('dashboard')} className="hover:text-foreground hover:underline">
                Inicio
            </Link>

            {modulo && !enElTablero && nombreDe(modulo) !== titulo && (
                <>
                    <ChevronRight aria-hidden className="size-3" />

                    {enlazable ? (
                        <Link href={route(modulo.ruta)} className="hover:text-foreground hover:underline">
                            {nombreDe(modulo)}
                        </Link>
                    ) : (
                        <span>{nombreDe(modulo)}</span>
                    )}
                </>
            )}

            {!enElTablero && (
                <>
                    <ChevronRight aria-hidden className="size-3" />
                    <span className="truncate font-medium text-foreground">{titulo}</span>
                </>
            )}
        </nav>
    );
}

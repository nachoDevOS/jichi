import { Link, router, usePage } from "@inertiajs/react";
import {
    Building2,
    ChevronDown,
    ChevronRight,
    LogOut,
    Menu,
    PanelLeft,
    ShieldCheck,
    type LucideIcon,
} from "lucide-react";
import { useEffect, useRef, useState, type ReactNode } from "react";
import { LogoJichi } from "@/components/comunes/logo-jichi";
import { ToggleApariencia } from "@/components/comunes/toggle-apariencia";
import { moduloActual, nombreDe } from "@/components/panel/layout/navegacion";
import { usePermisos } from "@/hooks/use-permisos";
import { cn, iniciales } from "@/lib/utils";
import type { PageProps } from "@/types";

/**
 *  El encabezado del panel, en dos franjas
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
                    <span className="text-base font-bold tracking-tight">
                        Jichi
                    </span>
                </span>

                <div className="flex-1" />

                <ToggleApariencia />

                <span aria-hidden className="h-6 w-px bg-border" />

                <MenuPerfil />
            </div>

            {/* ------------------------------------------- Franja de página */}
            <div className="border-b border-border px-4 py-3 sm:px-6">
                <Migas titulo={titulo} />

                <div className="mt-1 flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        {/* truncate corta con «...» los títulos largos en vez de
                            empujar los botones fuera de la pantalla. */}
                        <h1 className="truncate text-xl font-semibold tracking-tight">
                            {titulo}
                        </h1>

                        {descripcion && (
                            <p className="truncate text-sm text-muted-foreground">
                                {descripcion}
                            </p>
                        )}
                    </div>

                    {acciones && (
                        <div className="flex shrink-0 items-center gap-2">
                            {acciones}
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}

/**
 * El avatar abre un cuadro con los datos de la cuenta y el cierre de sesión.
 */
function MenuPerfil() {
    const { auth, institucion } = usePage<PageProps>().props;
    const [abierto, setAbierto] = useState(false);
    const contenedor = useRef<HTMLDivElement>(null);
    const usuario = auth.user;

    // Se cierra al hacer clic afuera o con Escape, como cualquier menú.
    useEffect(() => {
        if (!abierto) return;

        const alClic = (e: MouseEvent) => {
            if (!contenedor.current?.contains(e.target as Node))
                setAbierto(false);
        };
        const alTecla = (e: KeyboardEvent) =>
            e.key === "Escape" && setAbierto(false);

        document.addEventListener("mousedown", alClic);
        document.addEventListener("keydown", alTecla);

        return () => {
            document.removeEventListener("mousedown", alClic);
            document.removeEventListener("keydown", alTecla);
        };
    }, [abierto]);

    return (
        <div ref={contenedor} className="relative">
            <button
                type="button"
                onClick={() => setAbierto((a) => !a)}
                aria-haspopup="menu"
                aria-expanded={abierto}
                className="flex items-center gap-2.5 rounded-md py-1 pr-1.5 pl-1 text-left hover:bg-secondary"
            >
                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">
                    {iniciales(usuario?.name ?? "")}
                </span>

                {/* El nombre se esconde en pantallas chicas: con el título de
                    la página abajo, es lo primero que se puede sacrificar. */}
                <span className="hidden min-w-0 leading-tight sm:block">
                    <span className="block truncate text-sm font-medium">
                        {usuario?.name}
                    </span>
                    <span className="block truncate text-xs text-muted-foreground">
                        {usuario?.roles[0] ?? institucion?.sigla}
                    </span>
                </span>

                <ChevronDown
                    aria-hidden
                    className={cn(
                        "size-4 text-muted-foreground transition-transform",
                        abierto && "rotate-180",
                    )}
                />
            </button>

            {abierto && (
                <div
                    role="menu"
                    className="absolute right-0 mt-2 w-72 overflow-hidden rounded-lg border border-border bg-card shadow-lg"
                >
                    <div className="flex items-center gap-3 border-b border-border p-4">
                        <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                            {iniciales(usuario?.name ?? "")}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-semibold">
                                {usuario?.name}
                            </p>
                            {usuario?.email && (
                                <p className="truncate text-xs text-muted-foreground">
                                    {usuario.email}
                                </p>
                            )}
                        </div>
                    </div>

                    <dl className="space-y-2.5 p-4 text-sm">
                        <DatoPerfil
                            icono={ShieldCheck}
                            rotulo={
                                usuario?.roles.length === 1 ? "Rol" : "Roles"
                            }
                            valor={
                                usuario?.roles.length
                                    ? usuario.roles.join(", ")
                                    : "Sin rol"
                            }
                        />
                        <DatoPerfil
                            icono={Building2}
                            rotulo="Institución"
                            valor={
                                institucion?.municipio ??
                                institucion?.sigla ??
                                "—"
                            }
                        />
                    </dl>

                    {/* Cerrar sesión va por POST: un enlace GET lo podría disparar
                        cualquier sitio externo. router.post() adjunta el CSRF. */}
                    <button
                        type="button"
                        role="menuitem"
                        onClick={() => router.post(route("logout"))}
                        className="flex w-full items-center gap-2 border-t border-border px-4 py-3 text-sm text-destructive hover:bg-secondary"
                    >
                        <LogOut className="size-4" />
                        Cerrar sesión
                    </button>
                </div>
            )}
        </div>
    );
}

function DatoPerfil({
    icono: Icono,
    rotulo,
    valor,
}: {
    icono: LucideIcon;
    rotulo: string;
    valor: string;
}) {
    return (
        <div className="flex items-start gap-2.5">
            <Icono
                aria-hidden
                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
            />
            <div className="min-w-0">
                <dt className="text-xs text-muted-foreground">{rotulo}</dt>
                <dd className="break-words">{valor}</dd>
            </div>
        </div>
    );
}

/**
 * Las migas de pan: «Inicio / Trámites / Nuevo trámite».
 */
function Migas({ titulo }: { titulo: string }) {
    const { ziggy } = usePage<PageProps>().props;
    const { puede } = usePermisos();

    const modulo = moduloActual(ziggy?.location);
    const enElTablero = modulo?.ruta === "dashboard";

    // Ziggy solo conoce las rutas ya declaradas: un módulo todavía sin rutas se
    // muestra como texto y no como enlace, en vez de romper el render.
    const enlazable =
        modulo &&
        Boolean((ziggy?.routes as Record<string, unknown>)?.[modulo.ruta]) &&
        (!modulo.permiso || puede(modulo.permiso));

    return (
        <nav
            aria-label="Ruta de navegación"
            className="flex items-center gap-1 text-xs text-muted-foreground"
        >
            {/* Sin el permiso del tablero, «Inicio» es texto: el enlace terminaría en 403. */}
            {puede("dashboard.ver") ? (
                <Link
                    href={route("dashboard")}
                    className="hover:text-foreground hover:underline"
                >
                    Inicio
                </Link>
            ) : (
                <span>Inicio</span>
            )}

            {modulo && !enElTablero && nombreDe(modulo) !== titulo && (
                <>
                    <ChevronRight aria-hidden className="size-3" />

                    {enlazable ? (
                        <Link
                            href={route(modulo.ruta)}
                            className="hover:text-foreground hover:underline"
                        >
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
                    <span className="truncate font-medium text-foreground">
                        {titulo}
                    </span>
                </>
            )}
        </nav>
    );
}

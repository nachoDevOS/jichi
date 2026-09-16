import { Link, usePage } from '@inertiajs/react';
import { ChevronsLeft, ChevronsRight, X } from 'lucide-react';
import { Fragment } from 'react';
import { LogoJichi, MarcaJichi } from '@/components/comunes/logo-jichi';
import { moduloActual, NAVEGACION, type ItemNavegacion } from '@/components/panel/layout/navegacion';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

/**
 * Barra lateral azul institucional: la marca arriba y el menú debajo.
 *
 * ----------------------------------------------------------------------------
 *  TRES ESTADOS, NO DOS
 * ----------------------------------------------------------------------------
 *
 * En pantallas grandes está siempre visible y puede estar ANCHA —icono más
 * texto— o ANGOSTA —solo los iconos—. En celular no existe ninguno de los dos:
 * está fuera de la pantalla y entra deslizándose, y ahí siempre va ancha,
 * porque un menú de iconos sueltos en un teléfono no se entiende.
 *
 * Por eso recibe `abierto` (celular) y `angosto` (escritorio) por separado, y
 * los dos los guarda el layout: el botón que los cambia vive en el encabezado,
 * que es otro componente.
 */
export function BarraLateral({
    abierto,
    angosto,
    onCerrar,
    onAlternarAngosto,
}: {
    abierto: boolean;
    angosto: boolean;
    onCerrar: () => void;
    onAlternarAngosto: () => void;
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

    // Cuál es el módulo abierto. El cálculo vive en navegacion.ts porque las
    // migas de pan del encabezado necesitan exactamente el mismo.
    const actual = moduloActual(ziggy?.location);

    return (
        <aside
            className={cn(
                'fixed inset-y-0 left-0 z-40 flex flex-col bg-sidebar text-sidebar-foreground transition-[transform,width] duration-200 lg:translate-x-0',
                // En celular la barra va SIEMPRE ancha: `angosto` es una
                // decisión de escritorio y acá solo estorbaría.
                angosto ? 'w-64 lg:w-16' : 'w-64',
                abierto ? 'translate-x-0' : '-translate-x-full',
            )}
        >
            {/*
                La franja de la marca mide lo mismo que el encabezado (h-14) y va
                un tono más oscura: así las dos barras arrancan a la misma altura
                y la esquina superior izquierda se lee como una sola pieza.
            */}
            <div className="flex h-14 shrink-0 items-center justify-between bg-black/15 px-4">
                {/* Angosta queda solo el escudo; el nombre no entra en 64 px. */}
                <span className={cn(angosto && 'lg:hidden')}>
                    <MarcaJichi sigla={institucion?.sigla} />
                </span>

                <span
                    className={cn(
                        'hidden size-8 items-center justify-center rounded-md bg-white p-0.5',
                        angosto && 'lg:flex',
                    )}
                >
                    <LogoJichi className="size-full" />
                </span>

                <button
                    type="button"
                    className="lg:hidden"
                    onClick={onCerrar}
                    aria-label="Cerrar menú"
                >
                    <X className="size-5" />
                </button>
            </div>

            <nav className="flex-1 overflow-x-hidden overflow-y-auto py-2">
                {items.map((item, i) => (
                    // Fragment porque cada vuelta puede dibujar DOS cosas —el
                    // rótulo del grupo y el ítem— y JSX no deja devolver dos
                    // elementos sueltos desde un map.
                    <Fragment key={item.ruta}>
                        {/*
                            El rótulo se dibuja solo cuando el grupo cambia
                            respecto del ítem anterior. Así la lista de arriba es
                            la única fuente: no hay una lista de grupos aparte que
                            se pueda desincronizar.
                        */}
                        {item.grupo && item.grupo !== items[i - 1]?.grupo && (
                            <RotuloGrupo titulo={item.grupo} angosto={angosto} />
                        )}

                        <ItemMenu
                            item={item}
                            habilitado={rutaExiste(item.ruta)}
                            activo={actual?.ruta === item.ruta}
                            angosto={angosto}
                        />
                    </Fragment>
                ))}
            </nav>

            <PieBarra angosto={angosto} onAlternarAngosto={onAlternarAngosto} />
        </aside>
    );
}

/**
 * El rótulo de una sección: «Ventanilla», «Registro», «Administración».
 *
 * Angosta la barra, el texto no entra, pero el grupo sigue existiendo: se
 * reemplaza por una línea divisoria para que los iconos no queden como una
 * columna continua sin ninguna agrupación.
 */
function RotuloGrupo({ titulo, angosto }: { titulo: string; angosto: boolean }) {
    return (
        <>
            <p
                className={cn(
                    'px-4 pt-5 pb-1.5 text-[11px] font-semibold tracking-wider uppercase opacity-50',
                    angosto && 'lg:hidden',
                )}
            >
                {titulo}
            </p>

            <hr
                aria-hidden
                className={cn('mx-3 my-3 hidden border-sidebar-border', angosto && 'lg:block')}
            />
        </>
    );
}

/**
 * Un renglón del menú. Si el módulo todavía no existe se dibuja apagado.
 *
 * LA FILA VA A TODO EL ANCHO y sin esquinas redondeadas, y el ítem activo se
 * marca con una barra dorada pegada al borde izquierdo. Es lo que hace que la
 * columna se lea como una lista y no como una pila de botones sueltos: la marca
 * está siempre en la misma coordenada, así que el ojo la encuentra sin buscar.
 */
function ItemMenu({
    item,
    habilitado,
    activo,
    angosto,
}: {
    item: ItemNavegacion;
    habilitado: boolean;
    activo: boolean;
    angosto: boolean;
}) {
    const Icono = item.icono;

    // Angosta la barra el texto desaparece, así que el nombre pasa al globito
    // del navegador. Sin esto quedan ocho iconos sin ninguna forma de saber
    // cuál es cuál.
    const rotulo = angosto ? item.titulo : undefined;

    const base = 'flex items-center gap-3 border-l-[3px] px-4 py-2.5 text-sm font-medium';

    if (!habilitado) {
        return (
            <span
                title={rotulo ? `${item.titulo} — aún no habilitado` : 'Módulo aún no habilitado'}
                className={cn(base, 'cursor-not-allowed border-transparent opacity-40')}
            >
                <Icono className="size-4 shrink-0" />
                <span className={cn('truncate', angosto && 'lg:hidden')}>{item.titulo}</span>
            </span>
        );
    }

    return (
        <Link
            // route() es la función global de Ziggy: convierte el nombre de la
            // ruta de Laravel en su URL. Si mañana la URL cambia en
            // routes/panel.php, acá no hay que tocar nada.
            href={route(item.ruta)}
            title={rotulo}
            aria-current={activo ? 'page' : undefined}
            className={cn(
                base,
                'transition-colors',
                activo
                    ? 'border-sidebar-primary bg-sidebar-accent text-sidebar-accent-foreground'
                    : 'border-transparent opacity-80 hover:bg-sidebar-accent hover:opacity-100',
            )}
        >
            <Icono className="size-4 shrink-0" />
            <span className={cn('truncate', angosto && 'lg:hidden')}>{item.titulo}</span>
        </Link>
    );
}

/**
 * Pie de la barra: el botón que la angosta, y nada más.
 *
 * QUIÉN ESTÁ CONECTADO NO VA ACÁ, va en el encabezado. Es a propósito: angosta
 * la barra mide 64 px y ahí no entra un nombre, así que el dato desaparecería
 * justo en la configuración en la que el operador ya no ve los rótulos del menú
 * y más necesita saber con qué sesión está trabajando. El encabezado, en
 * cambio, mide siempre lo mismo.
 */
function PieBarra({
    angosto,
    onAlternarAngosto,
}: {
    angosto: boolean;
    onAlternarAngosto: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onAlternarAngosto}
            aria-label={angosto ? 'Ensanchar el menú' : 'Angostar el menú'}
            className="hidden shrink-0 items-center justify-center gap-2 bg-black/15 py-3 text-xs font-medium opacity-60 transition-opacity hover:opacity-100 lg:flex"
        >
            {angosto ? <ChevronsRight className="size-4" /> : <ChevronsLeft className="size-4" />}
            <span className={cn(angosto && 'lg:hidden')}>Angostar</span>
        </button>
    );
}

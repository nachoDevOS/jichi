import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Paginado } from '@/types';

/**
 * Barra de paginación para cualquier listado del panel.
 *
 * DE DÓNDE SALEN ESTOS DATOS
 *
 * En el controlador se escribió `->paginate(15)`. Laravel no devuelve un array
 * pelado, sino un objeto con las filas MÁS la información de navegación:
 *
 *   {
 *     data:         [ ...las 15 filas de esta página... ],
 *     current_page: 2,
 *     last_page:    4,
 *     from: 16, to: 30, total: 48,
 *     links: [ { url, label, active }, ... ]   <- los botones ya calculados
 *   }
 *
 * Ese objeto llega tal cual a React. Acá solo hay que pintarlo.
 *
 * POR QUÉ <Link> Y NO <a>
 *
 * <Link> es de Inertia. Hace la petición por detrás y reemplaza solo el
 * contenido de la página, sin recargar el navegador: no parpadea, no se
 * vuelven a descargar los estilos ni el JavaScript. Un <a> normal recargaría
 * todo. Regla simple: dentro del sistema, siempre <Link>.
 */
export function Paginacion<T>({ paginado }: { paginado: Paginado<T> }) {
    // Con una sola página no hay nada que navegar.
    if (paginado.last_page <= 1) {
        return null;
    }

    const enlaces = paginado.links;
    const anterior = enlaces[0];
    const siguiente = enlaces[enlaces.length - 1];
    const numeros = enlaces.slice(1, -1);

    return (
        <div className="flex flex-col items-center justify-between gap-3 border-t border-border px-5 py-3 sm:flex-row">
            <p className="text-sm text-muted-foreground">
                Mostrando <span className="font-medium text-foreground">{paginado.from ?? 0}</span>
                {' – '}
                <span className="font-medium text-foreground">{paginado.to ?? 0}</span>
                {' de '}
                <span className="font-medium text-foreground">{paginado.total}</span>
            </p>

            <nav className="flex items-center gap-1" aria-label="Paginación">
                <BotonPagina url={anterior?.url ?? null} titulo="Página anterior">
                    <ChevronLeft className="size-4" />
                </BotonPagina>

                {numeros.map((enlace, i) => (
                    <BotonPagina
                        // El label sirve de clave salvo en los "…", que pueden
                        // repetirse; por eso se le suma la posición.
                        key={`${enlace.label}-${i}`}
                        url={enlace.url}
                        activo={enlace.active}
                        titulo={`Ir a la página ${enlace.label}`}
                    >
                        {enlace.label}
                    </BotonPagina>
                ))}

                <BotonPagina url={siguiente?.url ?? null} titulo="Página siguiente">
                    <ChevronRight className="size-4" />
                </BotonPagina>
            </nav>
        </div>
    );
}

/**
 * Un botón de la barra. Si `url` es null significa que no se puede ir a ningún
 * lado (ya estás en la primera o última página): se pinta apagado y sin enlace.
 */
function BotonPagina({
    url,
    activo = false,
    titulo,
    children,
}: {
    url: string | null;
    activo?: boolean;
    titulo: string;
    children: React.ReactNode;
}) {
    const estilo = cn(
        'inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2.5 text-sm transition-colors',
        activo
            ? 'bg-primary font-medium text-primary-foreground'
            : 'text-muted-foreground hover:bg-secondary hover:text-foreground',
    );

    if (!url) {
        return (
            <span className={cn(estilo, 'cursor-not-allowed opacity-40')} aria-disabled>
                {children}
            </span>
        );
    }

    return (
        <Link
            href={url}
            title={titulo}
            aria-current={activo ? 'page' : undefined}
            // preserveScroll evita que la página salte al inicio al cambiar
            // de página: el usuario se queda mirando la misma parte de la tabla.
            preserveScroll
            className={estilo}
        >
            {children}
        </Link>
    );
}

import { Link, router, usePage } from '@inertiajs/react';
import { Clock, FileText, Home, LogOut, Receipt, User, type LucideIcon } from 'lucide-react';
import type { PropsWithChildren, ReactNode } from 'react';
import { Toaster } from 'sonner';
import { useFlash } from '@/hooks/use-flash';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

/** Las cinco secciones: pocas, porque se usa desde el celular y de vez en cuando. */
// `corto` es el del menú de abajo: cinco en 320 px no entran con el nombre largo.
const SECCIONES: { ruta: string; texto: string; corto: string; icono: LucideIcon }[] = [
    { ruta: 'portal.inicio', texto: 'Inicio', corto: 'Inicio', icono: Home },
    { ruta: 'portal.en-curso', texto: 'En curso', corto: 'En curso', icono: Clock },
    { ruta: 'portal.papeles', texto: 'Mis papeles', corto: 'Papeles', icono: FileText },
    { ruta: 'portal.pagos', texto: 'Mis pagos', corto: 'Pagos', icono: Receipt },
    { ruta: 'portal.perfil', texto: 'Mis datos', corto: 'Datos', icono: User },
];

/**
 *  El marco del portal del beneficiario (/mi-cuenta)
 *
 *  Claro siempre, como la portada: se abre en teléfonos de todo tipo y tiene
 *  que verse igual. Por eso no usa los tokens del panel, que cambian en oscuro.
 */
export default function LayoutPortal({
    titulo,
    bajada,
    extra,
    sinMenu = false,
    children,
}: PropsWithChildren<{
    titulo: string;
    /** Una línea de contexto bajo el título: «2 trámites abiertos». */
    bajada?: string;
    /** Lo que va a la derecha del título, como los filtros de «En curso». */
    extra?: ReactNode;
    /** Mientras la clave sea la temporal no hay adónde ir: solo cambiarla o salir. */
    sinMenu?: boolean;
}>) {
    useFlash();
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <div className="min-h-screen bg-rio-espuma text-slate-700">
                <header
                    className="text-white"
                    style={{ background: 'linear-gradient(170deg, var(--rio) 0%, var(--rio-profundo) 100%)' }}
                >
                    <div className="flex h-1.5" aria-hidden>
                        <i className="flex-1 bg-[#d52b1e]" />
                        <i className="flex-1 bg-[#f4c500]" />
                        <i className="flex-1 bg-[#0c6b32]" />
                    </div>

                    <div className="mx-auto flex max-w-4xl items-center gap-3 px-4 py-3 sm:px-6">
                        <img src="/image/icon.png" alt="" aria-hidden className="size-10 shrink-0 object-contain" />

                        <div className="min-w-0 flex-1 leading-tight">
                            <p className="text-xs font-semibold tracking-[0.15em] text-rio-claro uppercase">
                                Mi cuenta · SEDAG
                            </p>
                            <p className="truncate text-sm font-semibold">{auth.user?.name}</p>
                        </div>

                        <button
                            type="button"
                            onClick={() => router.post(route('portal.salir'))}
                            className="flex shrink-0 items-center gap-1.5 rounded-lg border border-white/25 bg-white/10 px-3 py-2 text-xs font-semibold transition-colors hover:bg-white/20"
                        >
                            <LogOut className="size-4" />
                            Salir
                        </button>
                    </div>

                    {!sinMenu && (
                        <nav className="mx-auto hidden max-w-4xl gap-1 px-4 sm:flex sm:px-6" aria-label="Secciones">
                            {SECCIONES.map((s) => (
                                <Link
                                    key={s.ruta}
                                    href={route(s.ruta)}
                                    className={cn(
                                        'flex items-center gap-2 rounded-t-xl px-4 py-2.5 text-sm font-semibold transition-colors',
                                        route().current(s.ruta)
                                            ? 'bg-rio-espuma text-rio-profundo'
                                            : 'text-white/80 hover:bg-white/10 hover:text-white',
                                    )}
                                >
                                    <s.icono className="size-4" />
                                    {s.texto}
                                </Link>
                            ))}
                        </nav>
                    )}
                </header>

                <main className={cn('mx-auto max-w-4xl px-4 pt-6 sm:px-6', sinMenu ? 'pb-10' : 'pb-28 sm:pb-10')}>
                    <div className="mb-4 flex flex-wrap items-end justify-between gap-x-4 gap-y-3 border-b border-rio-claro/30 pb-3">
                        <div className="min-w-0">
                            <h1 className="text-xl font-bold tracking-tight text-rio-profundo sm:text-2xl">{titulo}</h1>
                            {bajada && <p className="mt-0.5 text-sm text-slate-500">{bajada}</p>}
                        </div>
                        {extra}
                    </div>
                    {children}
                </main>

                {/* En el celular el menú va abajo, al alcance del pulgar. */}
                {!sinMenu && (
                    <nav
                        className="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur sm:hidden"
                        aria-label="Secciones"
                    >
                        {SECCIONES.map((s) => {
                            const activa = route().current(s.ruta);

                            return (
                                <Link
                                    key={s.ruta}
                                    href={route(s.ruta)}
                                    className={cn(
                                        'flex flex-col items-center gap-1 py-2.5 text-[11px] font-semibold',
                                        activa ? 'text-rio' : 'text-slate-500',
                                    )}
                                >
                                    <s.icono className={cn('size-5', activa && 'stroke-[2.5]')} />
                                    {s.corto}
                                </Link>
                            );
                        })}
                    </nav>
                )}

                <Toaster position="top-center" richColors closeButton />
            </div>
        </>
    );
}

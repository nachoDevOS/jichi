import type { LucideIcon } from 'lucide-react';
import { CalendarClock, ExternalLink, Users } from 'lucide-react';
import type { MouseEvent, ReactNode } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { TextoCopiable } from '@/components/comunes/texto-copiable';
import { EnlacePermitido } from '@/components/panel/comunes/enlace-permitido';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useConteo, useMontado } from '@/hooks/use-movimiento';
import { cn, fecha, fechaHora } from '@/lib/utils';

/**
 * Las piezas de las fichas de los cuatro trámites (autorización, carnet, faena
 * y guía), para que se vean iguales. La plantilla es la ficha de la autorización.
 */

export const kg = (n: number) => `${n.toLocaleString('es-BO', { maximumFractionDigits: 2 })} kg`;

export const porcentaje = (parte: number, total: number) =>
    total > 0 ? Math.max(0, Math.min(100, (parte / total) * 100)) : 0;

export type Tono = 'normal' | 'aviso' | 'peligro';

/** Cuánto queda del cupo: amarillo con la mitad o menos, rojo con el 20 % o menos. Siempre con texto. */
export function nivelDisponible(pct: number, aprobada: boolean): { tono: Tono; barra: string; texto: string | null } {
    if (aprobada && pct <= 20) {
        return { tono: 'peligro', barra: 'bg-red-500', texto: pct <= 0 ? 'sin kilos' : 'casi agotado' };
    }
    if (aprobada && pct <= 50) {
        return { tono: 'aviso', barra: 'bg-amber-400', texto: 'mitad o menos' };
    }

    return { tono: 'normal', barra: 'bg-rio-claro dark:bg-rio', texto: null };
}

// ----------------------------------------------------------------- La banda

export type TonoChip = 'normal' | 'aviso' | 'vencido';

const TONOS_CHIP: Record<TonoChip, string> = {
    normal: 'bg-white/10 ring-white/15',
    aviso: 'bg-amber-400/20 ring-amber-300/50 text-amber-50',
    vencido: 'bg-rose-500/25 ring-rose-300/50 text-rose-50',
};

/** Cuánto falta para el vencimiento, y en qué tono decirlo: ámbar el último mes, rojo vencido. */
export function avisoVencimiento(
    dias: number | null,
    aprobado: boolean,
    umbral = 30,
): { texto: string | null; tono: TonoChip } {
    if (dias === null || !aprobado) {
        return { texto: null, tono: 'normal' };
    }
    if (dias < 0) {
        return { texto: 'vencido', tono: 'vencido' };
    }
    if (dias === 0) {
        return { texto: 'vence hoy', tono: 'aviso' };
    }

    return { texto: `faltan ${dias} ${dias === 1 ? 'día' : 'días'}`, tono: dias <= umbral ? 'aviso' : 'normal' };
}

export function Chip({
    icono: Icono,
    tono = 'normal',
    children,
}: {
    icono: LucideIcon;
    tono?: TonoChip;
    children: ReactNode;
}) {
    return (
        <span className={cn('flex items-center gap-1.5 rounded-full px-2.5 py-1 ring-1', TONOS_CHIP[tono])}>
            <Icono className="size-3.5 text-institucional-dorado" />
            {children}
        </span>
    );
}

/** El titular con su foto: lo primero que se confirma contra quien está en el mostrador. */
export function BandaTitular({
    foto,
    nombre,
    beneficiarioId,
    linea,
    estado,
    children,
}: {
    foto: string | null;
    nombre: string | null;
    beneficiarioId: number | null;
    /** La línea chica bajo el nombre: C.I. y el número del documento. */
    linea: string;
    estado: { color: string; etiqueta: string };
    /** Los `Chip` de la derecha. */
    children?: ReactNode;
}) {
    return (
        <section
            className="relative overflow-hidden rounded-xl px-5 py-4 text-white shadow-sm sm:px-6"
            style={{
                background:
                    'radial-gradient(500px 160px at 95% 0%, color-mix(in oklch, var(--rio-claro) 30%, transparent), transparent 70%),' +
                    'linear-gradient(120deg, var(--rio) 0%, var(--rio-profundo) 70%)',
            }}
        >
            <div className="flex flex-wrap items-center gap-x-4 gap-y-3">
                <Retrato url={foto} nombre={nombre ?? 'Sin nombre'} className="size-14 ring-2 ring-white/25" />

                <div className="min-w-[16rem] flex-1">
                    {beneficiarioId !== null ? (
                        <EnlacePermitido
                            permiso="beneficiarios.ver"
                            href={route('beneficiarios.show', beneficiarioId)}
                            className="block truncate text-xl leading-tight font-semibold hover:underline"
                        >
                            {nombre ?? '—'}
                        </EnlacePermitido>
                    ) : (
                        <p className="truncate text-xl leading-tight font-semibold">{nombre ?? '—'}</p>
                    )}
                    <p className="mt-0.5 text-sm text-white/65 tabular-nums">{linea}</p>
                </div>

                <div className="flex flex-wrap items-center gap-2 text-sm">
                    {children}
                    <Badge color={estado.color} className="px-2.5 py-1 text-sm">
                        {estado.etiqueta}
                    </Badge>
                </div>
            </div>
        </section>
    );
}

// ----------------------------------------------------------------- Las cifras

const TONOS_CIFRA: Record<Tono, { tarjeta: string; valor: string; pie: string }> = {
    normal: { tarjeta: '', valor: '', pie: 'text-muted-foreground' },
    aviso: {
        tarjeta: 'bg-amber-50 ring-2 ring-amber-400/60 dark:bg-amber-500/10',
        valor: 'text-amber-700 dark:text-amber-300',
        pie: 'font-medium text-amber-800 dark:text-amber-200',
    },
    peligro: {
        tarjeta: 'bg-red-50 ring-2 ring-red-400/60 dark:bg-red-500/10',
        valor: 'text-red-700 dark:text-red-300',
        pie: 'font-medium text-red-800 dark:text-red-200',
    },
};

/** La cifra cuenta desde 0 al abrir la ficha. */
export function Conteo({ valor, formato }: { valor: number; formato: (n: number) => string }) {
    const actual = useConteo(valor);

    return <>{formato(actual === valor ? valor : Math.round(actual))}</>;
}

export function Cifra({
    icono: Icono,
    etiqueta,
    valor,
    pie,
    destacada = false,
    tono = 'normal',
}: {
    icono: LucideIcon;
    etiqueta: string;
    valor: ReactNode;
    pie: string;
    destacada?: boolean;
    /** Aviso en amarillo, peligro en rojo: la cifra pide atención. */
    tono?: Tono;
}) {
    const estilo = TONOS_CIFRA[tono];

    return (
        <Card className={cn('min-w-0', destacada && 'ring-2 ring-primary/20', estilo.tarjeta)}>
            <CardContent className="p-4">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{etiqueta}</p>
                    <Icono className={cn('size-4', destacada ? 'text-primary' : 'text-muted-foreground')} />
                </div>
                <p
                    className={cn(
                        'mt-2 truncate text-2xl font-semibold tabular-nums',
                        destacada && 'text-primary',
                        estilo.valor,
                    )}
                >
                    {valor}
                </p>
                <p className={cn('truncate text-xs', estilo.pie)}>{pie}</p>
            </CardContent>
        </Card>
    );
}

/** Barra de un solo tramo para lo disponible de una autorización: se llena al abrir y toma el color del nivel. */
export function BarraDisponible({
    disponible,
    total,
    aprobada,
}: {
    disponible: number;
    total: number;
    aprobada: boolean;
}) {
    const montado = useMontado();
    const pct = porcentaje(disponible, total);
    const nivel = nivelDisponible(pct, aprobada);

    return (
        <div className="space-y-1.5">
            <div className="h-2.5 overflow-hidden rounded-full bg-secondary">
                <div
                    className={cn(
                        'animar-brillo h-full rounded-full transition-[width] duration-1000 ease-out motion-reduce:transition-none',
                        nivel.barra,
                    )}
                    style={{ width: montado ? `${pct}%` : '0%' }}
                />
            </div>
            <p className="text-xs text-muted-foreground">
                <span className="font-medium text-foreground tabular-nums">{kg(disponible)}</span> disponibles de{' '}
                {kg(total)}
                {nivel.texto && <span className="font-medium"> · {nivel.texto}</span>}
            </p>
        </div>
    );
}

// ----------------------------------------------------------------- El documento

/** El código de 16 caracteres: la llave del QR, con el enlace a lo que ve quien lo escanea. */
export function TarjetaCodigo({
    titulo,
    codigo,
    numero,
    ayuda,
}: {
    titulo: string;
    codigo: string | null;
    numero: string | null;
    ayuda: string;
}) {
    return (
        <div className="rounded-xl border border-primary/20 bg-secondary/60 p-4">
            <div className="flex items-center justify-between gap-2">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{titulo}</p>
                {numero && <span className="text-xs text-muted-foreground tabular-nums">N° {numero}</span>}
            </div>
            {codigo ? (
                <TextoCopiable texto={codigo} className="mt-1 -ml-1.5 text-lg font-semibold text-primary" />
            ) : (
                <p className="mt-1 text-muted-foreground">Sin código</p>
            )}
            <p className="mt-1 text-xs leading-relaxed text-muted-foreground">{ayuda}</p>
            {codigo && (
                <a
                    href={route('verificar.show', codigo.replace(/-/g, ''))}
                    target="_blank"
                    rel="noreferrer"
                    className="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                >
                    <ExternalLink className="size-3.5" />
                    Ver la verificación pública
                </a>
            )}
        </div>
    );
}

export type Hito = { etiqueta: string; fecha: string | null; hecho: boolean; conHora?: boolean };

/** Las fechas del trámite en línea de tiempo: punto lleno si ya pasó. */
export function LineaTiempo({ hitos }: { hitos: Hito[] }) {
    return (
        <ol className="relative space-y-4 border-l border-border pl-5">
            {hitos.map((h) => (
                <li key={h.etiqueta} className="relative flex items-center justify-between gap-3">
                    <i
                        className={cn(
                            'absolute top-1/2 -left-[25px] size-2.5 -translate-y-1/2 rounded-full ring-4 ring-card',
                            h.hecho ? 'bg-primary' : 'border border-primary bg-card',
                        )}
                        aria-hidden
                    />
                    <span className="text-muted-foreground">{h.etiqueta}</span>
                    <span className="text-right font-medium tabular-nums">
                        {h.fecha ? (h.conHora ? fechaHora(h.fecha) : fecha(h.fecha)) : '—'}
                    </span>
                </li>
            ))}
        </ol>
    );
}

const TONOS_AVISO = {
    info: 'bg-sky-50 text-sky-900 dark:bg-sky-500/10 dark:text-sky-200',
    ok: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200',
    aviso: 'bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200',
    peligro: 'bg-rose-50 text-rose-900 dark:bg-rose-500/10 dark:text-rose-200',
} as const;

/** En qué situación está el trámite, en una frase. El texto llega resuelto del servidor. */
export function Aviso({
    tono,
    icono: Icono,
    titulo,
    children,
}: {
    tono: keyof typeof TONOS_AVISO;
    icono: LucideIcon;
    titulo?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('flex items-start gap-3 rounded-lg p-3 text-sm', TONOS_AVISO[tono])}>
            <Icono className="mt-0.5 size-4 shrink-0" />
            <div>
                {titulo && <p className="font-medium">{titulo}</p>}
                <div className={titulo ? 'opacity-85' : undefined}>{children}</div>
            </div>
        </div>
    );
}

// ----------------------------------------------------------------- Tablas y enlaces

export function BotonPestana({
    activa,
    onClick,
    icono: Icono,
    cantidad,
    children,
}: {
    activa: boolean;
    onClick: () => void;
    icono: LucideIcon;
    cantidad: number;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={activa}
            onClick={onClick}
            className={cn(
                'flex shrink-0 items-center gap-2 border-b-2 px-3 py-3.5 text-sm font-medium transition-colors',
                activa
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground',
            )}
        >
            <Icono className="size-4" />
            {children}
            <span
                className={cn(
                    'rounded-full px-1.5 text-xs tabular-nums',
                    activa ? 'bg-primary text-primary-foreground' : 'bg-secondary text-muted-foreground',
                )}
            >
                {cantidad}
            </span>
        </button>
    );
}

export function Tabla({
    columnas,
    children,
    pie,
}: {
    /** El rótulo de cada columna; las que empiezan con «>» van alineadas a la derecha. */
    columnas: string[];
    children: ReactNode;
    pie?: ReactNode;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="border-b border-border text-left text-xs tracking-wide text-muted-foreground uppercase">
                    <tr>
                        {columnas.map((c, i) => (
                            <th
                                key={`${c}-${i}`}
                                className={cn('px-5 py-2.5 font-medium', c.startsWith('>') && 'text-right')}
                            >
                                {c.replace(/^>/, '')}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">{children}</tbody>
                {pie && <tfoot className="border-t border-border font-medium">{pie}</tfoot>}
            </table>
        </div>
    );
}

export function Td({
    apagada = false,
    derecha = false,
    colSpan,
    children,
}: {
    apagada?: boolean;
    derecha?: boolean;
    colSpan?: number;
    children?: ReactNode;
}) {
    return (
        <td
            colSpan={colSpan}
            className={cn('px-5 py-3', apagada && 'text-muted-foreground', derecha && 'text-right tabular-nums')}
        >
            {children}
        </td>
    );
}

/** Un enlace firmado a S3 y cuándo vence (ver DocumentoEmitidoService::resumen()). */
export type EnlaceDirecto = { url: string; vence: string };

/**
 * Un PDF abre en otra pestaña: el visor del navegador es desde donde se imprime.
 * Con `directo` vigente abre el archivo de S3 sin pasar por Jichi; si no hay o
 * venció, va por `href`, que lo genera (la primera vez) o firma un enlace nuevo.
 */
// Un minuto de margen: el enlace no tiene que vencer mientras el visor lo abre.
function enlaceVigente(directo?: EnlaceDirecto | null): boolean {
    return !!directo && Date.parse(directo.vence) - Date.now() > 60_000;
}

/** Para un <a> que imprime: abre el PDF guardado directo de S3; sin él, sigue a su href (Jichi). */
export function abrirDirecto(directo?: EnlaceDirecto | null) {
    return (e: MouseEvent<HTMLAnchorElement>) => {
        if (enlaceVigente(directo)) {
            e.preventDefault();
            window.open(directo!.url, '_blank', 'noreferrer');
        }
    };
}

export function EnlacePdf({
    href,
    directo = null,
    icono: Icono,
    chico = false,
    className,
    children,
}: {
    href: string;
    directo?: EnlaceDirecto | null;
    icono: LucideIcon;
    chico?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <a
            href={enlaceVigente(directo) ? directo!.url : href}
            target="_blank"
            rel="noreferrer"
            onClick={abrirDirecto(directo)}
            className={cn(buttonVariants({ variant: 'outline', size: chico ? 'sm' : 'default' }), className)}
        >
            <Icono className="size-4" />
            {children}
        </a>
    );
}

/** En otra pestaña: se abre para contrastarlo con esta ficha sin perderla. */
export function EnlaceVer({ href, children = 'Ver' }: { href: string; children?: ReactNode }) {
    return (
        <a href={href} target="_blank" rel="noreferrer" className={cn(buttonVariants({ variant: 'ver', size: 'sm' }))}>
            <ExternalLink className="size-4" />
            {children}
        </a>
    );
}

// ----------------------------------------------------------------- Formularios

/** La banda de un formulario de emisión: qué se registra y los pasos, que se marcan al completarse. */
export function BandaFormulario({
    icono: Icono,
    titulo,
    bajada,
    pasos,
}: {
    icono: LucideIcon;
    titulo: string;
    bajada: string;
    pasos: { texto: string; hecho: boolean }[];
}) {
    return (
        <section
            className="relative overflow-hidden rounded-xl px-5 py-4 text-white shadow-sm sm:px-6"
            style={{
                background:
                    'radial-gradient(500px 160px at 95% 0%, color-mix(in oklch, var(--rio-claro) 30%, transparent), transparent 70%),' +
                    'linear-gradient(120deg, var(--rio) 0%, var(--rio-profundo) 70%)',
            }}
        >
            <div className="flex flex-wrap items-center gap-x-6 gap-y-4">
                <div className="flex min-w-[16rem] flex-1 items-center gap-4">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-white/10 ring-2 ring-white/20">
                        <Icono className="size-5 text-institucional-dorado" />
                    </span>
                    <div className="min-w-0">
                        <p className="text-lg leading-tight font-semibold">{titulo}</p>
                        <p className="mt-0.5 text-sm text-white/65">{bajada}</p>
                    </div>
                </div>

                <ol className="flex flex-wrap items-center gap-2 text-sm">
                    {pasos.map((p, i) => (
                        <li key={p.texto} className="flex items-center gap-2">
                            {i > 0 && (
                                <i
                                    className={cn('h-px w-5', p.hecho ? 'bg-institucional-dorado' : 'bg-white/25')}
                                    aria-hidden
                                />
                            )}
                            <span
                                className={cn(
                                    'flex items-center gap-1.5 rounded-full px-2.5 py-1 ring-1 transition-colors',
                                    p.hecho
                                        ? 'bg-institucional-dorado/20 ring-institucional-dorado/60'
                                        : 'bg-white/5 ring-white/15 text-white/70',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-5 items-center justify-center rounded-full text-xs font-semibold',
                                        p.hecho ? 'bg-institucional-dorado text-rio-profundo' : 'bg-white/15',
                                    )}
                                >
                                    {p.hecho ? '✓' : i + 1}
                                </span>
                                {p.texto}
                            </span>
                        </li>
                    ))}
                </ol>
            </div>
        </section>
    );
}

/** Un bloque numerado del formulario. */
export function SeccionFormulario({
    numero,
    titulo,
    ayuda,
    hecho = false,
    obligatorio = false,
    children,
}: {
    numero: number;
    titulo: string;
    ayuda?: string;
    hecho?: boolean;
    /** El asterisco rojo, como en `Campo`: el servidor también lo exige. */
    obligatorio?: boolean;
    children: ReactNode;
}) {
    return (
        <Card className="min-w-0">
            <CardContent className="space-y-3 p-4 sm:p-5">
                <div className="flex items-start gap-3">
                    <span
                        className={cn(
                            'flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold transition-colors',
                            hecho ? 'bg-primary text-primary-foreground' : 'bg-secondary text-muted-foreground',
                        )}
                    >
                        {hecho ? '✓' : numero}
                    </span>
                    <div>
                        <p className="font-semibold">
                            {titulo}
                            {obligatorio && (
                                <span className="ml-0.5 text-destructive" aria-hidden>
                                    *
                                </span>
                            )}
                        </p>
                        {ayuda && <p className="text-sm text-muted-foreground">{ayuda}</p>}
                    </div>
                </div>
                {children}
            </CardContent>
        </Card>
    );
}

/**
 * Lo que identifica a un carnet al elegirlo en un formulario: número, actividad,
 * asociación y vigencia. `children` es la tercera línea: qué habilita para ese trámite.
 */
export function DatosDelCarnet({
    carnet,
    children,
}: {
    carnet: {
        registro: string | null;
        tipo_actor_etiqueta: string;
        asociacion: string | null;
        asociacion_nombre: string | null;
        fecha_vencimiento: string | null;
    };
    children?: ReactNode;
}) {
    return (
        <>
            <span className="flex items-baseline gap-2 pr-5">
                <span className="font-semibold">
                    {carnet.registro ? `Carnet N° ${carnet.registro}` : carnet.tipo_actor_etiqueta}
                </span>
                <span className="text-xs text-muted-foreground">{carnet.tipo_actor_etiqueta}</span>
            </span>
            <span className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                <span className="flex items-center gap-1" title={carnet.asociacion_nombre ?? undefined}>
                    <Users className="size-3.5" />
                    {carnet.asociacion ?? 'Sin asociación'}
                </span>
                <span className="flex items-center gap-1">
                    <CalendarClock className="size-3.5" />
                    {carnet.fecha_vencimiento ? `Vigente hasta ${fecha(carnet.fecha_vencimiento)}` : 'Sin fecha'}
                </span>
            </span>
            {children && <span className="mt-1 block text-xs">{children}</span>}
        </>
    );
}

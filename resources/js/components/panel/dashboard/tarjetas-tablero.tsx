import { Link } from '@inertiajs/react';
import {
    Activity,
    BadgeCheck,
    BellRing,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    Fish,
    MapPin,
    Trophy,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { EnlacePermitido } from '@/components/panel/comunes/enlace-permitido';
import { Conteo } from '@/components/panel/comunes/ficha';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useMontado } from '@/hooks/use-movimiento';
import { bs, cn, fecha, hace } from '@/lib/utils';
import type {
    Actividad,
    Avisos,
    CasiAgotada,
    Destino,
    Pendiente,
    Pesca,
    TopPescador,
    UltimoCarnet,
} from '@/types/dashboard';
import { COLOR_CONCEPTO, ICONO_CONCEPTO } from './colores';

const kg = (n: number) => `${n.toLocaleString('es-BO', { maximumFractionDigits: 0 })} kg`;

// ----------------------------------------------------------------- Las cifras

/** Una cifra del tablero: cuenta desde cero al abrir y se levanta al pasar el mouse. */
export function Numero({
    icono: Icono,
    tono,
    titulo,
    valor,
    detalle,
    children,
}: {
    icono: LucideIcon;
    /** El color del ícono, escrito entero: Tailwind no ve clases armadas. */
    tono: string;
    titulo: string;
    valor: number;
    detalle: string;
    children?: ReactNode;
}) {
    return (
        <Card className="min-w-0 transition-transform duration-300 hover:-translate-y-0.5 hover:shadow-md motion-reduce:transition-none">
            <CardContent className="p-4 sm:p-5">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-xs text-muted-foreground sm:text-sm">{titulo}</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums sm:text-3xl">
                            <Conteo valor={valor} formato={(n) => String(n)} />
                        </p>
                    </div>
                    <span
                        className={cn('flex size-9 shrink-0 items-center justify-center rounded-xl sm:size-11', tono)}
                    >
                        <Icono className="size-5" />
                    </span>
                </div>
                {children}
                <p className="mt-2 truncate text-xs text-muted-foreground">{detalle}</p>
            </CardContent>
        </Card>
    );
}

/** Dos partes de un total en una barra: pescador y comercializador, faenas y guías. */
export function BarraPartes({ a, b, colorA, colorB }: { a: number; b: number; colorA: string; colorB: string }) {
    const montado = useMontado();
    const total = a + b || 1;

    return (
        <div className="mt-3 flex h-1.5 gap-0.5 overflow-hidden rounded-full bg-secondary">
            <div
                className={cn(
                    'rounded-full transition-[width] duration-1000 ease-out motion-reduce:transition-none',
                    colorA,
                )}
                style={{ width: montado ? `${(a / total) * 100}%` : '0%' }}
            />
            <div
                className={cn(
                    'rounded-full transition-[width] duration-1000 ease-out motion-reduce:transition-none',
                    colorB,
                )}
                style={{ width: montado ? `${(b / total) * 100}%` : '0%' }}
            />
        </div>
    );
}

// ----------------------------------------------------------------- La pesca

/**
 * El medidor de la gestión: cuánto de lo autorizado ya se vendió en guías.
 * Medio círculo dibujado a mano: no hace falta cargar recharts para un arco.
 */
export function MedidorPesca({ pesca }: { pesca: Pesca }) {
    const montado = useMontado();
    const pct = pesca.autorizado > 0 ? Math.min(100, (pesca.vendido / pesca.autorizado) * 100) : 0;
    const queda = 100 - pct;
    // Mismo criterio que la barra de cada autorización: amarillo con la mitad o menos, rojo con el 20 %.
    const color = queda <= 20 ? '#ef4444' : queda <= 50 ? '#f59e0b' : 'var(--primary)';

    return (
        <Card className="min-w-0">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Fish className="size-4.5 text-primary" />
                    La pesca de la gestión
                </CardTitle>
                <CardDescription>
                    {pesca.autorizaciones}{' '}
                    {pesca.autorizaciones === 1 ? 'autorización vigente' : 'autorizaciones vigentes'}. Solo las guías
                    descuentan.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                <div className="relative mx-auto aspect-[200/108] w-full max-w-72">
                    <svg viewBox="0 0 200 108" className="h-full w-full" aria-hidden>
                        <path
                            d="M10 100 A90 90 0 0 1 190 100"
                            fill="none"
                            stroke="var(--secondary)"
                            strokeWidth="14"
                            strokeLinecap="round"
                        />
                        <path
                            d="M10 100 A90 90 0 0 1 190 100"
                            fill="none"
                            stroke={color}
                            strokeWidth="14"
                            strokeLinecap="round"
                            pathLength={100}
                            strokeDasharray={`${montado ? pct : 0} 100`}
                            className="transition-[stroke-dasharray] duration-[1400ms] ease-out motion-reduce:transition-none"
                        />
                    </svg>
                    <div className="absolute inset-x-0 bottom-2 text-center">
                        <p className="text-3xl font-bold tabular-nums">
                            <Conteo valor={Math.round(pct)} formato={(n) => `${n} %`} />
                        </p>
                        <p className="text-xs text-muted-foreground">vendido en guías</p>
                    </div>
                </div>

                <dl className="grid grid-cols-3 gap-2 text-center text-xs">
                    <Dato rotulo="Autorizado" valor={kg(pesca.autorizado)} />
                    <Dato rotulo="Vendido" valor={kg(pesca.vendido)} />
                    <Dato rotulo="Disponible" valor={kg(pesca.disponible)} fuerte />
                </dl>
            </CardContent>
        </Card>
    );
}

function Dato({ rotulo, valor, fuerte = false }: { rotulo: string; valor: string; fuerte?: boolean }) {
    return (
        <div className="rounded-lg bg-secondary/60 px-2 py-2">
            <dt className="text-muted-foreground">{rotulo}</dt>
            <dd className={cn('mt-0.5 truncate font-semibold tabular-nums', fuerte && 'text-primary')}>{valor}</dd>
        </div>
    );
}

// ----------------------------------------------------------------- Lo que pide trabajo

/** Lo que espera el pago en SIREB: cada número lleva al listado ya filtrado. */
export function TrabajoPendiente({ pendientes }: { pendientes: Pendiente[] }) {
    const total = pendientes.reduce((s, p) => s + p.por_pagar, 0);
    const claves = ['aprovechamientos', 'carnets', 'faenas', 'guias'] as const;

    return (
        <Card id="pendientes" className="min-w-0 scroll-mt-24">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ClipboardList className="size-4.5 text-primary" />
                    Esperando el pago
                </CardTitle>
                <CardDescription>
                    {total === 0
                        ? 'Ningún trámite esperando: todo al día.'
                        : 'En Recaudaciones (SIREB). Se aprueban solos al pagarse.'}
                </CardDescription>
            </CardHeader>

            <CardContent className="grid grid-cols-2 gap-2">
                {pendientes.map((p, i) => {
                    const clave = claves[i];
                    const Icono = ICONO_CONCEPTO[clave];
                    const contenido = (
                        <>
                            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <Icono className="size-3.5" style={{ color: COLOR_CONCEPTO[clave] }} />
                                <span className="truncate">{p.documento}</span>
                            </span>
                            <span
                                className={cn(
                                    'mt-1 block text-2xl font-bold tabular-nums',
                                    p.por_pagar > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-muted-foreground/50',
                                )}
                            >
                                {p.por_pagar}
                            </span>
                        </>
                    );
                    const clase = cn(
                        'block rounded-xl border p-3 transition-colors',
                        p.por_pagar > 0
                            ? 'border-amber-300/60 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10'
                            : 'border-border bg-secondary/30',
                    );

                    // Un cero no es enlace; sin permiso del módulo, tampoco (terminaría en 403).
                    return p.por_pagar > 0 && p.url_por_pagar ? (
                        <Link
                            key={p.documento}
                            href={p.url_por_pagar}
                            className={cn(clase, 'hover:bg-amber-100 dark:hover:bg-amber-500/15')}
                        >
                            {contenido}
                        </Link>
                    ) : (
                        <div key={p.documento} className={clase}>
                            {contenido}
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}

/** Lo que vence pronto y las autorizaciones casi agotadas. Solo lo que tiene algo. */
export function Alertas({ avisos, casiAgotadas }: { avisos: Avisos; casiAgotadas: CasiAgotada[] }) {
    const vencen = [
        {
            icono: BadgeCheck,
            cantidad: avisos.carnets_por_vencer,
            texto: `carnet(s) vencen en ${avisos.dias_aviso} días`,
            href: avisos.urls.carnets,
        },
        {
            icono: CalendarClock,
            cantidad: avisos.autorizaciones_por_vencer,
            texto: `autorización(es) vencen en ${avisos.dias_aviso} días`,
            href: avisos.urls.autorizaciones,
        },
    ].filter((a) => a.cantidad > 0);
    const nada = vencen.length === 0 && casiAgotadas.length === 0;

    return (
        <Card className="min-w-0">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <BellRing className="size-4.5 text-primary" />
                    Alertas
                </CardTitle>
                <CardDescription>Lo que vence pronto o se está quedando sin kilos.</CardDescription>
            </CardHeader>

            <CardContent className="space-y-2">
                {nada && (
                    <p className="flex items-start gap-2 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
                        <CheckCircle2 className="mt-0.5 size-4.5 shrink-0" />
                        Nada por vencer y ninguna autorización casi agotada.
                    </p>
                )}

                {vencen.map((a) => {
                    const contenido = (
                        <>
                            <a.icono className="size-4 shrink-0 text-amber-700 dark:text-amber-300" />
                            <span className="text-sm">
                                <strong className="tabular-nums">{a.cantidad}</strong> {a.texto}
                            </span>
                        </>
                    );
                    const clase =
                        'flex items-center gap-2.5 rounded-lg border border-amber-600/20 bg-amber-50 px-3 py-2.5 dark:bg-amber-500/10';
                    return a.href ? (
                        <Link
                            key={a.texto}
                            href={a.href}
                            className={cn(clase, 'hover:bg-amber-100 dark:hover:bg-amber-500/15')}
                        >
                            {contenido}
                        </Link>
                    ) : (
                        <div key={a.texto} className={clase}>
                            {contenido}
                        </div>
                    );
                })}

                {casiAgotadas.length > 0 && (
                    <div className="space-y-2 pt-1">
                        <p className="flex items-center gap-1.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                            <TriangleAlert className="size-3.5 text-red-500" /> Casi agotadas (20 % o menos)
                        </p>
                        {casiAgotadas.map((a) => (
                            <EnlacePermitido
                                key={a.id}
                                permiso="aprovechamientos.ver"
                                href={route('aprovechamientos.show', a.id)}
                                className="flex items-center gap-2.5 rounded-lg px-1 py-1 hover:bg-secondary/60"
                            >
                                <Retrato url={a.foto} nombre={a.beneficiario ?? '—'} className="size-8" />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-medium">{a.beneficiario ?? '—'}</span>
                                    <span className="mt-1 block h-1 overflow-hidden rounded-full bg-secondary">
                                        <span
                                            className="block h-full rounded-full bg-red-500"
                                            style={{ width: `${a.total > 0 ? (a.disponible / a.total) * 100 : 0}%` }}
                                        />
                                    </span>
                                </span>
                                <span className="text-xs font-medium text-red-700 tabular-nums dark:text-red-300">
                                    {kg(a.disponible)}
                                </span>
                            </EnlacePermitido>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

/** Lo último que se aprobó: cada recibo es un trámite pagado en SIREB. */
export function ActividadReciente({ actividad, moneda }: { actividad: Actividad[]; moneda: string }) {
    return (
        <Card className="min-w-0">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Activity className="size-4.5 text-primary" />
                    Actividad reciente
                </CardTitle>
                <CardDescription>Los últimos trámites pagados y aprobados.</CardDescription>
            </CardHeader>

            <CardContent>
                {actividad.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Todavía no se aprobó ningún trámite.</p>
                ) : (
                    <ol className="relative space-y-3 border-l border-border pl-5">
                        {actividad.map((a) => {
                            const Icono = a.tipo ? ICONO_CONCEPTO[a.tipo] : CheckCircle2;
                            const color = a.tipo ? COLOR_CONCEPTO[a.tipo] : 'var(--muted-foreground)';
                            const titulo = (
                                <span className="block truncate text-sm font-medium">{a.concepto ?? 'Trámite'}</span>
                            );

                            return (
                                <li key={a.id} className="relative">
                                    <span
                                        className="absolute top-0.5 -left-[31px] flex size-5 items-center justify-center rounded-full ring-4 ring-card"
                                        style={{ backgroundColor: color }}
                                    >
                                        <Icono className="size-3 text-white" />
                                    </span>
                                    <div className="flex items-start gap-2">
                                        <div className="min-w-0 flex-1">
                                            {a.tipo ? (
                                                <EnlacePermitido
                                                    permiso={`${a.tipo}.ver`}
                                                    href={route(`${a.tipo}.show`, a.documento_id)}
                                                    className="hover:underline"
                                                >
                                                    {titulo}
                                                </EnlacePermitido>
                                            ) : (
                                                titulo
                                            )}
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {a.beneficiario ?? '—'} · {hace(a.cuando)}
                                            </span>
                                        </div>
                                        <span className="text-sm font-medium whitespace-nowrap text-emerald-700 tabular-nums dark:text-emerald-400">
                                            +{bs(a.monto, moneda)}
                                        </span>
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}

// ----------------------------------------------------------------- Rankings

/** Una fila de ranking con su barra, que se llena en cascada al abrir. */
function FilaRanking({
    indice,
    inicio,
    nombre,
    detalle,
    valor,
    pct,
}: {
    indice: number;
    inicio: ReactNode;
    nombre: ReactNode;
    detalle: string;
    valor: string;
    pct: number;
}) {
    const montado = useMontado();

    return (
        <li className="flex items-center gap-3">
            {inicio}
            <div className="min-w-0 flex-1">
                <div className="flex items-baseline justify-between gap-2">
                    <span className="truncate text-sm font-medium">{nombre}</span>
                    <span className="text-sm font-semibold whitespace-nowrap tabular-nums">{valor}</span>
                </div>
                <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-secondary">
                    <div
                        className="h-full rounded-full bg-gradient-to-r from-rio-claro to-rio transition-[width] duration-1000 ease-out motion-reduce:transition-none"
                        style={{ width: montado ? `${pct}%` : '0%', transitionDelay: `${indice * 100}ms` }}
                    />
                </div>
                <p className="mt-0.5 truncate text-xs text-muted-foreground">{detalle}</p>
            </div>
        </li>
    );
}

export function TopPescadores({ pescadores }: { pescadores: TopPescador[] }) {
    const mayor = pescadores[0]?.vendido || 1;

    return (
        <Card className="min-w-0">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Trophy className="size-4.5 text-institucional-dorado" />
                    Los que más vendieron
                </CardTitle>
                <CardDescription>Kilos vendidos en guías, con su autorización vigente.</CardDescription>
            </CardHeader>
            <CardContent>
                {pescadores.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Ninguna guía descontó kilos todavía.</p>
                ) : (
                    <ol className="space-y-3.5">
                        {pescadores.map((p, i) => (
                            <FilaRanking
                                key={p.id}
                                indice={i}
                                inicio={
                                    <span className="relative">
                                        <Retrato url={p.foto} nombre={p.beneficiario ?? '—'} className="size-9" />
                                        <span className="absolute -top-1 -left-1 flex size-4 items-center justify-center rounded-full bg-institucional-dorado text-[10px] font-bold text-white">
                                            {i + 1}
                                        </span>
                                    </span>
                                }
                                nombre={
                                    <EnlacePermitido
                                        permiso="beneficiarios.ver"
                                        href={route('beneficiarios.show', p.beneficiario_id)}
                                        className="hover:underline"
                                    >
                                        {p.beneficiario ?? '—'}
                                    </EnlacePermitido>
                                }
                                detalle={`de ${kg(p.total)} autorizados`}
                                valor={kg(p.vendido)}
                                pct={(p.vendido / mayor) * 100}
                            />
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}

export function Destinos({ destinos, gestion }: { destinos: Destino[]; gestion: number }) {
    const mayor = destinos[0]?.kilos || 1;

    return (
        <Card className="min-w-0">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <MapPin className="size-4.5 text-primary" />
                    Adónde va el pescado
                </CardTitle>
                <CardDescription>Destinos de las guías de la gestión {gestion}.</CardDescription>
            </CardHeader>
            <CardContent>
                {destinos.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Todavía no salió ninguna guía este año.</p>
                ) : (
                    <ol className="space-y-3.5">
                        {destinos.map((d, i) => (
                            <FilaRanking
                                key={d.destino}
                                indice={i}
                                inicio={
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-secondary text-primary">
                                        <MapPin className="size-4" />
                                    </span>
                                }
                                nombre={d.destino}
                                detalle={`${d.guias} ${d.guias === 1 ? 'guía' : 'guías'}`}
                                valor={kg(d.kilos)}
                                pct={(d.kilos / mayor) * 100}
                            />
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}

/** Las últimas credenciales emitidas, en lista corta. */
export function UltimosCarnets({ carnets }: { carnets: UltimoCarnet[] }) {
    return (
        <Card className="min-w-0">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <BadgeCheck className="size-4.5 text-primary" />
                    Últimos carnets
                </CardTitle>
                <CardDescription>Las credenciales más nuevas.</CardDescription>
            </CardHeader>
            <CardContent>
                {carnets.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Todavía no se emitió ningún carnet.</p>
                ) : (
                    <ul className="divide-y divide-border">
                        {carnets.slice(0, 6).map((c) => (
                            <li key={c.id} className="flex items-center gap-2 py-2 first:pt-0 last:pb-0">
                                <div className="min-w-0 flex-1">
                                    <EnlacePermitido
                                        permiso="carnets.ver"
                                        href={route('carnets.show', c.id)}
                                        className="block truncate text-sm font-medium hover:underline"
                                    >
                                        {c.beneficiario ?? '—'}
                                    </EnlacePermitido>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {c.asociacion ?? 'Sin asociación'} · {fecha(c.fecha_emision) || 'sin emitir'}
                                    </span>
                                </div>
                                <Badge color={c.tipo_actor_color}>{c.tipo_actor_etiqueta}</Badge>
                                <Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

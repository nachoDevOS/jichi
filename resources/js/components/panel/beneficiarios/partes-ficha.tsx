import { Link } from '@inertiajs/react';
import { CalendarClock, Eye, History, Printer, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { EnlacePermitido } from '@/components/panel/comunes/enlace-permitido';
import { abrirDirecto } from '@/components/panel/comunes/ficha';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { useMontado } from '@/hooks/use-movimiento';
import { usePermisos } from '@/hooks/use-permisos';
import { bs, cn, fecha } from '@/lib/utils';
import type { CarnetResumen } from '@/types/beneficiarios';

/** Quién es el titular: la réplica del carnet lleva su foto, nombre y cédula. */
export interface Titular {
    nombre: string;
    documento: string;
    foto: string | null;
}

// El verde del carnet impreso (CarnetImpresionController): la réplica se ve igual que el plástico.
const VERDE = 'bg-[#0c6b32]';

/**
 * El carnet tal como sale impreso, en miniatura. Mide en `cqw` (ancho del
 * contenedor): achica todo junto y no se desarma en el celular.
 */
export function CarnetReplica({ carnet, titular }: { carnet: CarnetResumen; titular: Titular }) {
    const sinVigor = !carnet.vigente;
    const franja = [
        { rotulo: 'REGISTRO', valor: `N° ${carnet.registro ?? '—'}` },
        ...(carnet.cupo_kg !== null ? [{ rotulo: 'CUPO', valor: `${carnet.cupo_kg} KG` }] : []),
        { rotulo: 'VÁLIDO HASTA', valor: fecha(carnet.fecha_vencimiento) || '—' },
    ];

    return (
        <EnlacePermitido
            permiso="carnets.ver"
            href={route('carnets.show', carnet.id)}
            className="group block @container"
        >
            <div
                className={cn(
                    'relative aspect-[243/153] overflow-hidden rounded-[3.5cqw] bg-white text-[#1f2a24] shadow-lg ring-1 ring-black/5 transition-transform duration-300 group-hover:-translate-y-1 group-hover:shadow-xl motion-reduce:transition-none',
                    sinVigor && 'grayscale-[0.85]',
                )}
            >
                {/* La banda verde con «GOBERNACIÓN» girado y el filete dorado. */}
                <div
                    className={cn(
                        'absolute inset-y-0 left-0 flex w-[9cqw] items-center justify-center border-r-[0.8cqw] border-[#c9a84c]',
                        VERDE,
                    )}
                >
                    <span className="rotate-180 text-[4.3cqw] font-bold tracking-[0.08em] text-white [writing-mode:vertical-rl]">
                        GOBERNACIÓN
                    </span>
                </div>

                <div className="absolute inset-y-0 right-0 left-[9.8cqw] flex flex-col px-[3cqw] pt-[2.2cqw] pb-[3cqw]">
                    <div className="flex items-center gap-[1.5cqw]">
                        <img src="/image/carnet-escudo-solo.png" alt="" className="h-[11cqw] w-auto" />
                        <span className="h-[8cqw] w-[0.3cqw] bg-[#0c6b32]/60" />
                        <img src="/image/carnet-beni.png" alt="" className="h-[9cqw] w-auto" />
                        <div className="ml-auto text-right leading-tight">
                            <p className="text-[2cqw] tracking-wide text-[#5f6b64] uppercase">
                                Servicio Departamental Agropecuario
                            </p>
                            <p className="text-[4.2cqw] font-bold text-[#0c6b32]">SEDAG - BENI</p>
                            <p className="text-[2cqw] text-[#5f6b64]">Unidad de Pesca</p>
                        </div>
                    </div>
                    <span className="mt-[1.6cqw] h-[0.5cqw] w-full bg-[#0c6b32]" />

                    <div className="mt-[2.4cqw] flex min-h-0 flex-1 gap-[3cqw]">
                        <div className="flex w-[25cqw] shrink-0 flex-col items-center">
                            <div className={cn('rounded-full p-[0.7cqw]', VERDE)}>
                                <div className="rounded-full bg-white p-[0.6cqw]">
                                    <Retrato
                                        url={titular.foto}
                                        nombre={titular.nombre}
                                        className="size-[20cqw] [&_svg]:size-[8cqw]"
                                    />
                                </div>
                            </div>
                            <p className="mt-[1.4cqw] text-[2.6cqw] font-bold whitespace-nowrap">
                                C.I.: {titular.documento}
                            </p>
                        </div>

                        <div className="flex min-w-0 flex-1 flex-col">
                            {/* «COMERCIALIZADOR» es largo: más chico, para que el título entre en un renglón. */}
                            <p
                                className={cn(
                                    'leading-none font-bold tracking-wide whitespace-nowrap text-[#0c6b32] uppercase',
                                    carnet.tipo_actor === 'comercializador' ? 'text-[2.85cqw]' : 'text-[3.6cqw]',
                                )}
                            >
                                Cédula de {carnet.tipo_actor_etiqueta}
                            </p>
                            <p className="mt-[1cqw] line-clamp-2 text-[3.4cqw] leading-tight font-bold uppercase">
                                {titular.nombre}
                            </p>
                            <p className="mt-[1.4cqw] text-[1.9cqw] font-bold tracking-wider text-[#5f6b64]">
                                ASOCIACIÓN
                            </p>
                            <p className="truncate text-[2.7cqw] font-semibold">
                                {carnet.asociacion ?? 'Sin asociación'}
                            </p>

                            <div className="mt-auto grid auto-cols-fr grid-flow-col gap-[1.5cqw] rounded-[1.5cqw] bg-[#e7f2ea] px-[2cqw] py-[1.3cqw]">
                                {franja.map((f) => (
                                    <div key={f.rotulo} className="min-w-0">
                                        <p className="truncate text-[1.7cqw] font-bold tracking-wider text-[#5f6b64]">
                                            {f.rotulo}
                                        </p>
                                        <p className="truncate text-[2.8cqw] font-bold text-[#0c6b32] tabular-nums">
                                            {f.valor}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>

                {/* El brillo que cruza al pasar el mouse: se siente plástico. */}
                <span className="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/45 to-transparent transition-transform duration-700 group-hover:translate-x-full motion-reduce:hidden" />

                {/* Sin vigor no se confunde con el que vale: va apagado y con la cinta. */}
                {sinVigor && (
                    <span className="absolute top-[6cqw] -right-[11cqw] w-[44cqw] rotate-45 bg-rose-600 py-[0.8cqw] text-center text-[2.4cqw] font-bold tracking-wider text-white uppercase shadow">
                        {carnet.estado_etiqueta}
                    </span>
                )}
            </div>
        </EnlacePermitido>
    );
}

/** Debajo de la réplica: cuánto le queda, si debe algo y los botones. */
export function PieCarnet({ carnet, moneda }: { carnet: CarnetResumen; moneda: string }) {
    const { puede } = usePermisos();
    const montado = useMontado();
    const dias = carnet.dias_para_vencer;
    // Lo que queda de la vigencia: de la emisión al vencimiento.
    const total = diasEntre(carnet.fecha_emision, carnet.fecha_vencimiento);
    const pct = total && dias !== null ? Math.max(0, Math.min(100, (dias / total) * 100)) : 0;
    const aviso = dias !== null && dias <= 30;

    return (
        <div className="space-y-2.5 rounded-xl border border-border bg-card p-3">
            {carnet.vigente && dias !== null ? (
                <div className="space-y-1.5">
                    <div className="flex items-center justify-between text-xs">
                        <span className="flex items-center gap-1.5 text-muted-foreground">
                            <CalendarClock className="size-3.5" /> Vigencia
                        </span>
                        <span className={cn('font-medium tabular-nums', aviso && 'text-amber-700 dark:text-amber-300')}>
                            {dias === 0 ? 'Vence hoy' : `Quedan ${dias} ${dias === 1 ? 'día' : 'días'}`}
                        </span>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-secondary">
                        <div
                            className={cn(
                                'h-full rounded-full transition-[width] duration-1000 ease-out motion-reduce:transition-none',
                                aviso ? 'bg-amber-400' : 'bg-emerald-500',
                            )}
                            style={{ width: montado ? `${pct}%` : '0%' }}
                        />
                    </div>
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {carnet.motivo_sin_permisos ?? `${carnet.estado_etiqueta}: hoy no habilita.`}
                </p>
            )}

            {carnet.saldo_pendiente > 0 && (
                <p className="rounded-md bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    Debe {bs(carnet.saldo_pendiente, moneda)} en Recaudaciones
                </p>
            )}

            <div className="flex flex-wrap items-center gap-2">
                {puede('carnets.imprimir') && carnet.puede_imprimirse && (
                    <a
                        href={route('carnets.imprimir', carnet.id)}
                        target="_blank"
                        rel="noreferrer"
                        onClick={abrirDirecto(carnet.pdf_enlace)}
                        className={cn(buttonVariants({ variant: 'outline', size: 'sm' }))}
                    >
                        <Printer className="size-4" /> Imprimir carnet
                    </a>
                )}
                {puede('carnets.ver') && (
                    <Link
                        href={route('carnets.show', carnet.id)}
                        className={cn(buttonVariants({ variant: 'ver', size: 'sm' }))}
                    >
                        <Eye className="size-4" /> Ver ficha
                    </Link>
                )}
                <span className="ml-auto font-mono text-xs tracking-wider text-muted-foreground">{carnet.codigo}</span>
            </div>
        </div>
    );
}

/** Días entre dos fechas 'AAAA-MM-DD', contadas en UTC las dos: el huso no mueve el resultado. */
export function diasEntre(desde: string | null, hasta: string | null): number | null {
    if (!desde || !hasta) return null;
    const dias = Math.round((Date.parse(hasta.slice(0, 10)) - Date.parse(desde.slice(0, 10))) / 86400000);
    return Number.isNaN(dias) ? null : dias;
}

export interface CifraTira {
    icono: LucideIcon;
    etiqueta: string;
    valor: ReactNode;
    pie: string;
    /** Aviso en amarillo, peligro en rojo: la cifra pide atención. */
    tono?: 'normal' | 'aviso' | 'peligro';
}

const TONOS_TIRA = {
    normal: { valor: '', icono: 'bg-secondary text-primary' },
    aviso: { valor: 'text-amber-700 dark:text-amber-300', icono: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15' },
    peligro: { valor: 'text-red-700 dark:text-red-300', icono: 'bg-red-100 text-red-700 dark:bg-red-500/15' },
} as const;

/** Las cifras del período en una sola franja: se leen de corrido y ocupan poco. */
export function TiraCifras({ cifras }: { cifras: CifraTira[] }) {
    return (
        <div className="grid grid-cols-2 overflow-hidden rounded-xl border border-border bg-card shadow-sm lg:grid-cols-4">
            {cifras.map((c, i) => {
                const tono = TONOS_TIRA[c.tono ?? 'normal'];
                return (
                    <div
                        key={c.etiqueta}
                        className={cn(
                            'flex min-w-0 items-center gap-3 px-4 py-3',
                            i % 2 === 1 && 'border-l border-border',
                            i >= 2 && 'border-t border-border lg:border-t-0',
                            i === 2 && 'lg:border-l',
                        )}
                    >
                        <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-lg', tono.icono)}>
                            <c.icono className="size-4" />
                        </span>
                        <div className="min-w-0">
                            <p className="truncate text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                {c.etiqueta}
                            </p>
                            <p className={cn('truncate text-lg leading-tight font-semibold tabular-nums', tono.valor)}>
                                {c.valor}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">{c.pie}</p>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

/** Un número grande con su rótulo, para los resúmenes de cada pestaña. */
export function Cifra({
    icono: Icono,
    etiqueta,
    valor,
    detalle,
    className,
}: {
    icono: LucideIcon;
    etiqueta: string;
    valor: string;
    detalle?: string;
    className?: string;
}) {
    return (
        <div className={cn('min-w-0 rounded-lg border border-border bg-card p-4', className)}>
            <div className="flex items-center gap-2 text-xs uppercase tracking-wide text-muted-foreground">
                <Icono className="size-4" />
                {etiqueta}
            </div>
            <p className="mt-1 truncate text-2xl font-semibold tabular-nums">{valor}</p>
            {detalle && <p className="truncate text-xs text-muted-foreground">{detalle}</p>}
        </div>
    );
}

/** Los carnets anteriores de la actividad: vencidos, revocados o en trámite. */
export function HistorialCarnets({ carnets, moneda }: { carnets: CarnetResumen[]; moneda: string }) {
    if (carnets.length === 0) return null;

    return (
        <ul className="divide-y divide-border rounded-lg border border-border">
            {carnets.map((c) => (
                <li key={c.id} className="flex flex-wrap items-center gap-3 px-4 py-2.5 text-sm">
                    <EnlacePermitido
                        permiso="carnets.ver"
                        href={route('carnets.show', c.id)}
                        className="font-mono font-medium tabular-nums text-primary hover:underline"
                    >
                        {c.registro ? `N° ${c.registro}` : c.codigo}
                    </EnlacePermitido>
                    <Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>
                    <span className="text-muted-foreground">
                        {c.asociacion ?? '—'} · vence {fecha(c.fecha_vencimiento)}
                    </span>
                    <span className="ml-auto tabular-nums">
                        {c.saldo_pendiente > 0 ? (
                            <span className="text-amber-700 dark:text-amber-400">
                                debe {bs(c.saldo_pendiente, moneda)}
                            </span>
                        ) : (
                            <span className="text-emerald-700 dark:text-emerald-400">Pagado</span>
                        )}
                    </span>
                </li>
            ))}
        </ul>
    );
}

/** Encabezado de sección dentro de una pestaña. */
export function Seccion({ titulo, accion, children }: { titulo: string; accion?: ReactNode; children: ReactNode }) {
    return (
        <section className="min-w-0 space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">{titulo}</h3>
                {accion}
            </div>
            {children}
        </section>
    );
}

/** Un período en la fila de arriba de la pestaña. Lo arman Pescador y Comercializador. */
export function TarjetaPeriodo({
    gestion,
    esActual,
    activo,
    insignia,
    titulo,
    detalle,
    onClick,
}: {
    gestion: number | null;
    esActual: boolean;
    activo: boolean;
    insignia?: ReactNode;
    titulo: string;
    detalle: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={activo}
            className={cn(
                'min-w-56 shrink-0 rounded-lg border bg-card p-3 text-left transition-colors',
                activo ? 'border-primary ring-2 ring-primary/20' : 'border-border hover:bg-secondary/50',
            )}
        >
            <div className="flex items-center gap-2">
                <span className="text-lg font-semibold tabular-nums">{gestion ?? '—'}</span>
                {esActual && <Badge color="sky">Actual</Badge>}
                {insignia}
            </div>
            <p className="mt-1 text-sm">{titulo}</p>
            <p className="text-xs text-muted-foreground">{detalle}</p>
        </button>
    );
}

/** El aviso de que lo que se ve es de otra gestión, con el atajo de vuelta. */
export function AvisoRegistroAnterior({ gestion, onVolver }: { gestion: number | null; onVolver: () => void }) {
    return (
        <div className="flex flex-wrap items-center gap-3 rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm dark:border-slate-500/40 dark:bg-slate-500/10">
            <History className="size-4 shrink-0 text-slate-600 dark:text-slate-300" />
            <span>
                Está viendo un <strong>registro anterior</strong>
                {gestion !== null && <> (gestión {gestion})</>}. Es solo de consulta.
            </span>
            <button type="button" onClick={onVolver} className="ml-auto font-medium text-primary hover:underline">
                Volver al actual
            </button>
        </div>
    );
}

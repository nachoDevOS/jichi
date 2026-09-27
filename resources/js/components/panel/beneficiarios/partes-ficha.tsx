import { Link } from '@inertiajs/react';
import { History, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { bs, cn, fecha } from '@/lib/utils';
import type { TipoActor } from '@/types';
import type { CarnetResumen } from '@/types/beneficiarios';

/** Los colores de cada actividad, escritos enteros: Tailwind no ve clases armadas. */
export const TONOS: Record<TipoActor, { tarjeta: string; suave: string; texto: string; punto: string }> = {
    pescador: {
        tarjeta: 'bg-gradient-to-br from-sky-600 to-sky-800 dark:from-sky-700 dark:to-sky-950',
        suave: 'bg-sky-50 dark:bg-sky-500/10',
        texto: 'text-sky-700 dark:text-sky-300',
        punto: 'bg-sky-500',
    },
    comercializador: {
        tarjeta: 'bg-gradient-to-br from-violet-600 to-violet-800 dark:from-violet-700 dark:to-violet-950',
        suave: 'bg-violet-50 dark:bg-violet-500/10',
        texto: 'text-violet-700 dark:text-violet-300',
        punto: 'bg-violet-500',
    },
};

/**
 * La credencial en miniatura: los datos que se dictan en el mostrador.
 * No es la vista previa del plástico —esa es `vista-previa-carnet.tsx`—.
 */
export function CredencialMini({ carnet, moneda }: { carnet: CarnetResumen; moneda: string }) {
    const tono = TONOS[carnet.tipo_actor];
    const dias = carnet.dias_para_vencer;

    return (
        <Link
            href={route('carnets.show', carnet.id)}
            className={cn(
                'group relative block overflow-hidden rounded-xl p-5 text-white shadow-md transition-transform hover:-translate-y-0.5',
                // Sin vigencia se apaga: a simple vista no se confunde con la que vale.
                carnet.vigente ? tono.tarjeta : 'bg-gradient-to-br from-slate-500 to-slate-700 dark:from-slate-600 dark:to-slate-800',
            )}
        >
            {/* Dos círculos de adorno: le dan cuerpo de tarjeta sin cargar una imagen. */}
            <span className="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-white/10" />
            <span className="pointer-events-none absolute -bottom-16 -right-2 size-40 rounded-full bg-white/5" />

            <div className="relative flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-xs uppercase tracking-widest text-white/70">{carnet.tipo ?? 'Carnet'}</p>
                    <p className="mt-1 font-mono text-2xl font-semibold tabular-nums">
                        N° {carnet.registro ?? '—'}
                    </p>
                </div>
                <span className="rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-medium backdrop-blur">
                    {carnet.estado_etiqueta}
                </span>
            </div>

            <p className="relative mt-4 font-mono text-sm tracking-wider text-white/90">{carnet.codigo}</p>

            <dl className="relative mt-4 grid grid-cols-3 gap-3 text-xs">
                <div>
                    <dt className="text-white/60">Asociación</dt>
                    <dd className="truncate font-medium">{carnet.asociacion ?? '—'}</dd>
                </div>
                <div>
                    <dt className="text-white/60">Vence</dt>
                    <dd className="font-medium tabular-nums">{fecha(carnet.fecha_vencimiento)}</dd>
                </div>
                <div>
                    <dt className="text-white/60">{carnet.cupo_kg !== null ? 'Cupo' : 'Cobro'}</dt>
                    <dd className="font-medium tabular-nums">
                        {carnet.cupo_kg !== null
                            ? `${carnet.cupo_kg} kg`
                            : carnet.saldo_pendiente > 0
                              ? `debe ${bs(carnet.saldo_pendiente, moneda)}`
                              : 'Pagado'}
                    </dd>
                </div>
            </dl>

            {/* Solo mientras vale: sobre uno vencido o revocado la cuenta no dice nada útil. */}
            {carnet.vigente && dias !== null && (
                <p className="relative mt-4 text-xs text-white/80">
                    {dias === 0 ? 'Vence hoy' : `Quedan ${dias} ${dias === 1 ? 'día' : 'días'} de vigencia`}
                </p>
            )}
        </Link>
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
                    <Link
                        href={route('carnets.show', c.id)}
                        className="font-mono font-medium tabular-nums text-primary hover:underline"
                    >
                        {c.registro ? `N° ${c.registro}` : c.codigo}
                    </Link>
                    <Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>
                    <span className="text-muted-foreground">
                        {c.asociacion ?? '—'} · vence {fecha(c.fecha_vencimiento)}
                    </span>
                    <span className="ml-auto tabular-nums">
                        {c.saldo_pendiente > 0 ? (
                            <span className="text-amber-700 dark:text-amber-400">debe {bs(c.saldo_pendiente, moneda)}</span>
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

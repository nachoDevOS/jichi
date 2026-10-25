import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import { ChevronRight, Clock, FileCheck2, Wallet, type LucideIcon } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { CatalogoTramites } from '@/components/portal/catalogo-tramites';
import { FilaPapel } from '@/components/portal/fila-papel';
import { Vacio } from '@/components/portal/piezas';
import { QueHacerAhora } from '@/components/portal/que-hacer-ahora';
import LayoutPortal from '@/layouts/layout-portal';
import { bs, cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { PapelPortal, TramiteDisponible } from '@/types/portal';

/**
 * Qué hacer ahora (lo más urgente, en una tarjeta), sus cifras, lo que vale hoy y el
 * camino de trámites. Se refresca solo cada minuto, como «En curso»: un pago validado
 * aparece sin recargar.
 */
export default function Inicio({
    nombre,
    vigentes,
    tramites_disponibles,
    abiertos,
    deuda,
}: {
    nombre: string;
    vigentes: PapelPortal[];
    tramites_disponibles: TramiteDisponible[];
    abiertos: PapelPortal[];
    deuda: number;
}) {
    usePoll(60_000, { only: ['vigentes', 'tramites_disponibles', 'abiertos', 'deuda'] });
    const { institucion } = usePage<PageProps>().props;
    const primerNombre = nombre.split(' ')[0];
    // Lo ya pagado que espera validación no se cuenta como deuda: la tarjeta dice «Ya pagó».
    const enRevision = (t: PapelPortal) => t.pago === 'revision' || t.pago === 'validado';
    const porPagar = abiertos.filter((t) => !enRevision(t)).reduce((suma, t) => suma + t.debe, 0);
    const revisando = abiertos.filter(enRevision).reduce((suma, t) => suma + t.debe, 0);
    const hoy = new Date().toLocaleDateString('es-BO', { day: 'numeric', month: 'long', year: 'numeric' });

    return (
        <LayoutPortal titulo={`Hola, ${primerNombre}`} bajada={`Su situación al ${hoy}`}>
            <Head title="Mi cuenta" />

            <QueHacerAhora abiertos={abiertos} vigentes={vigentes} deuda={porPagar} moneda={institucion.moneda} />

            <div className="mt-4 grid grid-cols-3 gap-2 sm:gap-3">
                <Cifra
                    href={route('portal.papeles')}
                    icono={FileCheck2}
                    valor={String(vigentes.length)}
                    rotulo={vigentes.length === 1 ? 'Vigente' : 'Vigentes'}
                    tono="text-emerald-700 bg-emerald-100"
                />
                <Cifra
                    href={route('portal.en-curso')}
                    icono={Clock}
                    valor={String(abiertos.length)}
                    rotulo="En curso"
                    tono="text-sky-700 bg-sky-100"
                />
                <Cifra
                    href={route('portal.en-curso')}
                    icono={Wallet}
                    valor={bs(porPagar > 0 || revisando === 0 ? porPagar : revisando, institucion.moneda)}
                    rotulo={porPagar > 0 || revisando === 0 ? 'Por pagar' : 'En revisión'}
                    tono={
                        porPagar > 0
                            ? 'text-amber-800 bg-amber-100'
                            : revisando > 0
                              ? 'text-sky-700 bg-sky-100'
                              : 'text-slate-500 bg-slate-100'
                    }
                    resaltada={porPagar > 0}
                />
            </div>

            <Seccion titulo="Vigente hoy" cuantos={vigentes.length}>
                {vigentes.length === 0 ? (
                    <Vacio>No tiene ningún documento vigente hoy.</Vacio>
                ) : (
                    <ul className="space-y-2.5">
                        {vigentes.map((p, i) => (
                            <FilaPapel key={`${p.clase}-${p.codigo ?? i}`} papel={p} />
                        ))}
                    </ul>
                )}
            </Seccion>

            <CatalogoTramites disponibles={tramites_disponibles} vigentes={vigentes} abiertos={abiertos} />
        </LayoutPortal>
    );
}

/** Una cifra que lleva a su pestaña: se toca para ver el detalle. */
function Cifra({
    href,
    icono: Icono,
    valor,
    rotulo,
    tono,
    resaltada = false,
}: {
    href: string;
    icono: LucideIcon;
    valor: string;
    rotulo: string;
    tono: string;
    resaltada?: boolean;
}) {
    return (
        <Link
            href={href}
            className={cn(
                'group flex min-w-0 flex-col gap-2 rounded-2xl bg-white p-3 shadow-sm ring-1 transition-all hover:-translate-y-0.5 hover:shadow-md sm:p-4',
                resaltada ? 'ring-amber-300' : 'ring-slate-200/80',
            )}
        >
            <span className="flex items-center justify-between">
                <span className={cn('flex size-8 items-center justify-center rounded-lg', tono)}>
                    <Icono className="size-4" />
                </span>
                <ChevronRight className="size-4 text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-slate-500" />
            </span>
            <span className="min-w-0">
                <span className="block text-base leading-tight font-extrabold break-words text-rio-profundo tabular-nums sm:text-2xl">
                    {valor}
                </span>
                <span className="block text-xs font-semibold text-slate-500">{rotulo}</span>
            </span>
        </Link>
    );
}

function Seccion({ titulo, cuantos, children }: PropsWithChildren<{ titulo: string; cuantos?: number }>) {
    return (
        <section className="mt-8 min-w-0">
            <h2 className="mb-2.5 flex items-center gap-2 text-xs font-bold tracking-wider text-slate-500 uppercase">
                {titulo}
                {cuantos !== undefined && cuantos > 0 && (
                    <span className="rounded-full bg-slate-200/70 px-1.5 text-[10px] text-slate-600 tabular-nums">
                        {cuantos}
                    </span>
                )}
            </h2>
            {children}
        </section>
    );
}

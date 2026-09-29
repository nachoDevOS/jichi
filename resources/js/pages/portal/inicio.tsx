import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight, Clock, Info, Wallet } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { ICONO_DOCUMENTO } from '@/components/portal/documento';
import { FilaPapel } from '@/components/portal/fila-papel';
import { EstadoChip, Vacio } from '@/components/portal/piezas';
import LayoutPortal from '@/layouts/layout-portal';
import { bs, cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { PapelPortal, TramiteDisponible } from '@/types/portal';

/**
 * Lo que vale hoy y lo que puede pedir en ventanilla. Lo que se está tramitando
 * va en «En curso»: acá solo cuántos son y si queda algo por pagar.
 */
export default function Inicio({
    nombre,
    vigentes,
    tramites_disponibles,
    en_curso,
    deuda,
}: {
    nombre: string;
    vigentes: PapelPortal[];
    tramites_disponibles: TramiteDisponible[];
    en_curso: number;
    deuda: number;
}) {
    const { institucion } = usePage<PageProps>().props;
    const primerNombre = nombre.split(' ')[0];
    const hoy = new Date().toLocaleDateString('es-BO', { day: 'numeric', month: 'long', year: 'numeric' });

    return (
        <LayoutPortal titulo={`Hola, ${primerNombre}`} bajada={`Su situación al ${hoy}`}>
            <Head title="Mi cuenta" />

            {(en_curso > 0 || deuda > 0) && (
                <Link
                    href={route('portal.en-curso')}
                    className="mb-5 flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm transition-colors hover:bg-slate-50"
                >
                    <Clock className="size-4 shrink-0 text-sky-700" />
                    <span className="min-w-0 flex-1 truncate font-semibold text-rio-profundo">
                        {en_curso === 1 ? '1 trámite en curso' : `${en_curso} trámites en curso`}
                    </span>
                    {deuda > 0 && (
                        <span className="flex shrink-0 items-center gap-1 text-xs font-bold text-amber-700 tabular-nums">
                            <Wallet className="size-3.5" />
                            {bs(deuda, institucion.moneda)} por pagar
                        </span>
                    )}
                    <ChevronRight className="size-4 shrink-0 text-slate-400" />
                </Link>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
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

                <Seccion titulo="Puede tramitar">
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        {tramites_disponibles.length === 0 ? (
                            <p className="p-4 text-sm text-slate-500">Hoy no tiene trámites nuevos para iniciar.</p>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {tramites_disponibles.map((t) => (
                                    <FilaDisponible key={`${t.clase}-${t.titulo}`} tramite={t} />
                                ))}
                            </ul>
                        )}
                        <p className="flex items-start gap-2 border-t border-slate-100 bg-slate-50/70 px-3.5 py-2.5 text-[11px] leading-snug text-slate-500">
                            <Info className="mt-px size-3.5 shrink-0" />
                            Se inician en ventanilla del SEDAG, con su cédula de identidad.
                        </p>
                    </div>
                </Seccion>
            </div>
        </LayoutPortal>
    );
}

function Seccion({ titulo, cuantos, children }: PropsWithChildren<{ titulo: string; cuantos?: number }>) {
    return (
        <section className="min-w-0">
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

/** Un trámite que puede pedir; si no, atenuado y con el porqué. */
function FilaDisponible({ tramite: t }: { tramite: TramiteDisponible }) {
    const { icono: Icono, tono } = ICONO_DOCUMENTO[t.clase];

    return (
        <li className="flex items-center gap-3 px-3.5 py-3">
            <span
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                    t.disponible ? tono : 'bg-slate-100 text-slate-400',
                )}
            >
                <Icono className="size-4" />
            </span>
            <div className="min-w-0 flex-1 leading-tight">
                <p className={cn('text-sm font-bold', t.disponible ? 'text-rio-profundo' : 'text-slate-500')}>
                    {t.titulo}
                </p>
                <p className="mt-0.5 text-xs text-slate-500">{t.detalle}</p>
            </div>
            <EstadoChip color={t.disponible ? 'emerald' : 'slate'}>
                {t.disponible ? 'Disponible' : 'No disponible'}
            </EstadoChip>
        </li>
    );
}

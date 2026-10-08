import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight, Clock, Wallet } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { CatalogoTramites } from '@/components/portal/catalogo-tramites';
import { FilaPapel } from '@/components/portal/fila-papel';
import { Vacio } from '@/components/portal/piezas';
import LayoutPortal from '@/layouts/layout-portal';
import { bs } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { PapelPortal, TramiteDisponible } from '@/types/portal';

/**
 * Lo que vale hoy y la vitrina de trámites (qué puede pedir y qué llevar). Lo que
 * se está tramitando va en «En curso»: acá solo cuántos son y si queda algo por pagar.
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
                        <span className="flex shrink-0 items-center gap-1 text-sm font-bold text-amber-700 tabular-nums">
                            <Wallet className="size-4" />
                            {bs(deuda, institucion.moneda)} por pagar
                        </span>
                    )}
                    <ChevronRight className="size-4 shrink-0 text-slate-400" />
                </Link>
            )}

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

            <CatalogoTramites disponibles={tramites_disponibles} />
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

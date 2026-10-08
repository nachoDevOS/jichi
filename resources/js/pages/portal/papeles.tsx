import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { FilaPapel } from '@/components/portal/fila-papel';
import { Vacio } from '@/components/portal/piezas';
import LayoutPortal from '@/layouts/layout-portal';
import { cn } from '@/lib/utils';
import type { PapelPortal } from '@/types/portal';

type Filtro = 'vigente' | 'vencido' | 'revocado' | 'todos';

const VACIO: Record<Filtro, string> = {
    vigente: 'Hoy no tiene papeles vigentes. Lo que está tramitando aparece en «En curso».',
    vencido: 'No tiene papeles vencidos.',
    revocado: 'No tiene papeles revocados.',
    todos: 'Todavía no tiene papeles aprobados. Lo que está tramitando aparece en «En curso».',
};

/**
 * Todo lo que alguna vez se aprobó, de todas las gestiones. Abre en «Vigentes»:
 * es lo que se muestra en un control. La situación la decide el servidor.
 */
export default function Papeles({ papeles }: { papeles: PapelPortal[] }) {
    const [filtro, setFiltro] = useState<Filtro>('vigente');

    const cuantos = (f: Filtro) => (f === 'todos' ? papeles.length : papeles.filter((p) => p.situacion === f).length);
    const visibles = filtro === 'todos' ? papeles : papeles.filter((p) => p.situacion === filtro);

    const filtros: { clave: Filtro; texto: string }[] = [
        { clave: 'vigente', texto: 'Vigentes' },
        { clave: 'vencido', texto: 'Vencidos' },
        { clave: 'revocado', texto: 'Revocados' },
        { clave: 'todos', texto: 'Todos' },
    ];

    const bajada = [
        `${cuantos('vigente')} ${cuantos('vigente') === 1 ? 'vigente' : 'vigentes'}`,
        cuantos('vencido') > 0 && `${cuantos('vencido')} ${cuantos('vencido') === 1 ? 'vencido' : 'vencidos'}`,
        cuantos('revocado') > 0 && `${cuantos('revocado')} sin validez`,
    ]
        .filter(Boolean)
        .join(' · ');

    const selector = (
        <div
            role="tablist"
            className="inline-flex max-w-full rounded-full bg-white p-1 shadow-sm ring-1 ring-slate-200"
        >
            {filtros.map((f) => (
                <button
                    key={f.clave}
                    type="button"
                    role="tab"
                    aria-selected={filtro === f.clave}
                    onClick={() => setFiltro(f.clave)}
                    className={cn(
                        'flex items-center gap-0.5 rounded-full px-2 py-1 text-[11px] font-semibold whitespace-nowrap transition-colors min-[360px]:gap-1 min-[360px]:px-2.5 min-[360px]:text-xs sm:px-3',
                        filtro === f.clave
                            ? 'bg-rio-profundo text-white shadow-sm'
                            : 'text-slate-600 hover:text-rio-profundo',
                    )}
                >
                    {f.texto}
                    <span className={cn('tabular-nums', filtro === f.clave ? 'text-white/70' : 'text-slate-400')}>
                        {cuantos(f.clave)}
                    </span>
                </button>
            ))}
        </div>
    );

    return (
        <LayoutPortal
            titulo="Mis papeles"
            bajada={papeles.length > 0 ? bajada : undefined}
            extra={papeles.length > 0 ? selector : undefined}
        >
            <Head title="Mis papeles" />

            {visibles.length === 0 ? (
                <Vacio>{VACIO[filtro]}</Vacio>
            ) : (
                <ul className="space-y-2.5">
                    {visibles.map((p, i) => (
                        <FilaPapel key={`${p.clase}-${p.codigo ?? i}`} papel={p} />
                    ))}
                </ul>
            )}
        </LayoutPortal>
    );
}

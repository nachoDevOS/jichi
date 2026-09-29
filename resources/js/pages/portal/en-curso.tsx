import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { FilaTramite } from '@/components/portal/fila-tramite';
import { Vacio } from '@/components/portal/piezas';
import LayoutPortal from '@/layouts/layout-portal';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { PapelPortal } from '@/types/portal';

type Filtro = 'todos' | 'pendientes' | 'revision';

/**
 * Sus trámites abiertos como una lista, cada uno con su línea de avance y qué le
 * falta. El filtro es solo de pantalla: todo llega en una sola respuesta.
 */
export default function EnCurso({ tramites }: { tramites: PapelPortal[] }) {
    const { institucion } = usePage<PageProps>().props;
    const [filtro, setFiltro] = useState<Filtro>('todos');

    const enRevision = tramites.filter((t) => t.en_revision).length;
    const pendientes = tramites.length - enRevision;

    // En revisión primero: es lo que está más cerca de valer.
    const visibles = tramites
        .filter((t) => filtro === 'todos' || (filtro === 'revision' ? t.en_revision : !t.en_revision))
        .sort((a, b) => Number(b.en_revision) - Number(a.en_revision));

    const filtros: { clave: Filtro; texto: string; cuantos: number }[] = [
        { clave: 'todos', texto: 'Todos', cuantos: tramites.length },
        { clave: 'pendientes', texto: 'Pendientes', cuantos: pendientes },
        { clave: 'revision', texto: 'En revisión', cuantos: enRevision },
    ];

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
                        'flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold whitespace-nowrap transition-colors',
                        filtro === f.clave
                            ? 'bg-rio-profundo text-white shadow-sm'
                            : 'text-slate-600 hover:text-rio-profundo',
                    )}
                >
                    {f.texto}
                    <span className={cn('tabular-nums', filtro === f.clave ? 'text-white/70' : 'text-slate-400')}>
                        {f.cuantos}
                    </span>
                </button>
            ))}
        </div>
    );

    return (
        <LayoutPortal
            titulo="En curso"
            bajada={
                tramites.length === 0
                    ? 'Sin trámites abiertos'
                    : `${tramites.length} ${tramites.length === 1 ? 'trámite abierto' : 'trámites abiertos'}`
            }
            extra={tramites.length > 0 ? selector : undefined}
        >
            <Head title="Trámites en curso" />

            {tramites.length === 0 ? (
                <Vacio>No tiene trámites en curso. Todo lo que tramitó ya está resuelto.</Vacio>
            ) : (
                <>
                    {visibles.length === 0 ? (
                        <div>
                            <Vacio>No hay trámites en este grupo.</Vacio>
                        </div>
                    ) : (
                        <ul className="space-y-2.5">
                            {visibles.map((t, i) => (
                                <FilaTramite
                                    key={`${t.clase}-${t.codigo ?? i}`}
                                    tramite={t}
                                    moneda={institucion.moneda}
                                />
                            ))}
                        </ul>
                    )}
                </>
            )}
        </LayoutPortal>
    );
}

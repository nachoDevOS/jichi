import { Head, usePoll } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { FilaTramite } from '@/components/portal/fila-tramite';
import { Vacio } from '@/components/portal/piezas';
import LayoutPortal from '@/layouts/layout-portal';
import type { PapelPortal } from '@/types/portal';

/**
 * Sus trámites abiertos —todos esperan el pago en SIREB—, cada uno con su línea
 * de avance, qué le falta y el código con el que se paga. Se refresca solo cada
 * minuto (solo la lista, y no con la pestaña oculta): el estado lo mueve la
 * verificación con SIREB, cada 10 minutos.
 */
export default function EnCurso({ tramites }: { tramites: PapelPortal[] }) {
    usePoll(60_000, { only: ['tramites'] });

    return (
        <LayoutPortal
            titulo="En curso"
            bajada={
                tramites.length === 0
                    ? 'Sin trámites abiertos'
                    : `${tramites.length} ${tramites.length === 1 ? 'trámite abierto' : 'trámites abiertos'}`
            }
        >
            <Head title="Trámites en curso" />

            <p className="mb-4 flex items-center gap-2 text-sm text-slate-500">
                <RefreshCw className="size-4 text-rio" />
                Esta página se actualiza sola: no hace falta recargarla.
            </p>

            {tramites.length === 0 ? (
                <Vacio>No tiene trámites en curso. Todo lo que tramitó ya está resuelto.</Vacio>
            ) : (
                <ul className="space-y-2.5">
                    {tramites.map((t, i) => (
                        <FilaTramite key={`${t.clase}-${t.codigo ?? i}`} tramite={t} />
                    ))}
                </ul>
            )}
        </LayoutPortal>
    );
}

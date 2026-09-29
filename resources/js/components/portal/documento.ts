import { Fish, IdCard, Scale, Truck, type LucideIcon } from 'lucide-react';
import { fechaHora } from '@/lib/utils';
import type { PapelPortal } from '@/types/portal';

/**
 * Lo que distingue a cada tipo de documento en las listas del portal: su ícono
 * y la línea que dice CUÁL es. Lo usan «En curso» y «Mis papeles».
 */
export const ICONO_DOCUMENTO: Record<PapelPortal['clase'], { icono: LucideIcon; tono: string }> = {
    aprovechamiento: { icono: Scale, tono: 'bg-emerald-100 text-emerald-700' },
    carnet: { icono: IdCard, tono: 'bg-violet-100 text-violet-700' },
    faena: { icono: Fish, tono: 'bg-sky-100 text-sky-700' },
    guia: { icono: Truck, tono: 'bg-amber-100 text-amber-700' },
};

export const kg = (n: number) => `${n.toLocaleString('es-BO', { maximumFractionDigits: 2 })} kg`;

export function detalleDocumento(p: PapelPortal): string {
    switch (p.clase) {
        case 'aprovechamiento':
            return [p.escala, kg(p.volumen_total_kg)].filter(Boolean).join(' · ');
        case 'carnet':
            return [p.asociacion ?? 'Sin asociación', p.cupo_kg !== null && `cupo ${kg(p.cupo_kg)}`]
                .filter(Boolean)
                .join(' · ');
        case 'faena':
            return [kg(p.kilos), p.region ?? p.embarcacion].filter(Boolean).join(' · ');
        case 'guia':
            return [kg(p.kilos), p.ruta, p.fecha_vencimiento && `vence ${fechaHora(p.fecha_vencimiento)}`]
                .filter(Boolean)
                .join(' · ');
    }
}

import { BadgeCheck, Ship, Truck, Waves } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { CobroPorConcepto } from '@/types/dashboard';

/**
 * Un color por trámite, siempre el mismo en todo el tablero. Validados contra
 * daltonismo en claro y oscuro (skill dataviz, 23/10/2026): no cambiar uno solo.
 */
export const COLOR_CONCEPTO: Record<CobroPorConcepto['clave'], string> = {
    aprovechamientos: '#0284c7',
    carnets: '#d97706',
    faenas: '#7c3aed',
    guias: '#16a34a',
};

export const ICONO_CONCEPTO: Record<CobroPorConcepto['clave'], LucideIcon> = {
    aprovechamientos: Waves,
    carnets: BadgeCheck,
    faenas: Ship,
    guias: Truck,
};

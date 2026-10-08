import { Link } from '@inertiajs/react';
import { CalendarClock, CheckCircle2, ChevronRight, Fish, IdCard, RefreshCw, Smartphone, Sparkles, Truck, Wallet } from 'lucide-react';
import type { ReactNode } from 'react';
import { ICONO_DOCUMENTO } from '@/components/portal/documento';
import { cn } from '@/lib/utils';
import type { TramiteDisponible } from '@/types/portal';

/**
 * La vitrina del inicio: qué se tramita y qué llevar, separado por actividad para
 * que cada uno vaya a lo suyo. Compacta y en palabras simples: la usa gente del
 * campo que casi no usa el celular. Los requisitos son los que el panel EXIGE al
 * registrar (los Request de cada trámite): si cambian allá, se cambian acá.
 */
interface Tramite {
    clase: TramiteDisponible['clase'];
    /** Distingue los dos carnets al cruzar con TramitesDisponibles. */
    actor?: 'pescador' | 'comercializador';
    titulo: string;
    lleve: string[];
    vale: string;
}

const PESCADOR: Tramite[] = [
    {
        clase: 'aprovechamiento',
        titulo: 'Autorización de Pesca',
        lleve: ['Cédula', 'Tipo de embarcación', 'Kilos que piensa pescar'],
        vale: 'todo el año',
    },
    {
        clase: 'carnet',
        actor: 'pescador',
        titulo: 'Carnet de pescador',
        lleve: ['Cédula', 'Carta de su asociación', 'Autorización de Pesca pagada'],
        vale: 'todo el año',
    },
    {
        clase: 'faena',
        titulo: 'Permiso de faena',
        lleve: ['Carnet de pescador al día', 'Datos de la embarcación', 'Kilos que va a sacar'],
        vale: '30 días',
    },
];

const COMERCIALIZADOR: Tramite[] = [
    {
        clase: 'carnet',
        actor: 'comercializador',
        titulo: 'Carnet de comercializador',
        lleve: ['Cédula', 'Carta de su asociación'],
        vale: 'todo el año',
    },
    {
        clase: 'guia',
        titulo: 'Guía de transporte',
        lleve: ['Carnet de comercializador al día', 'De dónde a dónde', 'En qué lo lleva', 'Qué pescado y cuántos kilos'],
        vale: '5 días',
    },
];

export function CatalogoTramites({ disponibles }: { disponibles: TramiteDisponible[] }) {
    const suyo = (t: Tramite) =>
        disponibles.find((d) => d.clase === t.clase && (!t.actor || d.titulo.toLowerCase().includes(t.actor)));

    return (
        <section className="mt-8">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-xl font-extrabold text-rio-profundo">Lo que puede tramitar</h2>
                <span className="inline-flex items-center gap-1.5 rounded-full bg-institucional-dorado px-3 py-1 text-xs font-bold text-rio-profundo">
                    <Sparkles className="size-3.5" />
                    Muy pronto: pague con QR desde aquí
                </span>
            </div>

            {/* Cómo se hace HOY, en una franja: así no espera algo que todavía no existe. */}
            <ol className="mt-3 flex flex-wrap items-center gap-x-2 gap-y-2 rounded-2xl bg-rio-profundo px-4 py-3 text-sm font-semibold text-white">
                <Paso numero={1} icono={<IdCard className="size-4" />}>
                    Venga a ventanilla con su cédula
                </Paso>
                <ChevronRight className="hidden size-4 text-white/40 sm:block" />
                <Paso numero={2} icono={<Wallet className="size-4" />}>
                    Pague con su código
                </Paso>
                <ChevronRight className="hidden size-4 text-white/40 sm:block" />
                <Paso numero={3} icono={<Smartphone className="size-4" />}>
                    Véalo aquí
                </Paso>
            </ol>

            {/* El seguimiento: «En curso» se refresca sola. No se dice «en tiempo real»: SIREB se consulta cada 10 min. */}
            <Link
                href={route('portal.en-curso')}
                className="mt-2 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200/80 transition-colors hover:bg-rio-espuma"
            >
                <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-rio-espuma text-rio">
                    <RefreshCw className="size-5" />
                </span>
                <span className="min-w-0 flex-1 leading-tight">
                    <span className="block font-bold text-rio-profundo">Siga sus trámites desde aquí</span>
                    <span className="block text-sm text-slate-600">Vea en qué paso va cada uno. Se actualiza solo.</span>
                </span>
                <ChevronRight className="size-5 shrink-0 text-slate-400" />
            </Link>

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <Grupo titulo="Si usted es pescador" icono={<Fish className="size-5" />}>
                    {PESCADOR.map((t) => (
                        <Fila key={t.titulo} tramite={t} suyo={suyo(t)} />
                    ))}
                </Grupo>
                <Grupo titulo="Si usted es comercializador" icono={<Truck className="size-5" />}>
                    {COMERCIALIZADOR.map((t) => (
                        <Fila key={t.titulo} tramite={t} suyo={suyo(t)} />
                    ))}
                </Grupo>
            </div>
        </section>
    );
}

function Paso({ numero, icono, children }: { numero: number; icono: ReactNode; children: ReactNode }) {
    return (
        <li className="flex items-center gap-2">
            <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-institucional-dorado text-xs font-extrabold text-rio-profundo">
                {numero}
            </span>
            <span className="text-rio-claro">{icono}</span>
            {children}
        </li>
    );
}

function Grupo({ titulo, icono, children }: { titulo: string; icono: ReactNode; children: ReactNode }) {
    return (
        <div className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/80">
            <h3 className="flex items-center gap-2 border-b border-slate-100 bg-rio-espuma px-4 py-2.5 font-bold text-rio-profundo">
                <span className="text-rio">{icono}</span>
                {titulo}
            </h3>
            <ul className="divide-y divide-slate-100">{children}</ul>
        </div>
    );
}

function Fila({ tramite: t, suyo }: { tramite: Tramite; suyo?: TramiteDisponible }) {
    const { icono: Icono, tono } = ICONO_DOCUMENTO[t.clase];
    const puede = suyo?.disponible ?? false;
    // Faena y guía traen un dato propio: los kilos que le quedan, o por qué hoy no puede.
    const nota = suyo && (t.clase === 'faena' || t.clase === 'guia') ? suyo.detalle : null;

    return (
        <li className="flex gap-3 px-4 py-3">
            <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-xl', tono)}>
                <Icono className="size-5" />
            </span>
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <p className="font-bold text-rio-profundo">{t.titulo}</p>
                    {puede && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">
                            <CheckCircle2 className="size-3.5" />
                            Puede pedirlo
                        </span>
                    )}
                </div>
                <p className="mt-0.5 text-sm text-slate-600">
                    <span className="font-semibold text-slate-800">Lleve:</span> {t.lleve.join(' · ')}
                </p>
                {nota && <p className={cn('mt-1 text-xs font-medium', puede ? 'text-emerald-700' : 'text-amber-700')}>{nota}</p>}
                <p className="mt-1 flex items-center gap-1 text-xs text-slate-500">
                    <CalendarClock className="size-3.5" />
                    Vale {t.vale}
                </p>
            </div>
        </li>
    );
}

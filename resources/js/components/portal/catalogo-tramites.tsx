import { CalendarClock, Check, CheckCircle2, Clock, Fish, IdCard, Lock, Smartphone, Truck, Wallet } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { ICONO_DOCUMENTO } from '@/components/portal/documento';
import { cn } from '@/lib/utils';
import type { PapelPortal, TramiteDisponible } from '@/types/portal';

/**
 * «Lo que puede tramitar», como un camino: elige su actividad, ve los papeles en el
 * orden en que se piden —cada uno habilita el siguiente— y toca uno para ver para qué
 * sirve y qué llevar. Los requisitos son los que el panel EXIGE al registrar (los
 * Request de cada trámite): si cambian allá, se cambian acá.
 */
interface Tramite {
    clase: TramiteDisponible['clase'];
    /** Distingue los dos carnets al cruzar con TramitesDisponibles. */
    actor?: 'pescador' | 'comercializador';
    titulo: string;
    corto: string;
    sirve: string;
    lleve: string[];
    vale: string;
}

type Actividad = 'pescador' | 'comercializador';

const CAMINOS: Record<Actividad, Tramite[]> = {
    pescador: [
        {
            clase: 'aprovechamiento',
            titulo: 'Autorización de Pesca para Aprovechamiento Pesquero',
            corto: 'Autorización',
            sirve: 'Es su cupo de pesca del año, en kilos. Sin ella no puede sacar el carnet de pescador.',
            lleve: ['Cédula', 'Tipo de embarcación', 'El tramo de la escala que va a pedir'],
            vale: 'todo el año',
        },
        {
            clase: 'carnet',
            actor: 'pescador',
            titulo: 'Carnet de pescador',
            corto: 'Carnet',
            sirve: 'Su credencial de pescador por un año. Con él pide los permisos de faena.',
            lleve: ['Cédula', 'Carta de su asociación', 'Autorización de Pesca pagada'],
            vale: 'todo el año',
        },
        {
            clase: 'faena',
            titulo: 'Permiso de faena',
            corto: 'Faena',
            sirve: 'El permiso para cada salida a pescar. Se saca uno por salida.',
            lleve: ['Carnet de pescador al día', 'Datos de la embarcación'],
            vale: '30 días',
        },
    ],
    comercializador: [
        {
            clase: 'carnet',
            actor: 'comercializador',
            titulo: 'Carnet de comercializador',
            corto: 'Carnet',
            sirve: 'Su credencial para comprar y vender pescado por un año. Con él pide las guías.',
            lleve: ['Cédula', 'Carta de su asociación'],
            vale: 'todo el año',
        },
        {
            clase: 'guia',
            titulo: 'Guía de transporte',
            corto: 'Guía',
            sirve: 'El papel para trasladar pescado. Una por viaje: los kilos se descuentan del pescador que le vendió.',
            lleve: [
                'Carnet de comercializador al día',
                'De dónde a dónde',
                'En qué lo lleva',
                'Qué pescado y cuántos kilos',
                'A qué pescador le compró (N° de su autorización)',
            ],
            vale: '5 días',
        },
    ],
};

/** Dónde está la persona respecto de cada papel del camino. */
type Situacion = 'tiene' | 'tramite' | 'puede' | 'falta';

const SITUACION: Record<Situacion, { etiqueta: string; chip: string; nodo: string }> = {
    tiene: {
        etiqueta: 'Ya lo tiene',
        chip: 'bg-emerald-100 text-emerald-800',
        nodo: 'bg-emerald-600 text-white ring-emerald-600',
    },
    tramite: {
        etiqueta: 'En trámite',
        chip: 'bg-sky-100 text-sky-800',
        nodo: 'bg-sky-600 text-white ring-sky-600',
    },
    puede: {
        etiqueta: 'Puede pedirlo',
        chip: 'bg-amber-100 text-amber-900',
        nodo: 'bg-institucional-dorado text-rio-profundo ring-institucional-dorado',
    },
    falta: {
        etiqueta: 'Todavía no',
        chip: 'bg-slate-100 text-slate-600',
        nodo: 'bg-white text-slate-400 ring-slate-300',
    },
};

const CLAVE_ACTIVIDAD = 'portal.actividad';

export function CatalogoTramites({
    disponibles,
    vigentes,
    abiertos,
}: {
    disponibles: TramiteDisponible[];
    vigentes: PapelPortal[];
    abiertos: PapelPortal[];
}) {
    const suyo = (t: Tramite) =>
        disponibles.find((d) => d.clase === t.clase && (!t.actor || d.titulo.toLowerCase().includes(t.actor)));
    const esDe = (p: PapelPortal, t: Tramite) =>
        p.clase === t.clase && (!t.actor || (p.clase === 'carnet' && p.tipo_actor === t.actor));
    const situacion = (t: Tramite): Situacion => {
        // Faena y guía se sacan una por salida: aunque tenga una, puede pedir otra.
        const repetible = t.clase === 'faena' || t.clase === 'guia';
        if (abiertos.some((p) => esDe(p, t))) return 'tramite';
        if (!repetible && vigentes.some((p) => esDe(p, t))) return 'tiene';
        if (suyo(t)?.disponible) return 'puede';
        return repetible && vigentes.some((p) => esDe(p, t)) ? 'tiene' : 'falta';
    };

    // Abre en la actividad que ya tiene; si no tiene ninguna, en la que eligió la última vez.
    const deducida: Actividad | null = [...vigentes, ...abiertos].some(
        (p) => p.clase === 'guia' || (p.clase === 'carnet' && p.tipo_actor === 'comercializador'),
    )
        ? 'comercializador'
        : [...vigentes, ...abiertos].length > 0
          ? 'pescador'
          : null;
    const [actividad, setActividad] = useState<Actividad>(() => deducida ?? leerActividad() ?? 'pescador');
    const camino = CAMINOS[actividad];

    // Abre el primer paso que puede hacer: es la respuesta a «¿y ahora qué saco?».
    const primeroPendiente = () => {
        const i = camino.findIndex((t) => situacion(t) === 'puede');
        return i === -1 ? 0 : i;
    };
    const [elegido, setElegido] = useState(primeroPendiente);

    useEffect(() => {
        setElegido(primeroPendiente());
        guardarActividad(actividad);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [actividad]);

    const tramite = camino[elegido] ?? camino[0];

    return (
        <section id="tramites" className="mt-8 scroll-mt-4">
            <h2 className="text-xl font-extrabold text-rio-profundo">Lo que puede tramitar</h2>
            <p className="mt-0.5 text-sm text-slate-600">Toque cada paso para ver para qué sirve y qué llevar.</p>

            <div
                role="tablist"
                aria-label="Su actividad"
                className="mt-3 grid grid-cols-2 gap-1 rounded-2xl bg-slate-200/60 p-1"
            >
                <PestanaActividad
                    activa={actividad === 'pescador'}
                    onClick={() => setActividad('pescador')}
                    icono={<Fish className="size-5" />}
                >
                    <span className="sm:hidden">Pescador</span>
                    <span className="hidden sm:inline">Soy pescador</span>
                </PestanaActividad>
                <PestanaActividad
                    activa={actividad === 'comercializador'}
                    onClick={() => setActividad('comercializador')}
                    icono={<Truck className="size-5" />}
                >
                    <span className="sm:hidden">Comercializador</span>
                    <span className="hidden sm:inline">Soy comercializador</span>
                </PestanaActividad>
            </div>

            {/* El camino: cada papel habilita el siguiente. */}
            <ol className="mt-4 flex items-start">
                {camino.map((t, i) => {
                    const s = situacion(t);
                    const activo = i === elegido;
                    const { icono: Icono } = ICONO_DOCUMENTO[t.clase];

                    return (
                        <li key={t.titulo} className="flex min-w-0 flex-1 items-start last:flex-none">
                            <button
                                type="button"
                                onClick={() => setElegido(i)}
                                aria-pressed={activo}
                                className="group flex w-20 shrink-0 flex-col items-center gap-1.5 text-center sm:w-32"
                            >
                                <span
                                    className={cn(
                                        'relative flex size-12 items-center justify-center rounded-2xl ring-2 transition-transform duration-200 group-hover:scale-105 sm:size-14',
                                        SITUACION[s].nodo,
                                        activo &&
                                            'scale-110 shadow-lg ring-offset-2 ring-offset-rio-espuma group-hover:scale-110',
                                    )}
                                >
                                    {s === 'falta' ? <Lock className="size-5" /> : <Icono className="size-6" />}
                                    {s === 'tiene' && (
                                        <span className="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full bg-white text-emerald-600 shadow">
                                            <Check className="size-3.5" strokeWidth={3} />
                                        </span>
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'text-xs leading-tight font-bold sm:text-sm',
                                        activo ? 'text-rio-profundo' : 'text-slate-600',
                                    )}
                                >
                                    {t.corto}
                                </span>
                                <span
                                    className={cn(
                                        'rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                                        SITUACION[s].chip,
                                    )}
                                >
                                    {SITUACION[s].etiqueta}
                                </span>
                            </button>
                            {i < camino.length - 1 && (
                                <span
                                    aria-hidden
                                    className={cn(
                                        'mt-6 h-1 min-w-3 flex-1 rounded-full sm:mt-7',
                                        s === 'tiene' ? 'bg-emerald-500' : 'bg-slate-300',
                                    )}
                                />
                            )}
                        </li>
                    );
                })}
            </ol>

            <DetalleTramite
                key={`${actividad}-${elegido}`}
                tramite={tramite}
                situacion={situacion(tramite)}
                suyo={suyo(tramite)}
            />
        </section>
    );
}

function PestanaActividad({
    activa,
    onClick,
    icono,
    children,
}: {
    activa: boolean;
    onClick: () => void;
    icono: ReactNode;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={activa}
            onClick={onClick}
            className={cn(
                'flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold transition-all',
                activa ? 'bg-white text-rio-profundo shadow-sm' : 'text-slate-600 hover:text-rio-profundo',
            )}
        >
            {icono}
            {children}
        </button>
    );
}

/** El papel elegido: para qué sirve, la lista para marcar lo que ya tiene y cómo se pide. */
function DetalleTramite({
    tramite: t,
    situacion,
    suyo,
}: {
    tramite: Tramite;
    situacion: Situacion;
    suyo?: TramiteDisponible;
}) {
    const { icono: Icono, tono } = ICONO_DOCUMENTO[t.clase];
    const [marcados, setMarcados] = useState<boolean[]>(() => t.lleve.map(() => false));
    const listos = marcados.filter(Boolean).length;
    // Faena y guía traen un dato propio (los kilos o por qué no); los demás, solo el motivo si hoy no puede.
    const nota = suyo && (t.clase === 'faena' || t.clase === 'guia' || !suyo.disponible) ? suyo.detalle : null;

    return (
        <div className="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/80 motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-1 motion-safe:duration-300">
            <div className="flex items-start gap-3 border-b border-slate-100 bg-rio-espuma p-4">
                <span className={cn('flex size-11 shrink-0 items-center justify-center rounded-xl', tono)}>
                    <Icono className="size-6" />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <h3 className="font-extrabold text-rio-profundo">{t.titulo}</h3>
                        <span
                            className={cn(
                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold',
                                SITUACION[situacion].chip,
                            )}
                        >
                            {situacion === 'puede' && <CheckCircle2 className="size-3.5" />}
                            {situacion === 'tramite' && <Clock className="size-3.5" />}
                            {SITUACION[situacion].etiqueta}
                        </span>
                    </div>
                    <p className="mt-1 text-sm text-slate-700">{t.sirve}</p>
                    {nota && (
                        <p
                            className={cn(
                                'mt-1 text-sm font-semibold',
                                situacion === 'puede' ? 'text-emerald-700' : 'text-amber-700',
                            )}
                        >
                            {nota}
                        </p>
                    )}
                    <p className="mt-1.5 flex items-center gap-1 text-xs text-slate-500">
                        <CalendarClock className="size-3.5" />
                        Vale {t.vale}
                    </p>
                </div>
            </div>

            <div className="grid gap-4 p-4 sm:grid-cols-[1fr_15rem]">
                <div>
                    <div className="mb-2 flex items-center justify-between gap-2">
                        <p className="font-bold text-rio-profundo">Qué llevar</p>
                        <span className="text-xs font-semibold text-slate-500 tabular-nums">
                            {listos} de {t.lleve.length} listos
                        </span>
                    </div>
                    <div className="mb-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div
                            className="h-full rounded-full bg-emerald-500 transition-[width] duration-300"
                            style={{ width: `${(listos / t.lleve.length) * 100}%` }}
                        />
                    </div>
                    <ul className="space-y-1.5">
                        {t.lleve.map((cosa, i) => (
                            <li key={cosa}>
                                <label
                                    className={cn(
                                        'flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 ring-1 transition-colors',
                                        marcados[i]
                                            ? 'bg-emerald-50 ring-emerald-200'
                                            : 'bg-white ring-slate-200 hover:bg-slate-50',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        className="sr-only"
                                        checked={marcados[i]}
                                        onChange={() => setMarcados((m) => m.map((v, j) => (j === i ? !v : v)))}
                                    />
                                    <span
                                        aria-hidden
                                        className={cn(
                                            'flex size-6 shrink-0 items-center justify-center rounded-lg border-2 transition-colors',
                                            marcados[i]
                                                ? 'border-emerald-600 bg-emerald-600 text-white'
                                                : 'border-slate-300',
                                        )}
                                    >
                                        {marcados[i] && <Check className="size-4" strokeWidth={3} />}
                                    </span>
                                    <span
                                        className={cn('text-sm', marcados[i] ? 'text-emerald-900' : 'text-slate-800')}
                                    >
                                        {cosa}
                                    </span>
                                </label>
                            </li>
                        ))}
                    </ul>
                    {listos === t.lleve.length && (
                        <p className="mt-2 flex items-center gap-1.5 text-sm font-semibold text-emerald-700 motion-safe:animate-in motion-safe:fade-in">
                            <CheckCircle2 className="size-4" />
                            ¡Listo! Ya puede venir a ventanilla.
                        </p>
                    )}
                </div>

                <div className="rounded-xl bg-rio-profundo p-4 text-white">
                    <p className="mb-3 font-bold">Cómo se pide</p>
                    <ol className="space-y-3 text-sm">
                        <PasoComo numero={1} icono={<IdCard className="size-4" />}>
                            Venga a ventanilla con lo de la lista
                        </PasoComo>
                        <PasoComo numero={2} icono={<Wallet className="size-4" />}>
                            Pague en el banco o con QR, con el código que le dan
                        </PasoComo>
                        <PasoComo numero={3} icono={<Smartphone className="size-4" />}>
                            Sígalo aquí: se aprueba solo al validarse el pago
                        </PasoComo>
                    </ol>
                </div>
            </div>
        </div>
    );
}

function PasoComo({ numero, icono, children }: { numero: number; icono: ReactNode; children: ReactNode }) {
    return (
        <li className="flex items-start gap-2.5">
            <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-institucional-dorado text-xs font-extrabold text-rio-profundo">
                {numero}
            </span>
            <span className="flex-1 leading-snug">
                <span className="mr-1 inline-block align-[-2px] text-rio-claro">{icono}</span>
                {children}
            </span>
        </li>
    );
}

// Recordar la actividad elegida es una comodidad: si el navegador no deja guardar, no pasa nada.
function leerActividad(): Actividad | null {
    try {
        const v = localStorage.getItem(CLAVE_ACTIVIDAD);
        return v === 'pescador' || v === 'comercializador' ? v : null;
    } catch {
        return null;
    }
}

function guardarActividad(a: Actividad) {
    try {
        localStorage.setItem(CLAVE_ACTIVIDAD, a);
    } catch {
        // Ventana privada o almacenamiento bloqueado.
    }
}

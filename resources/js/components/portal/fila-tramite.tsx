import { Check, ExternalLink, Eye } from 'lucide-react';
import { useState } from 'react';
import { detalleDocumento, ICONO_DOCUMENTO } from '@/components/portal/documento';
import { ConMontoResaltado, EstadoChip } from '@/components/portal/piezas';
import { VisorVistaPrevia } from '@/components/portal/visor-vista-previa';
import { cn } from '@/lib/utils';
import type { PapelPortal } from '@/types/portal';

const PASOS = ['Solicitado', 'Pago en Recaudaciones', 'Aprobado'];

/** Qué paso está en marcha: sale de la `etapa` que calcula el servidor. */
const PASO_ACTUAL: Record<NonNullable<PapelPortal['etapa']>, number> = { pago: 1 };

/**
 * Un trámite abierto, compacto: qué es, la línea de pasos del circuito, qué le
 * falta y el código con el que se paga en SIREB.
 */
export function FilaTramite({ tramite }: { tramite: PapelPortal }) {
    const { icono: Icono, tono } = ICONO_DOCUMENTO[tramite.clase];
    const actual = tramite.etapa ? PASO_ACTUAL[tramite.etapa] : 0;
    const [viendo, setViendo] = useState(false);

    return (
        <li className="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition-shadow hover:shadow-md sm:p-4">
            <div className="flex items-center gap-3">
                <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-lg', tono)}>
                    <Icono className="size-4.5" />
                </span>
                <div className="min-w-0 flex-1 leading-tight">
                    <p className="truncate text-sm font-bold text-rio-profundo">{tramite.tipo}</p>
                    <p className="truncate text-xs text-slate-500">{detalleDocumento(tramite)}</p>
                </div>
                <EstadoChip color={tramite.estado_color}>{tramite.estado_etiqueta}</EstadoChip>
            </div>

            {/* La línea de pasos: hecho con ✓, el paso en marcha en dorado y pulsando, lo que viene en gris. */}
            <ol className="mt-3 grid grid-cols-3" aria-label="Avance del trámite">
                {PASOS.map((paso, i) => {
                    const hecho = i < actual;
                    const enMarcha = i === actual;

                    return (
                        <li key={paso} className="relative flex flex-col items-center text-center">
                            {i > 0 && (
                                <span
                                    className={cn(
                                        'absolute top-2.5 right-1/2 h-0.5 w-full -translate-y-1/2',
                                        i <= actual ? 'bg-rio' : 'bg-slate-200',
                                    )}
                                    aria-hidden
                                />
                            )}
                            <span
                                className={cn(
                                    'relative z-10 flex size-5 items-center justify-center rounded-full text-[10px] font-bold ring-2 ring-white',
                                    hecho && 'bg-rio text-white',
                                    enMarcha && 'bg-institucional-dorado text-rio-profundo',
                                    !hecho && !enMarcha && 'bg-slate-200 text-slate-500',
                                )}
                            >
                                {hecho ? <Check className="size-3" /> : i + 1}
                                {enMarcha && (
                                    <span className="absolute inset-0 animate-ping rounded-full bg-institucional-dorado/50 motion-reduce:hidden" />
                                )}
                            </span>
                            <span
                                className={cn(
                                    'mt-1 text-[10px] leading-tight font-semibold sm:text-[11px]',
                                    enMarcha ? 'text-rio-profundo' : hecho ? 'text-rio' : 'text-slate-400',
                                )}
                            >
                                {paso}
                            </span>
                        </li>
                    );
                })}
            </ol>

            {tramite.siguiente_paso && (
                <p className="mt-2 text-xs leading-relaxed text-slate-600">
                    <ConMontoResaltado texto={tramite.siguiente_paso} />
                </p>
            )}

            <div className="mt-3 flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex min-w-0 items-center gap-3">
                    {tramite.vista_previa && (
                        <button
                            type="button"
                            onClick={() => setViendo(true)}
                            className="flex shrink-0 items-center gap-1.5 rounded-full border border-rio/30 px-3 py-1.5 text-xs font-bold text-rio transition-colors hover:bg-rio-espuma"
                        >
                            <Eye className="size-3.5" />
                            Vista previa
                        </button>
                    )}
                    {tramite.codigo && tramite.verificar && (
                        <a
                            href={tramite.verificar}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex min-w-0 items-center gap-1.5 text-xs text-slate-500 hover:text-rio"
                        >
                            <span className="truncate font-mono tracking-wider">{tramite.codigo}</span>
                            <span className="flex shrink-0 items-center gap-1 font-semibold text-rio">
                                Verificar
                                <ExternalLink className="size-3.5" />
                            </span>
                        </a>
                    )}
                </div>

                {/* Con este código se paga en Recaudaciones. */}
                {tramite.codigo_pago && (
                    <p className="shrink-0 rounded-full bg-rio-espuma px-3 py-1.5 text-xs text-rio-profundo">
                        Código de pago <strong className="font-mono tracking-wider">{tramite.codigo_pago}</strong>
                    </p>
                )}
            </div>

            {viendo && tramite.vista_previa && (
                <VisorVistaPrevia url={tramite.vista_previa} titulo={tramite.tipo} onCerrar={() => setViendo(false)} />
            )}
        </li>
    );
}

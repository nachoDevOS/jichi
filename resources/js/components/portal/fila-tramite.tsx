import { AlertTriangle, Check, CheckCircle2, ExternalLink, Eye, Hourglass, QrCode, Search } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { detalleDocumento, ICONO_DOCUMENTO } from '@/components/portal/documento';
import { ConMontoResaltado, EstadoChip } from '@/components/portal/piezas';
import { VentanaPagoQr } from '@/components/portal/ventana-pago-qr';
import { VisorVistaPrevia } from '@/components/portal/visor-vista-previa';
import { cn } from '@/lib/utils';
import type { PapelPortal } from '@/types/portal';

const PASOS = ['Solicitado', 'Pago en Recaudaciones', 'Aprobado'];

/** Qué paso está en marcha: sale de la `etapa` que calcula el servidor. */
const PASO_ACTUAL: Record<NonNullable<PapelPortal['etapa']>, number> = { pago: 1 };

/** Cómo se dice el estado del pago, de un vistazo. Clases completas: Tailwind solo ve lo escrito. */
const ESTADO_PAGO: Record<NonNullable<PapelPortal['pago']>, { texto: string; clase: string; icono: ReactNode }> = {
    sin_pago: { texto: 'Esperando su pago', clase: 'bg-amber-50 text-amber-900 ring-amber-200', icono: <Hourglass className="size-5" /> },
    revision: { texto: 'Pago cargado: lo están revisando', clase: 'bg-sky-50 text-sky-900 ring-sky-200', icono: <Search className="size-5" /> },
    validado: { texto: 'Pago validado', clase: 'bg-emerald-50 text-emerald-900 ring-emerald-200', icono: <CheckCircle2 className="size-5" /> },
    caida: { texto: 'Hace falta un nuevo código de pago', clase: 'bg-rose-50 text-rose-900 ring-rose-200', icono: <AlertTriangle className="size-5" /> },
};

/**
 * Un trámite abierto, compacto: qué es, la línea de pasos del circuito, qué le
 * falta y el código con el que se paga en SIREB.
 */
export function FilaTramite({ tramite }: { tramite: PapelPortal }) {
    const { icono: Icono, tono } = ICONO_DOCUMENTO[tramite.clase];
    const actual = tramite.etapa ? PASO_ACTUAL[tramite.etapa] : 0;
    const [viendo, setViendo] = useState(false);
    const [pagando, setPagando] = useState(false);

    return (
        <li className="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition-shadow hover:shadow-md sm:p-4">
            <div className="flex items-center gap-3">
                <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-lg', tono)}>
                    <Icono className="size-4.5" />
                </span>
                <div className="min-w-0 flex-1 leading-tight">
                    <p className="text-base leading-snug font-bold text-rio-profundo">{tramite.tipo}</p>
                    <p className="truncate text-sm text-slate-500">{detalleDocumento(tramite)}</p>
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

            {/* En qué está el pago: lo primero que busca quien ya pagó. */}
            {tramite.pago && (
                <div className={cn('mt-3 rounded-xl px-3 py-2.5 ring-1', ESTADO_PAGO[tramite.pago].clase)}>
                    <p className="flex items-center gap-2 font-bold">
                        {ESTADO_PAGO[tramite.pago].icono}
                        {ESTADO_PAGO[tramite.pago].texto}
                    </p>
                    {tramite.siguiente_paso && (
                        <p className="mt-1 text-sm leading-relaxed">
                            <ConMontoResaltado texto={tramite.siguiente_paso} />
                        </p>
                    )}
                </div>
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

                <div className="flex shrink-0 flex-col gap-2 sm:flex-row sm:items-center">
                    {/* Con este código se paga en Recaudaciones. */}
                    {tramite.codigo_pago && (
                        <p className="rounded-full bg-rio-espuma px-3.5 py-2 text-sm text-rio-profundo">
                            Código de pago <strong className="font-mono tracking-wider">{tramite.codigo_pago}</strong>
                        </p>
                    )}
                    {tramite.puede_pagar_qr && tramite.codigo && (
                        <button
                            type="button"
                            onClick={() => setPagando(true)}
                            className="flex items-center justify-center gap-1.5 rounded-full bg-rio px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-rio-profundo"
                        >
                            <QrCode className="size-4" />
                            Pagar por QR
                        </button>
                    )}
                </div>
            </div>

            {pagando && tramite.codigo && (
                <VentanaPagoQr
                    codigo={tramite.codigo}
                    titulo={tramite.tipo}
                    detalle={detalleDocumento(tramite)}
                    onCerrar={() => setPagando(false)}
                />
            )}

            {viendo && tramite.vista_previa && (
                <VisorVistaPrevia url={tramite.vista_previa} titulo={tramite.tipo} onCerrar={() => setViendo(false)} />
            )}
        </li>
    );
}

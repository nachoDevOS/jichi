import { CalendarClock, ExternalLink } from 'lucide-react';
import { detalleDocumento, ICONO_DOCUMENTO, kg } from '@/components/portal/documento';
import { AvisoRetiroCarnet, BotonDescarga, EstadoChip } from '@/components/portal/piezas';
import { cn, fecha, fechaHora } from '@/lib/utils';
import type { PapelPortal } from '@/types/portal';

/** Desde cuántos días se avisa «vence pronto»: contra lo que dura cada uno (la faena, 30 días; la guía, 5). */
const DIAS_AVISO: Record<PapelPortal['clase'], number> = { aprovechamiento: 30, carnet: 30, faena: 5, guia: 1 };

const CHIP: Record<NonNullable<PapelPortal['situacion']>, { color: string; texto: string }> = {
    vigente: { color: 'emerald', texto: 'Vigente' },
    vencido: { color: 'slate', texto: 'Vencido' },
    revocado: { color: 'rose', texto: 'Sin validez' },
};

/**
 * Un papel ya aprobado, en «Mis papeles». Lo que ya no vale se ve atenuado, con
 * cuándo venció o por qué se dio de baja; lo vigente, con cuánto le queda.
 */
export function FilaPapel({ papel }: { papel: PapelPortal }) {
    const { icono: Icono, tono } = ICONO_DOCUMENTO[papel.clase];
    const situacion = papel.situacion ?? 'vencido';
    const vigente = situacion === 'vigente';
    const vencePronto = vigente && papel.dias_restantes !== null && papel.dias_restantes <= DIAS_AVISO[papel.clase];
    // La guía vale por horas: su fecha va con la hora.
    const cuando = papel.clase === 'guia' ? fechaHora(papel.vence_el) : fecha(papel.vence_el);

    return (
        <li
            className={cn(
                'rounded-2xl border bg-white p-3.5 shadow-sm transition-shadow hover:shadow-md sm:p-4',
                vigente ? 'border-emerald-200' : 'border-slate-200 bg-white/70',
            )}
        >
            <div className="flex items-center gap-3">
                <span
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                        vigente ? tono : 'bg-slate-100 text-slate-400',
                    )}
                >
                    <Icono className="size-4.5" />
                </span>
                <div className="min-w-0 flex-1 leading-tight">
                    <p className={cn('truncate text-sm font-bold', vigente ? 'text-rio-profundo' : 'text-slate-500')}>
                        {papel.tipo}
                    </p>
                    <p className="truncate text-xs text-slate-500">{detalleDocumento(papel)}</p>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1 sm:flex-row sm:items-center">
                    {vencePronto && <EstadoChip color="amber">Vence pronto</EstadoChip>}
                    <EstadoChip color={CHIP[situacion].color}>{CHIP[situacion].texto}</EstadoChip>
                </div>
            </div>

            {/* La autorización vigente muestra su saldo: es lo que el pescador consulta. */}
            {papel.clase === 'aprovechamiento' && vigente && (
                <div className="mt-3">
                    <div className="flex justify-between text-[11px] font-semibold text-slate-500">
                        <span>Usado {kg(papel.kilos_consumidos)}</span>
                        <span className="text-rio">Le quedan {kg(papel.saldo_kg)}</span>
                    </div>
                    <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div
                            className="h-full rounded-full bg-linear-to-r from-rio-claro to-rio"
                            style={{ width: `${Math.min(100, papel.porcentaje_usado)}%` }}
                        />
                    </div>
                </div>
            )}

            <p
                className={cn(
                    'mt-2.5 flex items-center gap-1.5 text-xs',
                    vigente ? 'text-slate-600' : situacion === 'revocado' ? 'text-rose-700' : 'text-slate-500',
                )}
            >
                <CalendarClock className="size-3.5 shrink-0" />
                {vigente ? (
                    <span>
                        Vigente hasta <strong className="text-rio-profundo">{cuando}</strong>
                        {papel.dias_restantes !== null && (
                            <span className={cn(vencePronto ? 'font-semibold text-amber-700' : 'text-slate-400')}>
                                {' · '}
                                {papel.dias_restantes === 0
                                    ? 'vence hoy'
                                    : `faltan ${papel.dias_restantes} ${papel.dias_restantes === 1 ? 'día' : 'días'}`}
                            </span>
                        )}
                    </span>
                ) : (
                    <span>{papel.motivo_baja ?? `Venció el ${cuando}`}</span>
                )}
            </p>

            <div className="mt-2.5 flex items-center justify-between gap-3">
                {papel.codigo && papel.verificar ? (
                    <a
                        href={papel.verificar}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex min-w-0 items-center gap-1.5 text-xs text-slate-500 hover:text-rio"
                    >
                        <span className="truncate font-mono tracking-wider">{papel.codigo}</span>
                        <span className="flex shrink-0 items-center gap-1 font-semibold text-rio">
                            Verificar
                            <ExternalLink className="size-3.5" />
                        </span>
                    </a>
                ) : (
                    <span />
                )}

                {papel.clase === 'carnet' && vigente && <AvisoRetiroCarnet />}

                {papel.descargar && <BotonDescarga url={papel.descargar} etiqueta={`Descargar ${papel.tipo} en PDF`} />}
            </div>
        </li>
    );
}

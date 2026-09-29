import { Head, router, usePage } from '@inertiajs/react';
import { ChevronDown, ExternalLink, FileImage, Landmark, MessageSquareWarning, Receipt } from 'lucide-react';
import { useState } from 'react';
import { BotonDescarga, EstadoChip, Vacio } from '@/components/portal/piezas';
import LayoutPortal from '@/layouts/layout-portal';
import { bs, cn, fecha } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { ReciboPortal, TramitePagado } from '@/types/portal';

type Pestana = 'pagos' | 'recibos';

/**
 * Sus depósitos y recibos por gestión: el informe de aportes del Art. 13 del
 * reglamento. Abre en «Pagos»: un trámite por fila, con sus boletas adentro.
 */
export default function Pagos({
    gestion,
    gestiones,
    total_pagado,
    observados,
    tramites,
    recibos,
}: {
    gestion: number;
    gestiones: number[];
    total_pagado: number;
    observados: number;
    tramites: TramitePagado[];
    recibos: ReciboPortal[];
}) {
    const { institucion } = usePage<PageProps>().props;
    const [pestana, setPestana] = useState<Pestana>('pagos');

    const bajada = [
        `Pagado en ${gestion}: ${bs(total_pagado, institucion.moneda)}`,
        observados > 0 && `${observados} ${observados === 1 ? 'depósito observado' : 'depósitos observados'}`,
    ]
        .filter(Boolean)
        .join(' · ');

    const pestanas: { clave: Pestana; texto: string; cuantos: number }[] = [
        { clave: 'pagos', texto: 'Pagos', cuantos: tramites.length },
        { clave: 'recibos', texto: 'Recibos', cuantos: recibos.length },
    ];

    const controles = (
        <div className="flex items-center gap-2">
            <div role="tablist" className="inline-flex rounded-full bg-white p-1 shadow-sm ring-1 ring-slate-200">
                {pestanas.map((p) => (
                    <button
                        key={p.clave}
                        type="button"
                        role="tab"
                        aria-selected={pestana === p.clave}
                        onClick={() => setPestana(p.clave)}
                        className={cn(
                            'flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold whitespace-nowrap transition-colors',
                            pestana === p.clave
                                ? 'bg-rio-profundo text-white shadow-sm'
                                : 'text-slate-600 hover:text-rio-profundo',
                        )}
                    >
                        {p.texto}
                        <span className={cn('tabular-nums', pestana === p.clave ? 'text-white/70' : 'text-slate-400')}>
                            {p.cuantos}
                        </span>
                    </button>
                ))}
            </div>

            {/* La gestión va a la URL: el servidor filtra y los totales salen de ahí. */}
            <select
                aria-label="Gestión"
                value={gestion}
                onChange={(e) =>
                    router.get(route('portal.pagos'), { gestion: e.target.value }, { preserveScroll: true })
                }
                className="rounded-full border-0 bg-white py-1.5 pr-8 pl-3 text-xs font-semibold text-rio-profundo shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-rio-claro"
            >
                {gestiones.map((g) => (
                    <option key={g} value={g}>
                        {g}
                    </option>
                ))}
            </select>
        </div>
    );

    return (
        <LayoutPortal titulo="Mis pagos" bajada={bajada} extra={controles}>
            <Head title="Mis pagos" />

            {pestana === 'pagos' &&
                (tramites.length === 0 ? (
                    <Vacio>No tiene pagos en {gestion}.</Vacio>
                ) : (
                    <ul className="space-y-2.5">
                        {tramites.map((t, i) => (
                            <FilaTramitePagado key={`${t.concepto}-${i}`} tramite={t} moneda={institucion.moneda} />
                        ))}
                    </ul>
                ))}

            {pestana === 'recibos' &&
                (recibos.length === 0 ? (
                    <Vacio>No tiene recibos en {gestion}.</Vacio>
                ) : (
                    <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        {recibos.map((r) => (
                            <li key={r.numero} className="flex items-center gap-3 p-3.5 sm:p-4">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-rio-espuma text-rio">
                                    <Receipt className="size-4.5" />
                                </span>
                                <div className="min-w-0 flex-1 leading-tight">
                                    <p className="truncate text-sm font-bold text-rio-profundo">{r.concepto}</p>
                                    <p className="truncate text-xs text-slate-500">
                                        Recibo N° {r.numero} · {fecha(r.emitido_en)} ·{' '}
                                        {r.depositos === 1 ? '1 depósito' : `${r.depositos} depósitos`}
                                    </p>
                                </div>
                                <p className="shrink-0 text-sm font-extrabold text-rio-profundo tabular-nums">
                                    {bs(r.monto_total, institucion.moneda)}
                                </p>
                                {r.descargar && (
                                    <BotonDescarga url={r.descargar} etiqueta={`Descargar el recibo N° ${r.numero}`} />
                                )}
                            </li>
                        ))}
                    </ul>
                ))}
        </LayoutPortal>
    );
}

/**
 * Un trámite pagado: el total en una fila; al tocarla, sus boletas con el
 * comprobante que subió. Se puede pagar con una sola boleta o con varias.
 */
function FilaTramitePagado({ tramite: t, moneda }: { tramite: TramitePagado; moneda: string }) {
    const [abierto, setAbierto] = useState(t.control === 'observado');
    const observado = t.control === 'observado';
    const cuantos = t.depositos.length;

    return (
        <li
            className={cn(
                'overflow-hidden rounded-2xl border bg-white shadow-sm',
                observado ? 'border-rose-200' : 'border-slate-200',
            )}
        >
            <button
                type="button"
                onClick={() => setAbierto((v) => !v)}
                aria-expanded={abierto}
                className="flex w-full items-center gap-3 p-3.5 text-left transition-colors hover:bg-slate-50 sm:p-4"
            >
                <span
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                        observado ? 'bg-rose-100 text-rose-700' : 'bg-rio-espuma text-rio',
                    )}
                >
                    <Landmark className="size-4.5" />
                </span>
                <span className="min-w-0 flex-1 leading-tight">
                    <span className="line-clamp-2 block text-sm font-bold [overflow-wrap:anywhere] text-rio-profundo">
                        {t.concepto}
                    </span>
                    <span className="mt-0.5 line-clamp-2 block text-xs text-slate-500">
                        {cuantos === 1 ? '1 depósito' : `${cuantos} depósitos`}
                        {t.recibos.length > 0
                            ? ` · Recibo N° ${t.recibos.map((r) => r.numero).join(', ')}`
                            : ' · todavía sin recibo'}
                    </span>
                </span>
                <span className="flex shrink-0 flex-col items-end gap-1">
                    <span className="text-sm font-extrabold text-rio-profundo tabular-nums">{bs(t.total, moneda)}</span>
                    <EstadoChip color={t.control_color}>{t.control_etiqueta}</EstadoChip>
                </span>
                <ChevronDown
                    className={cn(
                        'hidden size-4 shrink-0 text-slate-400 transition-transform min-[360px]:block',
                        abierto && 'rotate-180',
                    )}
                />
            </button>

            {abierto && t.recibos.length > 0 && (
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-slate-100 bg-slate-50/70 px-3.5 py-2 sm:px-4">
                    {t.recibos.map((r) => (
                        <span key={r.numero} className="flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                            Recibo N° {r.numero}
                            {r.descargar && (
                                <BotonDescarga url={r.descargar} etiqueta={`Descargar el recibo N° ${r.numero}`} />
                            )}
                        </span>
                    ))}
                </div>
            )}

            {abierto && (
                <ul className="divide-y divide-slate-100 border-t border-slate-100 bg-slate-50/70">
                    {t.depositos.map((d, i) => (
                        <li key={`${d.nro_transaccion}-${i}`} className="px-3.5 py-2.5 sm:px-4">
                            <div className="flex items-center gap-3">
                                <div className="min-w-0 flex-1 leading-tight">
                                    <p className="text-xs font-semibold text-slate-700">
                                        Boleta N° {d.nro_transaccion}
                                    </p>
                                    <p className="text-[11px] text-slate-500">
                                        Depositada el {fecha(d.fecha_deposito)}
                                    </p>
                                </div>
                                <span className="shrink-0 text-xs font-bold text-rio-profundo tabular-nums">
                                    {bs(d.monto, moneda)}
                                </span>
                                <EstadoChip color={d.control_color}>{d.control_etiqueta}</EstadoChip>
                            </div>

                            {d.observacion && (
                                <p className="mt-2 flex items-start gap-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-900">
                                    <MessageSquareWarning className="mt-0.5 size-3.5 shrink-0" />
                                    <span>
                                        «{d.observacion}». Acérquese a ventanilla con la boleta para corregirlo.
                                    </span>
                                </p>
                            )}

                            {d.comprobante && (
                                <a
                                    href={d.comprobante}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-rio hover:underline"
                                >
                                    <FileImage className="size-3.5" />
                                    Ver comprobante
                                    <ExternalLink className="size-3" />
                                </a>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </li>
    );
}

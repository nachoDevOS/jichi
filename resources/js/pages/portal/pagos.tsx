import { Head, router, usePage } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { BotonDescarga, Vacio } from '@/components/portal/piezas';
import LayoutPortal from '@/layouts/layout-portal';
import { bs, fecha } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { ReciboPortal } from '@/types/portal';

/**
 * Sus recibos por gestión: el informe de aportes del Art. 13 del reglamento. El
 * pago se hace en Recaudaciones; cada recibo trae la boleta tal como la validaron.
 */
export default function Pagos({
    gestion,
    gestiones,
    total_pagado,
    recibos,
}: {
    gestion: number;
    gestiones: number[];
    total_pagado: number;
    recibos: ReciboPortal[];
}) {
    const { institucion } = usePage<PageProps>().props;

    // La gestión va a la URL: el servidor filtra y los totales salen de ahí.
    const selector = (
        <select
            aria-label="Gestión"
            value={gestion}
            onChange={(e) => router.get(route('portal.pagos'), { gestion: e.target.value }, { preserveScroll: true })}
            className="rounded-full border-0 bg-white py-1.5 pr-8 pl-3 text-xs font-semibold text-rio-profundo shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-rio-claro"
        >
            {gestiones.map((g) => (
                <option key={g} value={g}>
                    {g}
                </option>
            ))}
        </select>
    );

    return (
        <LayoutPortal titulo="Mis pagos" bajada={`Pagado en ${gestion}: ${bs(total_pagado, institucion.moneda)}`} extra={selector}>
            <Head title="Mis pagos" />

            {recibos.length === 0 ? (
                <Vacio>No tiene pagos en {gestion}.</Vacio>
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
                                    Recibo N° {r.numero} · pagado el {fecha(r.fecha_pago ?? r.emitido_en)}
                                    {r.numero_boleta && <> · boleta {r.numero_boleta}</>}
                                    {r.entidad_bancaria && <> ({r.entidad_bancaria})</>}
                                </p>
                            </div>
                            <p className="shrink-0 text-sm font-extrabold text-rio-profundo tabular-nums">
                                {bs(r.monto_total, institucion.moneda)}
                            </p>
                            {r.descargar && <BotonDescarga url={r.descargar} etiqueta={`Descargar el recibo N° ${r.numero}`} />}
                        </li>
                    ))}
                </ul>
            )}
        </LayoutPortal>
    );
}

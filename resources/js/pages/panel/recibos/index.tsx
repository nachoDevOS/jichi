import { Head, Link, router, usePage } from '@inertiajs/react';
import { ReceiptText, Search, TriangleAlert } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fechaHora } from '@/lib/utils';
import type { PageProps, Paginado } from '@/types';
import type { ReciboFila } from '@/types/caja';

/**
 * ============================================================================
 *  RECIBOS — los comprobantes entregados
 * ============================================================================
 *
 * Es la vista de los PAPELES, con su número correlativo: lo que audita
 * Contabilidad. La otra mitad —cada entrega de dinero— está en «Caja».
 *
 * ----------------------------------------------------------------------------
 *  LA COLUMNA QUE MÁS IMPORTA ES «CUADRA»
 * ----------------------------------------------------------------------------
 *
 * `monto_total` es lo que se IMPRIMIÓ, congelado al emitir. `monto_actual` es
 * lo que HAY hoy en el detalle. Si alguien corrigió un abono después de
 * entregar el papel, los dos números se separan.
 *
 * Eso NO se tapa recalculando al leer: el papel entregado no puede cambiar
 * porque después se corrija algo. Se muestra, que es justamente lo que un
 * arqueo tiene que poder detectar.
 */
export default function IndiceRecibos({
    recibos,
    filtros,
    opcionesPorPagina,
}: {
    recibos: Paginado<ReciboFila>;
    filtros: { buscar: string | null; desde: string | null; hasta: string | null; por_pagina: number };
    opcionesPorPagina: number[];
}) {
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null>) {
        router.get(
            route('recibos.index'),
            { buscar, desde: filtros.desde, hasta: filtros.hasta, ...valores },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    const descuadrados = recibos.data.filter((r) => !r.cuadra).length;

    return (
        <LayoutPanel
            titulo="Recibos"
            descripcion="Los comprobantes numerados. Cada uno puede cubrir varios trámites."
        >
            <Head title="Recibos" />

            <div className="space-y-6">
                {/*
                    El aviso solo aparece si hay alguno descuadrado. Un cartel
                    permanente enseñaría a ignorarlo, que es lo contrario de lo
                    que un aviso viene a hacer.
                */}
                {descuadrados > 0 && (
                    <Card className="border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
                        <CardContent className="flex items-start gap-3 pt-5 text-sm">
                            <TriangleAlert className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
                            <div>
                                <p className="font-medium text-amber-900 dark:text-amber-200">
                                    {descuadrados} recibo(s) no cuadran
                                </p>
                                <p className="text-amber-800/80 dark:text-amber-200/80">
                                    Lo impreso en el papel entregado ya no coincide con la suma de sus
                                    abonos. Alguien corrigió un cobro después de emitirlo.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* `min-w-0`: sin él la tarjeta se estira al ancho de la tabla. */}
                <Card className="min-w-0">
                    <CardContent className="space-y-4 p-0">
                        <form
                            onSubmit={(e: FormEvent) => {
                                e.preventDefault();
                                filtrar({});
                            }}
                            className="flex flex-wrap gap-2 p-5 pb-0"
                        >
                            <Input
                                value={buscar}
                                onChange={(e) => setBuscar(e.target.value)}
                                placeholder="Buscar por número, nombre o NIT…"
                                className="min-w-48 flex-1"
                            />

                            <Input
                                type="date"
                                value={filtros.desde ?? ''}
                                onChange={(e) => filtrar({ desde: e.target.value || null })}
                                className="w-auto"
                                aria-label="Desde"
                            />

                            <Input
                                type="date"
                                value={filtros.hasta ?? ''}
                                onChange={(e) => filtrar({ hasta: e.target.value || null })}
                                className="w-auto"
                                aria-label="Hasta"
                            />

                            <Select
                                value={filtros.por_pagina}
                                onChange={(e) => filtrar({ por_pagina: Number(e.target.value) })}
                                className="w-auto"
                            >
                                {opcionesPorPagina.map((n) => (
                                    <option key={n} value={n}>
                                        {n} filas
                                    </option>
                                ))}
                            </Select>

                            <Button type="submit" variant="outline">
                                <Search className="size-4" />
                            </Button>
                        </form>

                        {recibos.data.length === 0 ? (
                            <EstadoVacio
                                icono={ReceiptText}
                                titulo="Sin recibos emitidos"
                                descripcion={
                                    filtros.buscar || filtros.desde || filtros.hasta
                                        ? 'Ninguno coincide con los filtros.'
                                        : 'Los recibos nacen del cobro: se emiten desde Caja, junto con sus abonos.'
                                }
                            />
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-5 py-2.5 font-medium">N°</th>
                                                <th className="px-5 py-2.5 font-medium">A nombre de</th>
                                                <th className="px-5 py-2.5 font-medium">Concepto</th>
                                                <th className="px-5 py-2.5 text-right font-medium">Total</th>
                                                <th className="px-5 py-2.5 font-medium">Emitido</th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {recibos.data.map((r) => (
                                                <tr key={r.id} className="hover:bg-secondary/50">
                                                    <td className="px-5 py-2.5">
                                                        <Link
                                                            href={route('recibos.show', r.id)}
                                                            className="font-mono font-medium text-primary hover:underline"
                                                        >
                                                            {r.numero_recibo}
                                                        </Link>
                                                        <p className="text-xs text-muted-foreground">
                                                            {r.pagos_count} abono(s)
                                                        </p>
                                                    </td>

                                                    <td className="px-5 py-2.5">
                                                        <p className="font-medium">{r.nombre_factura}</p>
                                                        <p className="font-mono text-xs text-muted-foreground">
                                                            {r.nit_ci_factura}
                                                        </p>
                                                    </td>

                                                    <td className="max-w-xs truncate px-5 py-2.5 text-muted-foreground">
                                                        {r.concepto}
                                                    </td>

                                                    <td className="px-5 py-2.5 text-right tabular-nums">
                                                        <span className="font-medium">
                                                            {bs(r.monto_total, institucion.moneda)}
                                                        </span>

                                                        {!r.cuadra && (
                                                            <>
                                                                <Badge color="amber" className="ml-2">
                                                                    no cuadra
                                                                </Badge>
                                                                <p className="text-xs text-muted-foreground">
                                                                    hoy suma{' '}
                                                                    {bs(r.monto_actual, institucion.moneda)}
                                                                </p>
                                                            </>
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-2.5 text-muted-foreground">
                                                        {fechaHora(r.emitido_en)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <Paginacion paginado={recibos} />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </LayoutPanel>
    );
}

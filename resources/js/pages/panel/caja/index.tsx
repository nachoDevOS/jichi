import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Search, Wallet } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fechaHora } from '@/lib/utils';
import type { OpcionEnum, PageProps, Paginado } from '@/types';
import type { ArqueoDelDia, PagoFila } from '@/types/caja';

/**
 * ============================================================================
 *  CAJA — el listado de ABONOS
 * ============================================================================
 *
 * ----------------------------------------------------------------------------
 *  ESTA PANTALLA MUESTRA ABONOS, NO RECIBOS, Y NO ES LO MISMO
 * ----------------------------------------------------------------------------
 *
 * Un recibo agrupa varios abonos, así que las dos listas nunca tienen la misma
 * cantidad de filas. Acá se mira el DINERO —cada entrega, con su método— que es
 * lo que se cuadra contra el cajón. En «Recibos» se miran los PAPELES, con su
 * correlativo, que es lo que audita Contabilidad.
 *
 * ----------------------------------------------------------------------------
 *  EL ARQUEO ES SIEMPRE DE HOY, AUNQUE SE ESTÉ FILTRANDO OTRO MES
 * ----------------------------------------------------------------------------
 *
 * Es deliberado. Lo que se compara contra el efectivo del cajón antes de cerrar
 * es lo de hoy, y esa pregunta no cambia porque alguien esté mirando marzo. Un
 * total que siguiera al filtro invitaría a cuadrar la caja contra el número
 * equivocado.
 */
export default function IndiceCaja({
    pagos,
    filtros,
    metodos,
    arqueo,
    opcionesPorPagina,
}: {
    pagos: Paginado<PagoFila>;
    filtros: {
        buscar: string | null;
        metodo: string | null;
        desde: string | null;
        hasta: string | null;
        por_pagina: number;
    };
    metodos: OpcionEnum[];
    arqueo: ArqueoDelDia;
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null>) {
        router.get(
            route('caja.index'),
            {
                buscar,
                metodo: filtros.metodo,
                desde: filtros.desde,
                hasta: filtros.hasta,
                ...valores,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Caja"
            descripcion="Cada entrega de dinero, con su método y el trámite al que se aplicó."
            acciones={
                puede('caja.cobrar') && (
                    <Button onClick={() => router.visit(route('caja.create'))}>
                        <Plus className="size-4" />
                        Cobrar
                    </Button>
                )
            }
        >
            <Head title="Caja" />

            <div className="space-y-6">
                {/* ------------------------------------------------- El arqueo */}
                <Card>
                    <CardHeader>
                        <CardTitle>Arqueo de hoy</CardTitle>
                    </CardHeader>

                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-4">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                    Total cobrado
                                </p>
                                <p className="text-3xl font-semibold tabular-nums">
                                    {bs(arqueo.total, institucion.moneda)}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {arqueo.cantidad} abono(s)
                                </p>
                            </div>

                            {arqueo.por_metodo.map((m) => (
                                <div key={m.metodo}>
                                    <Badge color={m.color}>{m.etiqueta}</Badge>
                                    <p className="mt-1 text-xl font-semibold tabular-nums">
                                        {bs(m.total, institucion.moneda)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {m.cantidad} abono(s)
                                    </p>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* ------------------------------------------------- Los abonos */}
                {/* `min-w-0`: sin él la tarjeta se estira al ancho de la tabla y
                    el que termina con barra de desplazamiento es el documento. */}
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
                                placeholder="Buscar por recibo o nombre del comprobante…"
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
                                value={filtros.metodo ?? ''}
                                onChange={(e) => filtrar({ metodo: e.target.value || null })}
                                className="w-auto"
                            >
                                <option value="">Todo método</option>
                                {metodos.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </Select>

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

                        {pagos.data.length === 0 ? (
                            <EstadoVacio
                                icono={Wallet}
                                titulo="Sin cobros"
                                descripcion={
                                    filtros.buscar || filtros.metodo || filtros.desde || filtros.hasta
                                        ? 'Ninguno coincide con los filtros.'
                                        : 'Acá aparece cada entrega de dinero. Se cobra desde el botón de arriba, y un mismo recibo puede cubrir varios trámites.'
                                }
                            />
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-5 py-2.5 font-medium">Recibo</th>
                                                <th className="px-5 py-2.5 font-medium">Concepto</th>
                                                <th className="px-5 py-2.5 font-medium">Titular</th>
                                                <th className="px-5 py-2.5 font-medium">Método</th>
                                                <th className="px-5 py-2.5 text-right font-medium">Monto</th>
                                                <th className="px-5 py-2.5 font-medium">Cobrado</th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {pagos.data.map((p) => (
                                                <tr key={p.id} className="hover:bg-secondary/50">
                                                    <td className="px-5 py-2.5">
                                                        <Link
                                                            href={route('recibos.show', p.recibo_id)}
                                                            className="font-mono font-medium text-primary hover:underline"
                                                        >
                                                            {p.numero_recibo ?? '—'}
                                                        </Link>
                                                        <p className="text-xs text-muted-foreground">
                                                            {p.a_nombre_de ?? '—'}
                                                        </p>
                                                    </td>

                                                    <td className="px-5 py-2.5">{p.concepto}</td>
                                                    <td className="px-5 py-2.5">{p.titular ?? '—'}</td>

                                                    <td className="px-5 py-2.5">
                                                        <Badge color={p.metodo_color}>
                                                            {p.metodo_etiqueta}
                                                        </Badge>
                                                    </td>

                                                    <td className="px-5 py-2.5 text-right font-medium tabular-nums">
                                                        {bs(p.monto_parcial, institucion.moneda)}
                                                    </td>

                                                    <td className="px-5 py-2.5 text-muted-foreground">
                                                        {fechaHora(p.cobrado_en)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <Paginacion paginado={pagos} />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </LayoutPanel>
    );
}

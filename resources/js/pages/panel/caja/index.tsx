import { Head, Link, router, usePage } from '@inertiajs/react';
import { Paperclip, Plus, Search, Wallet } from 'lucide-react';
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
 *  CAJA — el listado de ABONOS
 */
export default function IndiceCaja({
    pagos,
    filtros,
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
            descripcion="Cada depósito bancario, con su boleta y el trámite al que se aplicó."
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

                            {/*
                                LAS DOS FECHAS NO SON LA MISMA PREGUNTA, y por eso
                                van los dos números:
                            */}
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                    Depositado hoy, según la boleta
                                </p>
                                <p className="mt-1 text-xl font-semibold tabular-nums">
                                    {bs(arqueo.total_depositado, institucion.moneda)}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {arqueo.cantidad_depositada} depósito(s) — se cruza contra el
                                    extracto del banco
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ------------------------------------------------- Los abonos */}
                {/* `min-w-0`: sin él la tarjeta se estira al ancho de la tabla y
                    el que termina con barra de desplazamiento es el documento. */}
                <Card className="min-w-0">
                    <CardContent className="space-y-4 p-0">
                        {/*
                            LA BARRA DE ARRIBA DE LA TABLA, la misma del padrón de
                            beneficiarios: «Mostrar N» a la izquierda y los filtros
                            pegados al buscador a la derecha, sobre doce columnas.
                        */}
                        <div className="grid grid-cols-1 gap-3 border-b border-border p-4 sm:grid-cols-12 sm:items-end">
                            <label className="flex items-center gap-2 text-sm text-muted-foreground sm:col-span-3">
                                Mostrar
                                <Select
                                    className="w-auto"
                                    value={filtros.por_pagina}
                                    onChange={(e) => filtrar({ por_pagina: Number(e.target.value) })}
                                    aria-label="Registros por página"
                                >
                                    {opcionesPorPagina.map((n) => (
                                        <option key={n} value={n}>
                                            {n}
                                        </option>
                                    ))}
                                </Select>
                                registros
                            </label>

                            {/* Todos filtran lo mismo —qué filas se ven— así que van
                                juntos: separados se leen como controles sueltos. */}
                            <div className="flex flex-wrap items-center justify-end gap-2 sm:col-span-9">
                                <Input
                                    type="date"
                                    className="w-auto"
                                    value={filtros.desde ?? ''}
                                    onChange={(e) => filtrar({ desde: e.target.value || null })}
                                    aria-label="Desde"
                                />

                                <Input
                                    type="date"
                                    className="w-auto"
                                    value={filtros.hasta ?? ''}
                                    onChange={(e) => filtrar({ hasta: e.target.value || null })}
                                    aria-label="Hasta"
                                />

                                {/*
                                    El buscador es un <form> propio para que el Enter lo
                                    envíe. No busca mientras se teclea: cada tecla sería
                                    una consulta que recorre la tabla entera.
                                */}
                                <form
                                    className="relative min-w-48 flex-1 sm:max-w-sm"
                                    onSubmit={(e: FormEvent) => {
                                        e.preventDefault();
                                        filtrar({});
                                    }}
                                >
                                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        className="pl-9"
                                        placeholder="Buscar…"
                                        value={buscar}
                                        onChange={(e) => setBuscar(e.target.value)}
                                        aria-label="Buscar por recibo o nombre del comprobante"
                                    />
                                </form>
                            </div>
                        </div>

                        {pagos.data.length === 0 ? (
                            <EstadoVacio
                                icono={Wallet}
                                titulo="Sin cobros"
                                descripcion={
                                    filtros.buscar || filtros.desde || filtros.hasta
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
                                                <th className="px-5 py-2.5 font-medium">Boleta</th>
                                                <th className="px-5 py-2.5 text-right font-medium">Monto</th>
                                                <th className="px-5 py-2.5 font-medium">Cobrado</th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {pagos.data.map((p) => (
                                                <tr key={p.id} className="hover:bg-secondary/50">
                                                    {/* SIN ENLACE CUANDO TODAVÍA NO HAY
                                                        PAPEL: los depósitos de un
                                                        aprovechamiento se cargan antes de
                                                        que el recibo exista —sale uno solo
                                                        al enviarlo a revisión— y un Link a
                                                        un id nulo lleva a una pantalla que
                                                        no existe. */}
                                                    <td className="px-5 py-2.5">
                                                        {p.recibo_id ? (
                                                            <Link
                                                                href={route('recibos.show', p.recibo_id)}
                                                                className="font-mono font-medium text-primary hover:underline"
                                                            >
                                                                {p.numero_recibo ?? '—'}
                                                            </Link>
                                                        ) : (
                                                            <span
                                                                className="font-mono text-muted-foreground"
                                                                title="El recibo se emite al enviar el trámite a revisión"
                                                            >
                                                                Sin recibo
                                                            </span>
                                                        )}

                                                        <p className="text-xs text-muted-foreground">
                                                            {p.a_nombre_de ?? '—'}
                                                        </p>
                                                    </td>

                                                    <td className="px-5 py-2.5">{p.concepto}</td>
                                                    <td className="px-5 py-2.5">{p.titular ?? '—'}</td>

                                                    <td className="px-5 py-2.5">
                                                        {/*
                                                            LA BOLETA SE ABRE DESDE ACÁ, que es
                                                            donde se cuadra la caja contra el
                                                            extracto del banco. Sin el enlace habría
                                                            que entrar a cada recibo para verla, y
                                                            cuadrar un día son decenas de filas.

                                                        */}
                                                        {p.comprobante_url && (
                                                            <a
                                                                href={p.comprobante_url}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="mt-1 flex items-center gap-1 text-xs text-primary hover:underline"
                                                                title="Abrir la boleta del depósito"
                                                            >
                                                                <Paperclip className="size-3" />
                                                                {p.nro_transaccion ?? 'boleta'}
                                                            </a>
                                                        )}
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

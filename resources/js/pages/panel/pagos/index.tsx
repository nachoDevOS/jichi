import { Head, Link, router, usePage } from '@inertiajs/react';
import { Receipt, Search } from 'lucide-react';
import { useState } from 'react';
import { AccionesControl, EstadoControl } from '@/components/panel/pagos/control-deposito';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { OpcionEnum, Paginado, PageProps } from '@/types';
import type { PagoFila } from '@/types/pagos';

/**
 * El libro de caja: todos los depósitos, para cuadrar contra el extracto del
 * banco.
 *
 * Es la única pantalla de pagos que no cuelga de un trámite, porque justamente
 * lo que se quiere acá es mirarlos todos juntos. El alta de un depósito sí
 * cuelga de lo que se está cobrando: un pago sin origen no significa nada.
 *
 * LISTA LAS TRES COSAS QUE SE COBRAN: trámites, faenas y guías. Tiene que ser
 * así, porque lo que se cuadra contra el extracto del banco es todo lo que
 * entró, no una parte. La columna «Concepto» dice de cuál viene cada fila.
 *
 * NO HAY BOTÓN DE ANULAR. Una boleta cargada mal se corrige, y la tabla
 * `auditorias` deja registrado el valor anterior, quién lo cambió y cuándo. Un
 * pago «anulado» que sigue en la lista solo invita a sumarlo por error.
 */
export default function IndicePagos({
    pagos,
    filtros,
    validaciones,
    total,
    opcionesPorPagina,
}: {
    pagos: Paginado<PagoFila>;
    filtros: {
        buscar: string | null;
        desde: string | null;
        hasta: string | null;
        validacion: string | null;
        por_pagina: number;
    };
    validaciones: OpcionEnum[];
    /** Suma de TODO lo filtrado, no solo de la página visible. */
    total: number;
    opcionesPorPagina: number[];
}) {
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null>) {
        router.get(
            route('pagos.index'),
            {
                buscar,
                desde: filtros.desde,
                hasta: filtros.hasta,
                validacion: filtros.validacion,
                por_pagina: filtros.por_pagina,
                ...valores,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Pagos"
            descripcion="Libro de caja: depósitos registrados por trámites, faenas y guías."
        >
            <Head title="Pagos" />

            <Card>
                {/* La misma barra de todos los listados: «Mostrar N» a la
                    izquierda, buscador a la derecha en cuatro columnas. */}
                <div className="grid grid-cols-1 gap-3 border-b border-border p-4 sm:grid-cols-12 sm:items-end">
                    <label className="flex items-center gap-2 text-sm text-muted-foreground sm:col-span-3">
                        Mostrar
                        <Select
                            className="w-auto"
                            value={filtros.por_pagina}
                            onChange={(e) => filtrar({ por_pagina: e.target.value })}
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

                    {/* «Mostrame lo que falta controlar» es la primera
                        pregunta de quien revisa, así que el estado del control
                        es un filtro y no solo una columna. */}
                    <Select
                        className="sm:col-span-2 sm:col-start-4"
                        value={filtros.validacion ?? ''}
                        onChange={(e) => filtrar({ validacion: e.target.value || null })}
                        aria-label="Estado del control"
                    >
                        <option value="">Todo el control</option>
                        {validaciones.map((v) => (
                            <option key={v.value} value={v.value}>
                                {v.label}
                            </option>
                        ))}
                    </Select>

                    <Input
                        type="date"
                        className="sm:col-span-2"
                        value={filtros.desde ?? ''}
                        onChange={(e) => filtrar({ desde: e.target.value || null })}
                        aria-label="Desde"
                    />

                    <Input
                        type="date"
                        className="sm:col-span-2"
                        value={filtros.hasta ?? ''}
                        onChange={(e) => filtrar({ hasta: e.target.value || null })}
                        aria-label="Hasta"
                    />

                    <form
                        className="relative sm:col-span-3"
                        onSubmit={(e) => {
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
                            aria-label="Buscar por número de transacción"
                        />
                    </form>
                </div>

                {/*
                    El total va arriba y suma TODO lo filtrado, no la página que
                    se está viendo: sumar solo la página respondería una pregunta
                    que nadie hizo.
                */}
                <div className="flex items-baseline justify-between gap-3 border-b border-border px-5 py-3">
                    <span className="text-sm text-muted-foreground">Total del periodo filtrado</span>
                    <span className="text-lg font-semibold tabular-nums">
                        {bs(total, institucion.moneda)}
                    </span>
                </div>

                {pagos.data.length === 0 ? (
                    <EstadoVacio
                        icono={Receipt}
                        titulo="Sin pagos"
                        descripcion="No hay depósitos que coincidan con el filtro."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3 font-medium">Nº transacción</th>
                                    <th className="px-5 py-3 font-medium">Beneficiario</th>
                                    <th className="px-5 py-3 font-medium">Concepto</th>
                                    <th className="px-5 py-3 font-medium">Rubro</th>
                                    <th className="px-5 py-3 text-right font-medium">Monto</th>
                                    <th className="px-5 py-3 font-medium">Fecha</th>
                                    {/* El CONTROL de la boleta, que es otra cosa
                                        que el pago: el dinero entró o no entró, y
                                        esto dice si alguien lo comprobó contra el
                                        extracto del banco. */}
                                    <th className="px-5 py-3 font-medium">Control</th>
                                    <th className="px-5 py-3" />
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border">
                                {pagos.data.map((p) => (
                                    <tr key={p.id} className="hover:bg-secondary/50">
                                        <td className="px-5 py-3 font-mono text-xs">{p.nro_transaccion}</td>
                                        <td className="px-5 py-3">
                                            {/*
                                                El enlace solo cuando el pago es
                                                de un trámite. Faenas y guías no
                                                tienen pantalla propia todavía,
                                                y un <Link> a una ruta que no
                                                existe revienta al construir la
                                                URL, no al hacer clic.
                                            */}
                                            {p.tramite_id !== null ? (
                                                <Link
                                                    href={route('tramites.show', p.tramite_id)}
                                                    className="text-primary hover:underline"
                                                >
                                                    {p.beneficiario ?? '—'}
                                                </Link>
                                            ) : (
                                                <span>{p.beneficiario ?? '—'}</span>
                                            )}
                                            <p className="font-mono text-xs text-muted-foreground">
                                                {p.carnet_registro ?? '—'}
                                            </p>
                                        </td>
                                        <td className="px-5 py-3">{p.concepto}</td>
                                        <td className="px-5 py-3">{p.rubro ?? '—'}</td>
                                        <td className="px-5 py-3 text-right font-medium tabular-nums">
                                            {bs(p.monto, institucion.moneda)}
                                        </td>
                                        <td className="px-5 py-3 text-muted-foreground">
                                            {fecha(p.fecha_pago)}
                                        </td>
                                        <td className="px-5 py-3">
                                            <EstadoControl pago={p} />
                                        </td>
                                        <td className="px-5 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <AccionesControl pago={p} compacto />
                                            </div>
                                            {p.comprobante_url && (
                                                <a
                                                    href={p.comprobante_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-primary hover:underline"
                                                >
                                                    Boleta
                                                </a>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Paginacion paginado={pagos} />
            </Card>
        </LayoutPanel>
    );
}

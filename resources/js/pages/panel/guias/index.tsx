import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Search, Truck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fechaHora } from '@/lib/utils';
import type { OpcionEnum, PageProps, Paginado } from '@/types';
import type { GuiaFila } from '@/types/guias';

/**
 *  LISTADO DE GUÍAS DE MOVIMIENTO
 */
export default function IndiceGuias({
    guias,
    filtros,
    estados,
    opcionesPorPagina,
}: {
    guias: Paginado<GuiaFila>;
    filtros: {
        buscar: string | null;
        estado: string | null;
        piscicultura: boolean | null;
        por_pagina: number;
    };
    estados: OpcionEnum[];
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | boolean | null>) {
        router.get(
            route('guias.index'),
            {
                buscar,
                estado: filtros.estado,
                piscicultura: filtros.piscicultura,
                ...valores,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Guías de movimiento"
            descripcion="Una por traslado. Valen 5 días desde la hora de emisión."
            acciones={
                puede('guias.crear') && (
                    <Button onClick={() => router.visit(route('guias.create'))}>
                        <Plus className="size-4" />
                        Emitir guía
                    </Button>
                )
            }
        >
            <Head title="Guías de movimiento" />

            {/* `min-w-0`: sin él la tarjeta se estira al ancho de la tabla y el
                que termina con barra de desplazamiento es el documento entero. */}
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
                            <Select
                                className="w-auto min-w-36"
                                value={filtros.estado ?? ''}
                                onChange={(e) => filtrar({ estado: e.target.value || null })}
                                aria-label="Filtrar por estado"
                            >
                                <option value="">Todos los estados</option>
                                {estados.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </Select>

                            <Select
                                className="w-auto min-w-36"
                                value={filtros.piscicultura === null ? '' : String(filtros.piscicultura)}
                                onChange={(e) =>
                                    filtrar({ piscicultura: e.target.value === '' ? null : e.target.value })
                                }
                                aria-label="Filtrar por origen"
                            >
                                <option value="">Todo origen</option>
                                <option value="1">Piscicultura</option>
                                <option value="0">De río</option>
                            </Select>

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
                                    aria-label="Buscar por código, persona, origen o destino"
                                />
                            </form>
                        </div>
                    </div>

                    {guias.data.length === 0 ? (
                        <EstadoVacio
                            icono={Truck}
                            titulo="Sin guías emitidas"
                            descripcion={
                                filtros.buscar || filtros.estado || filtros.piscicultura !== null
                                    ? 'Ninguna coincide con los filtros.'
                                    : 'La guía cuelga del carnet de comercializador: la persona necesita credencial vigente de esa actividad.'
                            }
                        />
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">Código</th>
                                            <th className="px-5 py-2.5 font-medium">Beneficiario</th>
                                            <th className="px-5 py-2.5 font-medium">Ruta</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Carga</th>
                                            <th className="px-5 py-2.5 font-medium">Estado</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Cobro</th>
                                            <th className="px-5 py-2.5 font-medium">Vence</th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {guias.data.map((g) => (
                                            <tr key={g.id} className="hover:bg-secondary/50">
                                                <td className="px-5 py-2.5">
                                                    <Link
                                                        href={route('guias.show', g.id)}
                                                        className="font-mono font-medium text-primary hover:underline"
                                                    >
                                                        {g.codigo_guia}
                                                    </Link>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <p className="font-medium">{g.comercializador ?? '—'}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {g.asociacion ?? '—'}
                                                    </p>
                                                </td>

                                                <td className="px-5 py-2.5">{g.ruta}</td>

                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {g.peso_total_kg} kg
                                                    {/*
                                                        La marca de piscicultura va acá y no en una
                                                        columna propia: es un atributo de la CARGA, y
                                                        además explica por qué esa fila cobró la
                                                        mitad.
                                                    */}
                                                    {g.es_piscicultura && (
                                                        <Badge color="sky" className="ml-2">
                                                            criadero
                                                        </Badge>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <Badge color={g.estado_color}>{g.estado_etiqueta}</Badge>
                                                    {g.caducada && (
                                                        <Badge color="amber" className="ml-1">
                                                            sin cerrar
                                                        </Badge>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {g.pagado ? (
                                                        <span className="text-emerald-700 dark:text-emerald-400">
                                                            Pagado
                                                        </span>
                                                    ) : (
                                                        <span className="text-amber-700 dark:text-amber-400">
                                                            debe {bs(g.saldo_pendiente, institucion.moneda)}
                                                        </span>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    {fechaHora(g.fecha_vencimiento)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <Paginacion paginado={guias} />
                        </>
                    )}
                </CardContent>
            </Card>
        </LayoutPanel>
    );
}

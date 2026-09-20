import { Head, Link, router, usePage } from '@inertiajs/react';
import { BadgeCheck, Plus, Search } from 'lucide-react';
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
import { bs, fecha } from '@/lib/utils';
import type { OpcionEnum, PageProps, Paginado } from '@/types';
import type { CarnetFila } from '@/types/carnets';

/**
 *  LISTADO DE CARNETS
 */
export default function IndiceCarnets({
    carnets,
    filtros,
    estados,
    actores,
    opcionesPorPagina,
}: {
    carnets: Paginado<CarnetFila>;
    filtros: { buscar: string | null; estado: string | null; actor: string | null; por_pagina: number };
    estados: OpcionEnum[];
    actores: OpcionEnum[];
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null>) {
        router.get(
            route('carnets.index'),
            { buscar, estado: filtros.estado, actor: filtros.actor, ...valores },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Carnets"
            descripcion="La credencial anual. De ella cuelgan las faenas y las guías."
            acciones={
                puede('carnets.crear') && (
                    <Button onClick={() => router.visit(route('carnets.create'))}>
                        <Plus className="size-4" />
                        Emitir carnet
                    </Button>
                )
            }
        >
            <Head title="Carnets" />

            {/* `min-w-0`: sin él la tarjeta se estira al ancho de la tabla y el
                que termina con barra de desplazamiento es el documento entero. */}
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
                            placeholder="Buscar por cédula, nombre o código…"
                            className="min-w-48 flex-1"
                        />

                        <Select
                            value={filtros.actor ?? ''}
                            onChange={(e) => filtrar({ actor: e.target.value || null })}
                            className="w-auto"
                        >
                            <option value="">Toda actividad</option>
                            {actores.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </Select>

                        <Select
                            value={filtros.estado ?? ''}
                            onChange={(e) => filtrar({ estado: e.target.value || null })}
                            className="w-auto"
                        >
                            <option value="">Todos los estados</option>
                            {estados.map((o) => (
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

                    {carnets.data.length === 0 ? (
                        <EstadoVacio
                            icono={BadgeCheck}
                            titulo="Sin carnets emitidos"
                            descripcion={
                                filtros.buscar || filtros.estado || filtros.actor
                                    ? 'Ninguno coincide con los filtros.'
                                    : 'El carnet es el paso 3 del flujo: la persona ya tiene que estar registrada, y si es pescador, con su cupo otorgado.'
                            }
                        />
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">Titular</th>
                                            <th className="px-5 py-2.5 font-medium">Actividad</th>
                                            <th className="px-5 py-2.5 font-medium">Asociación</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Cupo</th>
                                            <th className="px-5 py-2.5 font-medium">Estado</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Cobro</th>
                                            <th className="px-5 py-2.5 font-medium">Vence</th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {carnets.data.map((c) => (
                                            <tr key={c.id} className="hover:bg-secondary/50">
                                                <td className="px-5 py-2.5">
                                                    <Link
                                                        href={route('carnets.show', c.id)}
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {c.beneficiario ?? '—'}
                                                    </Link>
                                                    <p className="font-mono text-xs text-muted-foreground">
                                                        {c.codigo}
                                                    </p>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <Badge color={c.tipo_actor_color}>
                                                        {c.tipo_actor_etiqueta}
                                                    </Badge>
                                                </td>

                                                <td className="px-5 py-2.5">{c.asociacion ?? '—'}</td>

                                                {/*
                                                    Solo el pescador lleva cupo. El guion no es
                                                    «falta cargarlo»: la comercialización no se
                                                    autoriza por volumen.
                                                */}
                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {c.cupo_kg === null ? (
                                                        <span className="text-muted-foreground">—</span>
                                                    ) : (
                                                        `${c.cupo_kg} kg`
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>
                                                </td>

                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {c.pagado ? (
                                                        <span className="text-emerald-700 dark:text-emerald-400">
                                                            Pagado
                                                        </span>
                                                    ) : (
                                                        <span className="text-amber-700 dark:text-amber-400">
                                                            debe {bs(c.saldo_pendiente, institucion.moneda)}
                                                        </span>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    {fecha(c.fecha_vencimiento)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <Paginacion paginado={carnets} />
                        </>
                    )}
                </CardContent>
            </Card>
        </LayoutPanel>
    );
}

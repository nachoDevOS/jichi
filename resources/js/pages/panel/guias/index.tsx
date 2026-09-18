import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, Truck } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { fecha } from '@/lib/utils';
import type { OpcionEnum, Paginado } from '@/types';
import type { GuiaFila } from '@/types/guias';

/**
 * El listado de guías únicas de transporte.
 *
 * Mismo criterio que el de faenas: NO hay editar ni borrar. El número salió del
 * talonario y el papel viaja con la carga; una guía mal emitida se ANULA y se
 * emite otra. Lo único que se corrige es el DETALLE, desde la ficha, porque el
 * peso real recién se conoce en la balanza.
 */
export default function IndiceGuias({
    guias,
    filtros,
    estados,
    transportes,
    opcionesPorPagina,
}: {
    guias: Paginado<GuiaFila>;
    filtros: {
        buscar: string | null;
        estado: string | null;
        transporte: string | null;
        por_pagina: number;
    };
    estados: OpcionEnum[];
    transportes: OpcionEnum[];
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null>) {
        router.get(
            route('guias.index'),
            {
                buscar,
                estado: filtros.estado,
                transporte: filtros.transporte,
                por_pagina: filtros.por_pagina,
                ...valores,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Trámites de guía"
            descripcion="Guía única de transporte de productos ictícolas, sobre un carnet de comercializador vigente."
            acciones={
                puede('guias.crear') && (
                    <Button onClick={() => router.visit(route('guias.create'))}>
                        <Plus className="size-4" />
                        Nueva guía
                    </Button>
                )
            }
        >
            <Head title="Guías de transporte" />

            <Card>
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

                    <Select
                        className="sm:col-span-2 sm:col-start-5"
                        value={filtros.estado ?? ''}
                        onChange={(e) => filtrar({ estado: e.target.value || null })}
                        aria-label="Estado"
                    >
                        <option value="">Todos los estados</option>
                        {estados.map((e) => (
                            <option key={e.value} value={e.value}>
                                {e.label}
                            </option>
                        ))}
                    </Select>

                    <Select
                        className="sm:col-span-2"
                        value={filtros.transporte ?? ''}
                        onChange={(e) => filtrar({ transporte: e.target.value || null })}
                        aria-label="Tipo de transporte"
                    >
                        <option value="">Todo transporte</option>
                        {transportes.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </Select>

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
                            placeholder="Nº, transportista, destino…"
                            value={buscar}
                            onChange={(e) => setBuscar(e.target.value)}
                            aria-label="Buscar guías"
                        />
                    </form>
                </div>

                {guias.data.length === 0 ? (
                    <EstadoVacio
                        icono={Truck}
                        titulo="Sin guías"
                        descripcion="No hay guías de transporte que coincidan con el filtro."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3 font-medium">Nº guía</th>
                                    <th className="px-5 py-3 font-medium">Titular</th>
                                    <th className="px-5 py-3 font-medium">Recorrido</th>
                                    <th className="px-5 py-3 font-medium">Transporte</th>
                                    <th className="px-5 py-3 text-right font-medium">Kg</th>
                                    <th className="px-5 py-3 font-medium">Estado</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border">
                                {guias.data.map((g) => (
                                    <tr key={g.id} className="hover:bg-secondary/50">
                                        <td className="px-5 py-3">
                                            <Link
                                                href={route('guias.show', g.id)}
                                                className="font-mono text-xs text-primary hover:underline"
                                            >
                                                {g.nro_guia}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {fecha(g.fecha)}
                                            </p>
                                        </td>
                                        <td className="px-5 py-3">
                                            <p className="font-medium">{g.beneficiario ?? '—'}</p>
                                            <p className="font-mono text-xs text-muted-foreground">
                                                {g.carnet_registro ?? '—'}
                                            </p>
                                        </td>
                                        <td className="px-5 py-3 text-muted-foreground">
                                            {g.origen_lugar ?? '—'} → {g.destino_lugar ?? '—'}
                                        </td>
                                        <td className="px-5 py-3">
                                            <Badge color={g.transporte_color}>
                                                {g.transporte_etiqueta}
                                            </Badge>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {g.transporte_nombre ?? '—'}
                                            </p>
                                        </td>
                                        <td className="px-5 py-3 text-right font-medium tabular-nums">
                                            {g.total_kg.toFixed(2)}
                                        </td>
                                        <td className="px-5 py-3">
                                            <Badge color={g.estado_color}>{g.estado_etiqueta}</Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Paginacion paginado={guias} />
            </Card>
        </LayoutPanel>
    );
}

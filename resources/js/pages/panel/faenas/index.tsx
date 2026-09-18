import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, Ship } from 'lucide-react';
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
import type { FaenaFila } from '@/types/faenas';

/**
 * El listado de faenas: los permisos por salida de pesca.
 *
 * ----------------------------------------------------------------------------
 *  NO HAY BOTÓN DE EDITAR NI DE BORRAR
 * ----------------------------------------------------------------------------
 *
 * Una faena es un papel del talonario que la persona se llevó en el momento.
 * Editarla dejaría el sistema diciendo una cosa y el papel otra; borrarla
 * dejaría un hueco en la serie y liberaría un número que el índice único
 * volvería a aceptar. Una faena mal emitida se ANULA desde su ficha, con el
 * motivo escrito, y se emite otra con un número nuevo.
 */
export default function IndiceFaenas({
    faenas,
    filtros,
    estados,
    opcionesPorPagina,
}: {
    faenas: Paginado<FaenaFila>;
    filtros: {
        buscar: string | null;
        estado: string | null;
        desde: string | null;
        hasta: string | null;
        por_pagina: number;
    };
    estados: OpcionEnum[];
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null>) {
        router.get(
            route('faenas.index'),
            {
                buscar,
                estado: filtros.estado,
                desde: filtros.desde,
                hasta: filtros.hasta,
                por_pagina: filtros.por_pagina,
                ...valores,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Trámites de faena"
            descripcion="Permisos por salida de pesca, emitidos sobre un carnet de pescador vigente."
            acciones={
                puede('faenas.crear') && (
                    <Button onClick={() => router.visit(route('faenas.create'))}>
                        <Plus className="size-4" />
                        Nueva faena
                    </Button>
                )
            }
        >
            <Head title="Faenas" />

            <Card>
                {/* La misma barra de todos los listados: «Mostrar N» a la
                    izquierda, filtros y buscador a la derecha. */}
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
                        className="sm:col-span-2"
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

                    <Input
                        type="date"
                        className="sm:col-span-2"
                        value={filtros.desde ?? ''}
                        onChange={(e) => filtrar({ desde: e.target.value || null })}
                        aria-label="Salidas desde"
                    />

                    <Input
                        type="date"
                        className="sm:col-span-2"
                        value={filtros.hasta ?? ''}
                        onChange={(e) => filtrar({ hasta: e.target.value || null })}
                        aria-label="Salidas hasta"
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
                            placeholder="Nº, embarcación o titular…"
                            value={buscar}
                            onChange={(e) => setBuscar(e.target.value)}
                            aria-label="Buscar faenas"
                        />
                    </form>
                </div>

                {faenas.data.length === 0 ? (
                    <EstadoVacio
                        icono={Ship}
                        titulo="Sin faenas"
                        descripcion="No hay permisos de salida que coincidan con el filtro."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3 font-medium">Nº permiso</th>
                                    <th className="px-5 py-3 font-medium">Titular</th>
                                    <th className="px-5 py-3 font-medium">Embarcación</th>
                                    <th className="px-5 py-3 font-medium">Salida → desembarque</th>
                                    <th className="px-5 py-3 text-right font-medium">Autorizado</th>
                                    <th className="px-5 py-3 font-medium">Estado</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border">
                                {faenas.data.map((f) => (
                                    <tr key={f.id} className="hover:bg-secondary/50">
                                        <td className="px-5 py-3">
                                            <Link
                                                href={route('faenas.show', f.id)}
                                                className="font-mono text-xs text-primary hover:underline"
                                            >
                                                {f.nro_permiso}
                                            </Link>
                                        </td>
                                        <td className="px-5 py-3">
                                            <p className="font-medium">{f.beneficiario ?? '—'}</p>
                                            <p className="font-mono text-xs text-muted-foreground">
                                                {f.carnet_registro ?? '—'}
                                            </p>
                                        </td>
                                        <td className="px-5 py-3">
                                            <p>{f.embarcacion ?? '—'}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {f.comandante_barco ?? '—'}
                                            </p>
                                        </td>
                                        <td className="px-5 py-3 text-muted-foreground">
                                            {fecha(f.fecha_salida)} → {fecha(f.fecha_desembarque)}
                                        </td>
                                        <td className="px-5 py-3 text-right font-medium tabular-nums">
                                            {f.cantidad ?? '—'}
                                        </td>
                                        <td className="px-5 py-3">
                                            <Badge color={f.estado_color}>{f.estado_etiqueta}</Badge>

                                            {/*
                                                El saldo se avisa acá y no en una
                                                columna aparte: la mayoría están
                                                pagadas, y una columna casi
                                                siempre vacía gasta ancho que en
                                                el celular no sobra.
                                            */}
                                            {!f.pagada && f.estado === 'emitido' && (
                                                <p className="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                                    Debe {f.saldo.toFixed(2)}
                                                </p>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Paginacion paginado={faenas} />
            </Card>
        </LayoutPanel>
    );
}

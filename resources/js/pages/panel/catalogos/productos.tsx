import { Head, Link, router, usePage } from '@inertiajs/react';
import { Fish, History, Pencil, Plus, Search, TriangleAlert } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { CeldaSireb } from '@/components/panel/catalogos/celda-sireb';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn } from '@/lib/utils';
import type { PageProps, Paginado } from '@/types';
import type { ProductoFila } from '@/types/catalogos';

/**
 *  Catálogo de productos hidrobiológicos — el cuadro D de la guía
 */
export default function CatalogoProductos({
    productos,
    filtros,
    opcionesPorPagina,
    sirebDisponible,
}: {
    productos: Paginado<ProductoFila>;
    filtros: { buscar: string | null; por_pagina: number };
    opcionesPorPagina: number[];
    /** false = SIREB no respondió: los precios no se pueden mostrar. */
    sirebDisponible: boolean;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null> = {}) {
        router.get(
            route('productos.index'),
            { buscar, ...valores },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Productos hidrobiológicos"
            descripcion="Las especies del cuadro D de la guía y su tarifa por kilo en Recaudaciones (SIREB)."
            acciones={
                puede('catalogos.gestionar') && (
                    <Link href={route('productos.create')} className={cn(buttonVariants())}>
                        <Plus className="size-4" />
                        Nuevo producto
                    </Link>
                )
            }
        >
            <Head title="Productos hidrobiológicos" />

            <div className="space-y-6">
                {!sirebDisponible && (
                    <Card className="border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
                        <CardContent className="flex items-start gap-3 pt-5 text-sm">
                            <TriangleAlert className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
                            <p className="text-amber-900 dark:text-amber-200">
                                Recaudaciones (SIREB) no responde: no se pueden mostrar los precios.
                            </p>
                        </CardContent>
                    </Card>
                )}

                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle>Registrados</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4 p-0">
                        <div className="grid grid-cols-1 gap-3 border-y border-border p-4 sm:grid-cols-12 sm:items-end">
                            <label className="flex items-center gap-2 text-sm text-muted-foreground sm:col-span-4">
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

                            <div className="flex flex-wrap items-center justify-end gap-2 sm:col-span-8">
                                <form
                                    className="relative min-w-48 flex-1 sm:max-w-sm"
                                    onSubmit={(e: FormEvent) => {
                                        e.preventDefault();
                                        filtrar();
                                    }}
                                >
                                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        className="pl-9"
                                        placeholder="Buscar…"
                                        value={buscar}
                                        onChange={(e) => setBuscar(e.target.value)}
                                        aria-label="Buscar por nombre del producto"
                                    />
                                </form>
                            </div>
                        </div>

                        {productos.data.length === 0 ? (
                            <EstadoVacio
                                icono={Fish}
                                titulo="Sin productos"
                                descripcion={
                                    filtros.buscar
                                        ? 'Ninguno coincide con la búsqueda.'
                                        : 'El catálogo está vacío: sin productos no se puede cargar el detalle de una guía.'
                                }
                            />
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-5 py-2.5 font-medium">Producto</th>
                                                <th className="px-5 py-2.5 font-medium">Estado</th>
                                                <th className="px-5 py-2.5 text-right font-medium">En guías</th>
                                                <th className="px-5 py-2.5 font-medium">SIREB</th>
                                                <th className="px-5 py-2.5 text-right font-medium">Precio / kg</th>
                                                <th className="px-5 py-2.5" />
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {productos.data.map((p) => (
                                                <tr key={p.id} className="hover:bg-secondary/50">
                                                    <td className="px-5 py-2.5 font-medium">{p.nombre}</td>

                                                    <td className="px-5 py-2.5">
                                                        <Badge color={p.estado ? 'emerald' : 'slate'}>
                                                            {p.estado ? 'Activo' : 'Inactivo'}
                                                        </Badge>
                                                    </td>

                                                    {/* Por esto no hay papelera: las guías que lo
                                                        usaron lo siguen nombrando. */}
                                                    <td className="px-5 py-2.5 text-right tabular-nums text-muted-foreground">
                                                        {p.detalles_count || '—'}
                                                    </td>

                                                    <td className="px-5 py-2.5">
                                                        <CeldaSireb
                                                            servicioId={p.servicio_sireb}
                                                            tarifaId={p.tarifa_sireb}
                                                            servicio={p.sireb_servicio}
                                                            etiqueta={p.sireb_etiqueta}
                                                        />
                                                    </td>

                                                    <td className="px-5 py-2.5 text-right tabular-nums">
                                                        {p.precio !== null ? bs(p.precio, institucion.moneda) : '—'}
                                                    </td>

                                                    <td className="px-5 py-2.5 text-right">
                                                        <div className="flex justify-end gap-2">
                                                            <Link
                                                                href={route('productos.show', p.id)}
                                                                className={cn(buttonVariants({ variant: 'ver', size: 'sm' }))}
                                                                title="Historial de SIREB"
                                                            >
                                                                <History className="size-4" />
                                                            </Link>

                                                            {puede('catalogos.gestionar') && (
                                                                <Link
                                                                    href={route('productos.edit', p.id)}
                                                                    className={cn(buttonVariants({ variant: 'editar', size: 'sm' }))}
                                                                    title="Editar"
                                                                >
                                                                    <Pencil className="size-4" />
                                                                </Link>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <Paginacion paginado={productos} />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </LayoutPanel>
    );
}

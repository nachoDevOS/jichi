import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Fish, Pencil, Plus, Search, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { PageProps, Paginado } from '@/types';
import type { ProductoFila } from '@/types/catalogos';

/**
 *  CATÁLOGO DE PRODUCTOS HIDROBIOLÓGICOS — el cuadro D de la guía
 */
export default function CatalogoProductos({
    productos,
    filtros,
    opcionesPorPagina,
}: {
    productos: Paginado<ProductoFila>;
    filtros: { buscar: string | null; por_pagina: number };
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const [editando, setEditando] = useState<ProductoFila | 'nuevo' | null>(null);

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
            descripcion="Las especies del cuadro D de la guía y su tasa por kilo. Lo que se cobra por una guía es la suma de su cuadro D."
            acciones={
                puede('catalogos.gestionar') && (
                    <Button onClick={() => setEditando('nuevo')}>
                        <Plus className="size-4" />
                        Nuevo producto
                    </Button>
                )
            }
        >
            <Head title="Productos hidrobiológicos" />

            <div className="space-y-6">
                {editando !== null && puede('catalogos.gestionar') && (
                    <FormularioProducto
                        // Ver el comentario de la `key` en asociaciones.tsx.
                        key={editando === 'nuevo' ? 'nuevo' : editando.id}
                        producto={editando === 'nuevo' ? null : editando}
                        onCerrar={() => setEditando(null)}
                    />
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
                                                <th className="px-5 py-2.5 text-right font-medium">Precio por kg</th>
                                                <th className="px-5 py-2.5 font-medium">Estado</th>
                                                <th className="px-5 py-2.5 text-right font-medium">En guías</th>
                                                <th className="px-5 py-2.5" />
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {productos.data.map((p) => (
                                                <tr key={p.id} className="hover:bg-secondary/50">
                                                    <td className="px-5 py-2.5 font-medium">{p.nombre}</td>

                                                    <td className="px-5 py-2.5 text-right tabular-nums">
                                                        {bs(p.precio_kg, institucion.moneda)}
                                                    </td>

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

                                                    <td className="px-5 py-2.5 text-right">
                                                        {puede('catalogos.gestionar') && (
                                                            <Button
                                                                variant="editar"
                                                                size="sm"
                                                                title="Editar"
                                                                onClick={() => setEditando(p)}
                                                            >
                                                                <Pencil className="size-4" />
                                                            </Button>
                                                        )}
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

/** Alta o corrección de un producto. */
function FormularioProducto({ producto, onCerrar }: { producto: ProductoFila | null; onCerrar: () => void }) {
    const esAlta = producto === null;

    const form = useForm({
        nombre: producto?.nombre ?? '',
        precio_kg: producto ? String(producto.precio_kg) : '',
        estado: producto?.estado ?? true,
    });

    function enviar(e: FormEvent) {
        e.preventDefault();

        const opciones = { preserveScroll: true, onSuccess: () => onCerrar() };

        if (esAlta) {
            form.post(route('productos.store'), opciones);
        } else {
            form.put(route('productos.update', producto.id), opciones);
        }
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                <CardTitle>{esAlta ? 'Nuevo producto' : 'Editar producto'}</CardTitle>

                <Button variant="ghost" size="sm" onClick={onCerrar} aria-label="Cerrar">
                    <X className="size-4" />
                </Button>
            </CardHeader>

            <CardContent>
                <form onSubmit={enviar} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Campo etiqueta="Nombre" htmlFor="nombre" error={form.errors.nombre} obligatorio>
                            <Input
                                id="nombre"
                                value={form.data.nombre}
                                onChange={(e) => form.setData('nombre', e.target.value)}
                                aria-invalid={Boolean(form.errors.nombre)}
                                placeholder="Surubí"
                            />
                        </Campo>

                        <Campo
                            etiqueta="Precio por kilo (Bs)"
                            htmlFor="precio_kg"
                            error={form.errors.precio_kg}
                            ayuda="Desde 0,20 Bs. Es lo que se cobra por kilo en la guía; cambiarlo no toca las guías ya emitidas."
                            obligatorio
                        >
                            <Input
                                id="precio_kg"
                                type="number"
                                step="0.01"
                                min={0.2}
                                value={form.data.precio_kg}
                                onChange={(e) => form.setData('precio_kg', e.target.value)}
                                aria-invalid={Boolean(form.errors.precio_kg)}
                            />
                        </Campo>

                        <Campo etiqueta="Estado" htmlFor="estado" error={form.errors.estado}>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    id="estado"
                                    type="checkbox"
                                    checked={form.data.estado}
                                    onChange={(e) => form.setData('estado', e.target.checked)}
                                    className="size-4 rounded border-input"
                                />
                                Activo: se puede elegir en una guía
                            </label>
                        </Campo>
                    </div>

                    <div className="flex gap-2 pt-1">
                        <Button type="submit" disabled={form.processing}>
                            {esAlta ? 'Registrar producto' : 'Guardar cambios'}
                        </Button>

                        <Button type="button" variant="outline" onClick={onCerrar}>
                            Cancelar
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

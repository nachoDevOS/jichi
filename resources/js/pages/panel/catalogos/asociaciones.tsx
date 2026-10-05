import { Head, Link, router } from '@inertiajs/react';
import { Building2, Pencil, Plus, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { cn } from '@/lib/utils';
import type { OpcionEnum, Paginado } from '@/types';
import type { AsociacionFila } from '@/types/catalogos';

/**
 *  Catálogo de asociaciones — y el patrón de las tres pantallas de catálogo
 */
export default function CatalogoAsociaciones({
    asociaciones,
    filtros,
    estados,
    opcionesPorPagina,
}: {
    asociaciones: Paginado<AsociacionFila>;
    filtros: { buscar: string | null; estado: string | null; por_pagina: number };
    estados: OpcionEnum[];
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null> = {}) {
        router.get(route('asociaciones.index'), { buscar, estado: filtros.estado, ...valores }, {
            preserveState: true,
            preserveScroll: true,
            // replace evita llenar el historial con una entrada por búsqueda:
            // «atrás» debe volver a la pantalla anterior, no a la búsqueda
            // anterior.
            replace: true,
        });
    }

    return (
        <LayoutPanel
            titulo="Asociaciones"
            descripcion="Los gremios que certifican al beneficiario. Se imprimen en el carnet y en las guías."
            acciones={
                puede('catalogos.gestionar') && (
                    <Link href={route('asociaciones.create')} className={cn(buttonVariants())}>
                        <Plus className="size-4" />
                        Nueva asociación
                    </Link>
                )
            }
        >
            <Head title="Asociaciones" />

            <div className="space-y-6">
                {/* ------------------------------------------------------ Tabla */}
                {/*
                    `min-w-0` NO ES DECORACIÓN. Un elemento de grilla arranca con
                    `min-width: auto` —«no te encojas por debajo de tu
                    contenido»— así que la tarjeta se estiraría al ancho de la
                    tabla y el que terminaría con barra de desplazamiento sería
                    el DOCUMENTO ENTERO. En el celular eso corre de costado el
                    menú y el encabezado para leer una columna.
                */}
                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle>Registradas</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4 p-0">
                        {/*
                            LA BARRA DE ARRIBA DE LA TABLA, la misma del padrón
                            de beneficiarios: «Mostrar N» a la izquierda y los
                            filtros pegados al buscador a la derecha.
                        */}
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

                            {/* Los dos filtran lo mismo —qué filas se ven— así
                                que van juntos, pegados al buscador. */}
                            <div className="flex flex-wrap items-center justify-end gap-2 sm:col-span-8">
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

                                {/*
                                    El buscador es un <form> propio para que el
                                    Enter lo envíe. No busca mientras se teclea:
                                    cada tecla sería una consulta.
                                */}
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
                                        aria-label="Buscar por nombre, sigla o representante"
                                    />
                                </form>
                            </div>
                        </div>

                        {asociaciones.data.length === 0 ? (
                            <EstadoVacio
                                icono={Building2}
                                titulo="Sin asociaciones"
                                descripcion={
                                    filtros.buscar
                                        ? 'Ninguna coincide con la búsqueda.'
                                        : 'Cargue los gremios de la resolución: sin al menos uno no se puede emitir un carnet.'
                                }
                            />
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-5 py-2.5 font-medium">Asociación</th>
                                                <th className="px-5 py-2.5 font-medium">Estado</th>
                                                <th className="px-5 py-2.5 text-right font-medium">En uso</th>
                                                <th className="px-5 py-2.5" />
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {asociaciones.data.map((a) => (
                                                <tr key={a.id} className="hover:bg-secondary/50">
                                                    <td className="px-5 py-2.5">
                                                        <p className="font-medium">{a.nombre}</p>

                                                        <p className="text-xs text-muted-foreground">
                                                            {/* De la ficha va lo que sirve para
                                                                reconocerla de un vistazo; el resto se
                                                                ve al abrirla. */}
                                                            {[a.sigla, a.datos.representante, a.datos.telefono]
                                                                .filter(Boolean)
                                                                .join(' · ') || '—'}
                                                        </p>
                                                    </td>

                                                    <td className="px-5 py-2.5">
                                                        <Badge color={a.estado_color}>{a.estado_etiqueta}</Badge>
                                                    </td>

                                                    {/*
                                                        Los dos conteos juntos: es lo que
                                                        explica por qué no hay papelera.
                                                    */}
                                                    <td className="px-5 py-2.5 text-right tabular-nums text-muted-foreground">
                                                        {a.carnets_count + a.guias_count === 0 ? (
                                                            <span className="opacity-60">—</span>
                                                        ) : (
                                                            <>
                                                                {a.carnets_count} carnet(s)
                                                                <br />
                                                                <span className="text-xs">
                                                                    {a.guias_count} guía(s)
                                                                </span>
                                                            </>
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-2.5 text-right">
                                                        {puede('catalogos.gestionar') && (
                                                            <Link
                                                                href={route('asociaciones.edit', a.id)}
                                                                title="Editar"
                                                                className={cn(buttonVariants({ variant: 'editar', size: 'sm' }))}
                                                            >
                                                                <Pencil className="size-4" />
                                                            </Link>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <Paginacion paginado={asociaciones} />
                            </>
                        )}
                    </CardContent>
                </Card>

            </div>
        </LayoutPanel>
    );
}

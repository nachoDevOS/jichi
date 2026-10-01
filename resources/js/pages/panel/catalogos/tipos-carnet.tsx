import { Head, Link, router, usePage } from '@inertiajs/react';
import { History, Pencil, Search, Tags, TriangleAlert } from 'lucide-react';
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
import type { OpcionEnum, PageProps, Paginado } from '@/types';
import type { TipoCarnetFila } from '@/types/catalogos';

/**
 *  Catálogo de tipos de carnet
 */
export default function CatalogoTiposCarnet({
    tipos,
    filtros,
    actores,
    opcionesPorPagina,
    sirebDisponible,
}: {
    tipos: Paginado<TipoCarnetFila>;
    filtros: { buscar: string | null; actor: string | null; por_pagina: number };
    actores: OpcionEnum[];
    opcionesPorPagina: number[];
    /** false = SIREB no respondió: los precios no se pueden mostrar. */
    sirebDisponible: boolean;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null> = {}) {
        router.get(
            route('tipos-carnet.index'),
            { buscar, actor: filtros.actor, ...valores },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Tipos de carnet"
            descripcion="El nombre de cada credencial y su tarifa en Recaudaciones (SIREB). Se corrigen; no se agregan."
        >
            <Head title="Tipos de carnet" />

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

                            <div className="flex flex-wrap items-center justify-end gap-2 sm:col-span-8">
                                <Select
                                    className="w-auto min-w-36"
                                    value={filtros.actor ?? ''}
                                    onChange={(e) => filtrar({ actor: e.target.value || null })}
                                    aria-label="Filtrar por actividad"
                                >
                                    <option value="">Toda actividad</option>
                                    {actores.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))}
                                </Select>

                                {/* El buscador es un <form> propio para que el
                                    Enter lo envíe: no busca al teclear. */}
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
                                        aria-label="Buscar por nombre del tipo"
                                    />
                                </form>
                            </div>
                        </div>

                        {tipos.data.length === 0 ? (
                            <EstadoVacio
                                icono={Tags}
                                titulo="Sin tipos de carnet"
                                descripcion={
                                    filtros.buscar || filtros.actor
                                        ? 'Ninguno coincide con los filtros.'
                                        : 'El catálogo está vacío: sin tipo no se puede emitir una credencial.'
                                }
                            />
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-5 py-2.5 font-medium">Tipo</th>
                                                <th className="px-5 py-2.5 font-medium">Actividad</th>
                                                <th className="px-5 py-2.5 text-right font-medium">Emitidos</th>
                                                <th className="px-5 py-2.5 font-medium">SIREB</th>
                                                <th className="px-5 py-2.5 text-right font-medium">Precio</th>
                                                <th className="px-5 py-2.5" />
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {tipos.data.map((t) => (
                                                <tr key={t.id} className="hover:bg-secondary/50">
                                                    <td className="px-5 py-2.5">
                                                        <span className="font-medium">{t.nombre}</span>
                                                        {!t.estado && (
                                                            <Badge color="slate" className="ml-2">
                                                                Fuera de uso
                                                            </Badge>
                                                        )}
                                                    </td>

                                                    {/* Es lo que decide en qué carnets se puede
                                                        elegir: un tipo de comercializador no aparece
                                                        emitiendo uno de pescador. */}
                                                    <td className="px-5 py-2.5">
                                                        <Badge color={t.tipo_actor_color}>
                                                            {t.tipo_actor_etiqueta}
                                                        </Badge>
                                                    </td>

                                                    {/*
                                                        Es lo que explica por qué no hay papelera: con
                                                        carnets emitidos colgando, borrar la fila los
                                                        dejaría sin tipo.
                                                    */}
                                                    <td className="px-5 py-2.5 text-right tabular-nums text-muted-foreground">
                                                        {t.carnets_count || '—'}
                                                    </td>

                                                    <td className="px-5 py-2.5">
                                                        <CeldaSireb
                                                            servicioId={t.servicio_sireb}
                                                            tarifaId={t.tarifa_sireb}
                                                            servicio={t.sireb_servicio}
                                                            etiqueta={t.sireb_etiqueta}
                                                        />
                                                    </td>

                                                    <td className="px-5 py-2.5 text-right tabular-nums">
                                                        {t.precio !== null ? bs(t.precio, institucion.moneda) : '—'}
                                                    </td>

                                                    <td className="px-5 py-2.5 text-right">
                                                        <div className="flex justify-end gap-2">
                                                            <Link
                                                                href={route('tipos-carnet.show', t.id)}
                                                                className={cn(buttonVariants({ variant: 'ver', size: 'sm' }))}
                                                                title="Historial de SIREB"
                                                            >
                                                                <History className="size-4" />
                                                            </Link>

                                                            {puede('catalogos.gestionar') && (
                                                                <Link
                                                                    href={route('tipos-carnet.edit', t.id)}
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

                                <Paginacion paginado={tipos} />
                            </>
                        )}
                    </CardContent>
                </Card>

            </div>
        </LayoutPanel>
    );
}

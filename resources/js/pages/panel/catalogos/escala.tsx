import { Head, Link, router, usePage } from '@inertiajs/react';
import { History, Pencil, Plus, Ruler, Search, TriangleAlert } from 'lucide-react';
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
import type { EscalaFila, HuecoEscala } from '@/types/catalogos';

/**
 *  La escala oficial de aprovechamiento
 */
export default function CatalogoEscala({
    escala,
    filtros,
    huecos,
    modalidades,
    opcionesPorPagina,
    sirebDisponible,
}: {
    escala: Paginado<EscalaFila>;
    filtros: { buscar: string | null; modalidad: string | null; por_pagina: number };
    huecos: HuecoEscala[];
    /** Las opciones salen del enum de PHP: escritas acá se desincronizan. */
    modalidades: OpcionEnum[];
    opcionesPorPagina: number[];
    /** false = SIREB no respondió: los precios no se pueden mostrar. */
    sirebDisponible: boolean;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const modalidad = (valor: string) => modalidades.find((o) => o.value === valor);

    function filtrar(valores: Record<string, string | number | null> = {}) {
        router.get(
            route('categorias-aprovechamiento.index'),
            { buscar, modalidad: filtros.modalidad, ...valores },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Escala de aprovechamiento"
            descripcion="Los tramos oficiales en kilos. El precio de cada uno lo pone Recaudaciones (SIREB)."
            acciones={
                puede('catalogos.gestionar') && (
                    <Link href={route('categorias-aprovechamiento.create')} className={cn(buttonVariants())}>
                        <Plus className="size-4" />
                        Nuevo tramo
                    </Link>
                )
            }
        >
            <Head title="Escala de aprovechamiento" />

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

                {huecos.length > 0 && <AvisoHuecos huecos={huecos} />}

                <Card className="min-w-0">
                        <CardHeader>
                            <CardTitle>Tramos</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-4 p-0">
                            {/*
                                LA BARRA DE ARRIBA DE LA TABLA, la misma del
                                padrón de beneficiarios: «Mostrar N» a la
                                izquierda y los filtros pegados al buscador.
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
                                        value={filtros.modalidad ?? ''}
                                        onChange={(e) => filtrar({ modalidad: e.target.value || null })}
                                        aria-label="Filtrar por modalidad"
                                    >
                                        <option value="">Toda modalidad</option>
                                        {modalidades.map((o) => (
                                            <option key={o.value} value={o.value}>
                                                {o.label}
                                            </option>
                                        ))}
                                    </Select>

                                    {/* El buscador es un <form> propio para que
                                        el Enter lo envíe: no busca al teclear. */}
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
                                            aria-label="Buscar por tramo de kilos"
                                        />
                                    </form>
                                </div>
                            </div>

                            {escala.data.length === 0 ? (
                                <EstadoVacio
                                    icono={Ruler}
                                    titulo="Sin tramos cargados"
                                    descripcion={
                                        filtros.buscar || filtros.modalidad
                                            ? 'Ninguno coincide con los filtros.'
                                            : 'Cargue la escala de la resolución: sin ella no se puede otorgar ningún cupo de pesca.'
                                    }
                                />
                            ) : (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                                <tr>
                                                    <th className="px-5 py-2.5 font-medium">N°</th>
                                                    <th className="px-5 py-2.5 font-medium">Rango</th>
                                                    <th className="px-5 py-2.5 font-medium">Régimen</th>
                                                    <th className="px-5 py-2.5 font-medium">SIREB</th>
                                                    <th className="px-5 py-2.5 text-right font-medium">Precio</th>
                                                    <th className="px-5 py-2.5 text-right font-medium">Otorgados</th>
                                                    <th className="px-5 py-2.5" />
                                                </tr>
                                            </thead>

                                            <tbody className="divide-y divide-border">
                                                {escala.data.map((t) => (
                                                    <tr key={t.id} className="hover:bg-secondary/50">
                                                        <td className="px-5 py-2.5 tabular-nums">
                                                            <span className="font-semibold">{t.nro_escala}</span>
                                                            {!t.estado && (
                                                                <Badge color="slate" className="ml-2">
                                                                    Derogado
                                                                </Badge>
                                                            )}
                                                        </td>

                                                        <td className="px-5 py-2.5">
                                                            {/*
                                                                El TEXTO OFICIAL primero y los números
                                                                debajo: no siempre dicen lo mismo —el
                                                                tramo más alto agrega «PAICHE»— y lo que
                                                                se imprime es el texto.
                                                            */}
                                                            <p>{t.descripcion_kg}</p>
                                                            <p className="text-xs tabular-nums text-muted-foreground">
                                                                {t.kilos_min} – {t.kilos_max} kg
                                                            </p>
                                                        </td>

                                                        {/*
                                                            EL RÉGIMEN VA EN SU PROPIA COLUMNA porque no
                                                            se lee en ningún número: el tramo del paiche
                                                            se ve igual que los otros seis, y su valor
                                                            no sale de la progresión por kilos.
                                                        */}
                                                        <td className="px-5 py-2.5">
                                                            {/* Etiqueta y color salen de `modalidades`, que manda el enum. */}
                                                            <Badge color={modalidad(t.modalidad)?.color}>
                                                                {modalidad(t.modalidad)?.label ?? t.modalidad}
                                                            </Badge>

                                                        </td>

                                                        <td className="px-5 py-2.5">
                                                            <CeldaSireb
                                                                servicioId={t.servicio_sireb}
                                                                tarifaId={t.tarifa_sireb}
                                                                servicio={t.sireb_servicio}
                                                                etiqueta={t.sireb_etiqueta}
                                                                estado={t.sireb_estado}
                                                            />
                                                        </td>

                                                        <td className="px-5 py-2.5 text-right tabular-nums">
                                                            {t.precio !== null ? bs(t.precio, institucion.moneda) : '—'}
                                                        </td>

                                                        <td className="px-5 py-2.5 text-right tabular-nums text-muted-foreground">
                                                            {t.aprovechamientos_count || '—'}
                                                        </td>

                                                        <td className="px-5 py-2.5 text-right">
                                                            <div className="flex justify-end gap-2">
                                                                <Link
                                                                    href={route('categorias-aprovechamiento.show', t.id)}
                                                                    className={cn(buttonVariants({ variant: 'ver', size: 'sm' }))}
                                                                    title="Historial de SIREB"
                                                                >
                                                                    <History className="size-4" />
                                                                </Link>

                                                                {puede('catalogos.gestionar') && (
                                                                    <Link
                                                                        href={route('categorias-aprovechamiento.edit', t.id)}
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

                                    <Paginacion paginado={escala} />
                                </>
                            )}
                        </CardContent>
                    </Card>

            </div>
        </LayoutPanel>
    );
}

/**
 * El aviso de rangos sin cubrir.
 */
function AvisoHuecos({ huecos }: { huecos: HuecoEscala[] }) {
    return (
        <Card className="border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
            <CardContent className="flex items-start gap-3 pt-5">
                <TriangleAlert className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />

                <div className="min-w-0 text-sm">
                    <p className="font-medium text-amber-900 dark:text-amber-200">
                        La escala tiene {huecos.length} hueco(s)
                    </p>

                    <p className="text-amber-800/80 dark:text-amber-200/80">
                        Un cupo que caiga en estos rangos no va a encontrar ninguna escala, y el
                        formulario de aprovechamiento no va a ofrecer nada — sin ningún error que lo
                        explique.
                    </p>

                    <ul className="mt-2 flex flex-wrap gap-1">
                        {huecos.map((h) => (
                            <li key={`${h.desde}-${h.hasta}`}>
                                <Badge color="amber">
                                    {h.desde} – {h.hasta} kg
                                </Badge>
                            </li>
                        ))}
                    </ul>
                </div>
            </CardContent>
        </Card>
    );
}

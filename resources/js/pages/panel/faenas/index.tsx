import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Plus, Receipt, Search, Ship } from 'lucide-react';
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
import { bs, fecha, fechaHora, hace } from '@/lib/utils';
import type { OpcionEnum, PageProps, Paginado } from '@/types';
import type { FaenaFila } from '@/types/faenas';

/**
 *  LISTADO DE PERMISOS DE FAENA
 */
export default function IndiceFaenas({
    faenas,
    filtros,
    estados,
    opcionesPorPagina,
}: {
    faenas: Paginado<FaenaFila>;
    filtros: { buscar: string | null; estado: string | null; por_pagina: number };
    estados: OpcionEnum[];
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null>) {
        router.get(
            route('faenas.index'),
            { buscar, estado: filtros.estado, ...valores },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Permisos de faena"
            descripcion="Una por salida. Cada faena descuenta kilos de la bolsa madre del pescador."
            acciones={
                puede('faenas.crear') && (
                    <Button onClick={() => router.visit(route('faenas.create'))}>
                        <Plus className="size-4" />
                        Emitir faena
                    </Button>
                )
            }
        >
            <Head title="Permisos de faena" />

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

                        {/* Todos filtran lo mismo —qué filas se ven— así que van
                            juntos: separados se leen como controles sueltos. */}
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
                                    aria-label="Buscar por pescador o número de faena"
                                />
                            </form>
                        </div>
                    </div>

                    {faenas.data.length === 0 ? (
                        <EstadoVacio
                            icono={Ship}
                            titulo="Sin faenas emitidas"
                            descripcion={
                                filtros.buscar || filtros.estado
                                    ? 'Ninguna coincide con los filtros.'
                                    : 'La faena cuelga del carnet de pescador: la persona necesita carnet vigente y cupo con saldo.'
                            }
                        />
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">N°</th>
                                            <th className="px-5 py-2.5 font-medium">Pescador</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Kilos</th>
                                            <th className="px-5 py-2.5 font-medium">Estado</th>
                                            <th className="px-5 py-2.5 font-medium">Salida</th>
                                            <th className="px-5 py-2.5 font-medium">Límite</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Arancel</th>
                                            <th className="px-5 py-2.5 font-medium">Registrado</th>
                                            <th className="px-5 py-2.5" />
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {faenas.data.map((f) => (
                                            <tr key={f.id} className="hover:bg-secondary/50">
                                                <td className="px-5 py-2.5">
                                                    <Link
                                                        href={route('faenas.show', f.id)}
                                                        className="font-mono font-medium tabular-nums text-primary hover:underline"
                                                    >
                                                        {String(f.numero_faena).padStart(4, '0')}
                                                    </Link>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <p className="font-medium">{f.beneficiario ?? '—'}</p>
                                                    <p className="font-mono text-xs text-muted-foreground">
                                                        {f.carnet_codigo ?? '—'}
                                                    </p>
                                                </td>

                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {/*
                                                        Tachado cuando NO consume cupo: es lo que
                                                        hace que la suma cuadre con el saldo del
                                                        aprovechamiento.
                                                    */}
                                                    <span
                                                        className={
                                                            f.consume_cupo
                                                                ? undefined
                                                                : 'text-muted-foreground line-through'
                                                        }
                                                    >
                                                        {f.kilos_extraidos} kg
                                                    </span>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <Badge color={f.estado_color}>{f.estado_etiqueta}</Badge>
                                                    {f.caducada && (
                                                        <Badge color="amber" className="ml-1">
                                                            sin cerrar
                                                        </Badge>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    {fecha(f.fecha_salida)}
                                                </td>

                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    {fecha(f.fecha_limite)}
                                                </td>

                                                {/* Lo cobrado, no solo la tarifa: es lo que dice
                                                    si al expediente le falta plata. */}
                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {bs(f.monto, institucion.moneda)}
                                                    {!f.pagado && (
                                                        <p className="text-xs text-amber-700 dark:text-amber-300">
                                                            faltan {bs(f.saldo_pendiente, institucion.moneda)}
                                                        </p>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    <p className="tabular-nums">{fechaHora(f.registrado_en)}</p>
                                                    <p className="text-xs">{hace(f.registrado_en)}</p>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <div className="flex justify-end gap-1">
                                                        <Link href={route('faenas.show', f.id)}>
                                                            <Button variant="ver" size="sm" title="Ver">
                                                                <Eye className="size-4" />
                                                            </Button>
                                                        </Link>

                                                        {/* El recibo existe desde el ENVÍO. */}
                                                        {puede('recibos.imprimir') && f.recibo_id !== null && (
                                                            <a
                                                                href={route('recibos.imprimir', f.recibo_id)}
                                                                target="_blank"
                                                                rel="noopener"
                                                                title={`Recibo ${f.recibo_numero}`}
                                                            >
                                                                <Button variant="ver" size="sm">
                                                                    <Receipt className="size-4" />
                                                                </Button>
                                                            </a>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <Paginacion paginado={faenas} />
                        </>
                    )}
                </CardContent>
            </Card>
        </LayoutPanel>
    );
}

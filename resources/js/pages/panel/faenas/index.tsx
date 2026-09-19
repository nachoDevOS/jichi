import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, Ship } from 'lucide-react';
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
import { fecha } from '@/lib/utils';
import type { OpcionEnum, Paginado } from '@/types';
import type { FaenaFila } from '@/types/faenas';

/**
 * ============================================================================
 *  LISTADO DE PERMISOS DE FAENA
 * ============================================================================
 *
 * ----------------------------------------------------------------------------
 *  LAS CADUCADAS SE MARCAN, Y ES LO MÁS ÚTIL DE ESTA PANTALLA
 * ----------------------------------------------------------------------------
 *
 * Una faena que se pasó de fecha y sigue ACTIVA es un papel que alguien se
 * llevó y del que nadie registró la vuelta. El comando diario la marca como
 * vencida, pero entre corrida y corrida queda acá a la vista — y el dato es
 * accionable: hay que ir a buscarla, no esperar.
 *
 * El aviso sale de `caducada`, que llega resuelto del servidor: la pantalla no
 * compara fechas, porque `new Date('2026-12-31')` en JavaScript se interpreta
 * como medianoche UTC y en UTC-4 devuelve el día anterior.
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
                            placeholder="Buscar por pescador o número de faena…"
                            className="min-w-48 flex-1"
                        />

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

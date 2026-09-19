import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Search, Truck } from 'lucide-react';
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
import { bs, fechaHora } from '@/lib/utils';
import type { OpcionEnum, PageProps, Paginado } from '@/types';
import type { GuiaFila } from '@/types/guias';

/**
 * ============================================================================
 *  LISTADO DE GUÍAS DE MOVIMIENTO
 * ============================================================================
 *
 * ----------------------------------------------------------------------------
 *  SE BUSCA POR ORIGEN Y DESTINO, NO SOLO POR PERSONA
 * ----------------------------------------------------------------------------
 *
 * Es la pregunta que trae a alguien a esta pantalla: «¿qué salió para Santa
 * Cruz esta semana?». Un buscador que solo mire el nombre del comercializador
 * obligaría a saber de antemano a quién buscar, que es justo lo que no se sabe.
 *
 * Las fechas se muestran con `fechaHora()` y no con `fecha()`: los cinco días
 * se cuentan desde el instante de emisión, así que la hora es el dato que
 * decide la vigencia.
 */
export default function IndiceGuias({
    guias,
    filtros,
    estados,
    opcionesPorPagina,
}: {
    guias: Paginado<GuiaFila>;
    filtros: {
        buscar: string | null;
        estado: string | null;
        piscicultura: boolean | null;
        por_pagina: number;
    };
    estados: OpcionEnum[];
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | boolean | null>) {
        router.get(
            route('guias.index'),
            {
                buscar,
                estado: filtros.estado,
                piscicultura: filtros.piscicultura,
                ...valores,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Guías de movimiento"
            descripcion="Una por traslado. Valen 5 días desde la hora de emisión."
            acciones={
                puede('guias.crear') && (
                    <Button onClick={() => router.visit(route('guias.create'))}>
                        <Plus className="size-4" />
                        Emitir guía
                    </Button>
                )
            }
        >
            <Head title="Guías de movimiento" />

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
                            placeholder="Buscar por código, persona, origen o destino…"
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
                            value={filtros.piscicultura === null ? '' : String(filtros.piscicultura)}
                            onChange={(e) =>
                                filtrar({ piscicultura: e.target.value === '' ? null : e.target.value })
                            }
                            className="w-auto"
                        >
                            <option value="">Todo origen</option>
                            <option value="1">Piscicultura</option>
                            <option value="0">De río</option>
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

                    {guias.data.length === 0 ? (
                        <EstadoVacio
                            icono={Truck}
                            titulo="Sin guías emitidas"
                            descripcion={
                                filtros.buscar || filtros.estado || filtros.piscicultura !== null
                                    ? 'Ninguna coincide con los filtros.'
                                    : 'La guía cuelga del carnet de comercializador: la persona necesita credencial vigente de esa actividad.'
                            }
                        />
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">Código</th>
                                            <th className="px-5 py-2.5 font-medium">Comercializador</th>
                                            <th className="px-5 py-2.5 font-medium">Ruta</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Carga</th>
                                            <th className="px-5 py-2.5 font-medium">Estado</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Cobro</th>
                                            <th className="px-5 py-2.5 font-medium">Vence</th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {guias.data.map((g) => (
                                            <tr key={g.id} className="hover:bg-secondary/50">
                                                <td className="px-5 py-2.5">
                                                    <Link
                                                        href={route('guias.show', g.id)}
                                                        className="font-mono font-medium text-primary hover:underline"
                                                    >
                                                        {g.codigo_guia}
                                                    </Link>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <p className="font-medium">{g.comercializador ?? '—'}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {g.asociacion ?? '—'}
                                                    </p>
                                                </td>

                                                <td className="px-5 py-2.5">{g.ruta}</td>

                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {g.peso_total_kg} kg
                                                    {/*
                                                        La marca de piscicultura va acá y no en una
                                                        columna propia: es un atributo de la CARGA, y
                                                        además explica por qué esa fila cobró la
                                                        mitad.
                                                    */}
                                                    {g.es_piscicultura && (
                                                        <Badge color="sky" className="ml-2">
                                                            criadero
                                                        </Badge>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <Badge color={g.estado_color}>{g.estado_etiqueta}</Badge>
                                                    {g.caducada && (
                                                        <Badge color="amber" className="ml-1">
                                                            sin cerrar
                                                        </Badge>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {g.pagado ? (
                                                        <span className="text-emerald-700 dark:text-emerald-400">
                                                            Pagado
                                                        </span>
                                                    ) : (
                                                        <span className="text-amber-700 dark:text-amber-400">
                                                            debe {bs(g.saldo_pendiente, institucion.moneda)}
                                                        </span>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    {fechaHora(g.fecha_vencimiento)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <Paginacion paginado={guias} />
                        </>
                    )}
                </CardContent>
            </Card>
        </LayoutPanel>
    );
}

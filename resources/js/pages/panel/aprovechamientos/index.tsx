import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Search, TriangleAlert, Waves } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { BarraSaldo } from '@/components/panel/aprovechamientos/barra-saldo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { OpcionEnum, PageProps, Paginado } from '@/types';
import type { CupoFila } from '@/types/aprovechamientos';

/**
 * ============================================================================
 *  LISTADO DE CUPOS DE PESCA
 * ============================================================================
 *
 * ----------------------------------------------------------------------------
 *  LA COLUMNA QUE IMPORTA ES EL SALDO, NO EL VOLUMEN OTORGADO
 * ----------------------------------------------------------------------------
 *
 * «Tiene 500 kg» no dice si esa persona puede salir a pescar mañana. «Le quedan
 * 20» sí. Por eso la barra de consumo va en la fila y no escondida en la ficha:
 * quien mira este listado está buscando a quién le queda poco.
 */
export default function IndiceCupos({
    cupos,
    filtros,
    estados,
    opcionesPorPagina,
    modoEstricto,
}: {
    cupos: Paginado<CupoFila>;
    filtros: { buscar: string | null; estado: string | null; por_pagina: number };
    estados: OpcionEnum[];
    opcionesPorPagina: number[];
    /**
     * Lo que dice APROVECHAMIENTO_ESTRICTO en el servidor.
     *
     * Va en la pantalla porque cambia qué significa un saldo en cero: con la
     * validación encendida es un bloqueo, y con ella apagada es un dato. Sin
     * este aviso, el listado se leería mal justo en el caso raro.
     */
    modoEstricto: boolean;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null>) {
        router.get(route('aprovechamientos.index'), { buscar, estado: filtros.estado, ...valores }, {
            preserveState: true,
            preserveScroll: true,
            // replace evita llenar el historial con una entrada por búsqueda.
            replace: true,
        });
    }

    return (
        <LayoutPanel
            titulo="Autorizacion de Pesca Para aprovechamiento Pesquero"
            descripcion="La bolsa madre: el volumen anual que se le autoriza a cada pescador."
            acciones={
                puede('aprovechamientos.crear') && (
                    <Button onClick={() => router.visit(route('aprovechamientos.create'))}>
                        <Plus className="size-4" />
                        Otorgar cupo
                    </Button>
                )
            }
        >
            <Head title="Aprovechamientos de Pesca" />

            {!modoEstricto && <AvisoModoFlexible />}

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
                            placeholder="Buscar por cédula o nombre…"
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

                    {cupos.data.length === 0 ? (
                        <EstadoVacio
                            icono={Waves}
                            titulo="Sin cupos otorgados"
                            descripcion={
                                filtros.buscar || filtros.estado
                                    ? 'Ninguno coincide con los filtros.'
                                    : 'El cupo es el paso 2 del flujo: va antes del carnet, porque el plástico necesita saber qué volumen imprimir.'
                            }
                        />
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">Pescador</th>
                                            <th className="px-5 py-2.5 font-medium">Escala</th>
                                            <th className="px-5 py-2.5 font-medium">Régimen</th>
                                            <th className="px-5 py-2.5 font-medium">Saldo</th>
                                            <th className="px-5 py-2.5 font-medium">Estado</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Cobro</th>
                                            <th className="px-5 py-2.5 font-medium">Vence</th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {cupos.data.map((c) => (
                                            <tr key={c.id} className="hover:bg-secondary/50">
                                                <td className="px-5 py-2.5">
                                                    <Link
                                                        href={route('aprovechamientos.show', c.id)}
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {c.beneficiario ?? '—'}
                                                    </Link>
                                                    <p className="font-mono text-xs text-muted-foreground">
                                                        {c.documento ?? '—'}
                                                    </p>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <span className="font-semibold tabular-nums">
                                                        {c.escala ?? '—'}
                                                    </span>
                                                    <p className="text-xs text-muted-foreground">
                                                        {c.descripcion ?? '—'}
                                                    </p>
                                                </td>

                                                {/*
                                                    El régimen decide si este cupo se va a poder
                                                    ampliar cuando se agote, y no se deduce de la
                                                    escala: hay que verlo antes de que el pescador
                                                    esté en la ventanilla preguntando.
                                                */}
                                                <td className="px-5 py-2.5">
                                                    <Badge color={c.modalidad_color}>
                                                        {c.modalidad_etiqueta}
                                                    </Badge>
                                                </td>

                                                <td className="min-w-40 px-5 py-2.5">
                                                    <BarraSaldo cupo={c} />
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>
                                                </td>

                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {c.pagado ? (
                                                        <span className="text-emerald-700 dark:text-emerald-400">
                                                            Pagado
                                                        </span>
                                                    ) : (
                                                        <span className="text-amber-700 dark:text-amber-400">
                                                            debe {bs(c.saldo_pendiente, institucion.moneda)}
                                                        </span>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    {fecha(c.fecha_vencimiento)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <Paginacion paginado={cupos} />
                        </>
                    )}
                </CardContent>
            </Card>
        </LayoutPanel>
    );
}

/**
 * EL AVISO DE MODO FLEXIBLE.
 *
 * Solo aparece con APROVECHAMIENTO_ESTRICTO=false, y tiene que aparecer: con la
 * validación apagada el sistema deja emitir faenas por encima del volumen
 * otorgado, así que un saldo en cero deja de ser un freno. Quien mira este
 * listado buscando a quién le queda poco necesita saber que nadie va a ser
 * frenado por eso.
 */
function AvisoModoFlexible() {
    return (
        <Card className="mb-6 border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
            <CardContent className="flex items-start gap-3 pt-5">
                <TriangleAlert className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />

                <div className="min-w-0 text-sm">
                    <p className="font-medium text-amber-900 dark:text-amber-200">
                        Control de cupo desactivado
                    </p>

                    <p className="text-amber-800/80 dark:text-amber-200/80">
                        El sistema está en modo flexible (<code>APROVECHAMIENTO_ESTRICTO=false</code>):
                        las faenas se emiten aunque el cupo esté agotado. Los saldos se siguen
                        calculando y los excesos quedan a la vista, pero nada los frena.
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}

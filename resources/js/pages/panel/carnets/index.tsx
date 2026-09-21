import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { BadgeCheck, Eye, Pencil, Plus, Printer, Receipt, Search, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha, fechaHora, hace } from '@/lib/utils';
import type { OpcionEnum, PageProps, Paginado } from '@/types';
import type { CarnetFila } from '@/types/carnets';

/**
 *  LISTADO DE CARNETS
 */
export default function IndiceCarnets({
    carnets,
    filtros,
    estados,
    actores,
    opcionesPorPagina,
}: {
    carnets: Paginado<CarnetFila>;
    filtros: { buscar: string | null; estado: string | null; actor: string | null; por_pagina: number };
    estados: OpcionEnum[];
    actores: OpcionEnum[];
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    /*
     * Se guarda la FILA entera y no el id: la ventana de confirmación dice de
     * quién es el carnet y cuál es su código, y con el id habría que volver a
     * buscarla en el arreglo cada vez que se dibuja.
     */
    const [eliminando, setEliminando] = useState<CarnetFila | null>(null);
    const borrado = useForm({ motivo: '' });

    function filtrar(valores: Record<string, string | number | null>) {
        router.get(
            route('carnets.index'),
            { buscar, estado: filtros.estado, actor: filtros.actor, ...valores },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Carnets"
            descripcion="La credencial anual. De ella cuelgan las faenas y las guías."
            acciones={
                puede('carnets.crear') && (
                    <Button onClick={() => router.visit(route('carnets.create'))}>
                        <Plus className="size-4" />
                        Registra carnet
                    </Button>
                )
            }
        >
            <Head title="Carnets" />

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
                            placeholder="Buscar por cédula, nombre o código…"
                            className="min-w-48 flex-1"
                        />

                        <Select
                            value={filtros.actor ?? ''}
                            onChange={(e) => filtrar({ actor: e.target.value || null })}
                            className="w-auto"
                        >
                            <option value="">Toda actividad</option>
                            {actores.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </Select>

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

                    {carnets.data.length === 0 ? (
                        <EstadoVacio
                            icono={BadgeCheck}
                            titulo="Sin carnets emitidos"
                            descripcion={
                                filtros.buscar || filtros.estado || filtros.actor
                                    ? 'Ninguno coincide con los filtros.'
                                    : 'El carnet es el paso 3 del flujo: la persona ya tiene que estar registrada, y si es pescador, con su cupo otorgado.'
                            }
                        />
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">Titular</th>
                                            <th className="px-5 py-2.5 font-medium">Actividad</th>
                                            <th className="px-5 py-2.5 font-medium">Asociación</th>
                                            {/* <th className="px-5 py-2.5 text-right font-medium">Cobro</th> */}
                                            <th className="px-5 py-2.5 font-medium">Solicitado</th>
                                            {/* Vacío mientras no lo firmen. */}
                                            <th className="px-5 py-2.5 font-medium">Emitido</th>
                                            <th className="px-5 py-2.5 font-medium">Vence</th>

                                            {/* CUÁNDO SE CARGÓ y en qué ESTADO quedó, juntos y
                                                pegados a los botones: el estado decide cuáles
                                                aparecen, y leerlo al lado explica por qué falta
                                                alguno. */}
                                            <th className="px-5 py-2.5 font-medium">Registrado</th>
                                            <th className="px-5 py-2.5 font-medium">Estado</th>

                                            {/* Sin rótulo: los iconos se explican solos y un
                                                encabezado «Acciones» solo gasta ancho. */}
                                            <th className="px-5 py-2.5" />
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {carnets.data.map((c) => (
                                            <tr key={c.id} className="hover:bg-secondary/50">
                                                {/* Foto, nombre y cédula en la misma celda,
                                                    igual que en el listado de cupos: el hueco de
                                                    la silueta se dibuja también sin foto, así las
                                                    filas no cambian de alto. */}
                                                <td className="px-5 py-2.5">
                                                    <div className="flex items-center gap-3">
                                                        <Retrato
                                                            url={c.foto_url}
                                                            nombre={c.beneficiario ?? 'Sin nombre'}
                                                        />

                                                        <div className="min-w-0">
                                                            <Link
                                                                href={route('carnets.show', c.id)}
                                                                className="font-medium text-primary hover:underline"
                                                            >
                                                                {c.beneficiario ?? '—'}
                                                            </Link>

                                                            <p className="tabular-nums text-xs text-muted-foreground">
                                                                {c.documento ?? '—'}
                                                            </p>

                                                            {/* EL CUPO EN LUGAR DEL CÓDIGO.
                                                                Dieciséis caracteres al azar no
                                                                le dicen nada a nadie en un
                                                                listado —el código sirve para
                                                                verificar, y ahí está la ficha—;
                                                                los kilos, sí. En un
                                                                comercializador no va nada: no
                                                                lleva volumen. */}
                                                            {c.cupo_kg !== null && (
                                                                <p className="tabular-nums text-xs text-muted-foreground">
                                                                    {c.cupo_kg} kg autorizados
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <Badge color={c.tipo_actor_color}>
                                                        {c.tipo_actor_etiqueta}
                                                    </Badge>
                                                </td>

                                                {/* El nombre completo, con la sigla debajo
                                                    cuando la tiene: es lo que se dicta y lo que
                                                    va impreso en el carnet. */}
                                                <td className="px-5 py-2.5">
                                                    <p>{c.asociacion ?? '—'}</p>

                                                    {c.asociacion_sigla && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {c.asociacion_sigla}
                                                        </p>
                                                    )}
                                                </td>


                                                {/* <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {c.pagado ? (
                                                        <span className="text-emerald-700 dark:text-emerald-400">
                                                            Pagado
                                                        </span>
                                                    ) : (
                                                        <span className="text-amber-700 dark:text-amber-400">
                                                            debe {bs(c.saldo_pendiente, institucion.moneda)}
                                                        </span>
                                                    )}
                                                </td> */}

                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    {fecha(c.fecha_solicitud)}
                                                </td>

                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    {c.fecha_emision ? fecha(c.fecha_emision) : '—'}
                                                </td>

                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    {fecha(c.fecha_vencimiento)}
                                                </td>

                                                <td className="px-5 py-2.5 text-xs text-muted-foreground">
                                                    {fechaHora(c.registrado_en)}
                                                    <span className="block">{hace(c.registrado_en)}</span>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>
                                                </td>

                                                {/*
                                                    LOS TRES BOTONES, cada uno con su color: el ojo
                                                    en celeste CONSULTA, el lápiz en ámbar MODIFICA
                                                    y el tacho en rojo DESTRUYE. Editar y eliminar
                                                    solo aparecen sobre el borrador: las banderas
                                                    llegan resueltas del servidor.
                                                */}
                                                <td className="px-5 py-2.5">
                                                    <div className="flex justify-end gap-1">
                                                        {/* EL RECIBO, desde que el trámite se
                                                            presentó: existe a partir del envío,
                                                            así que sale en revisión y sigue
                                                            después. */}
                                                        {puede('recibos.imprimir') &&
                                                            c.recibo_id !== null && (
                                                                <a
                                                                    href={route(
                                                                        'recibos.imprimir',
                                                                        c.recibo_id,
                                                                    )}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    title={`Recibo ${c.recibo_numero ?? ''}`}
                                                                    aria-label={`Imprimir el recibo de ${c.beneficiario ?? 'la persona'}`}
                                                                    className={cn(
                                                                        buttonVariants({
                                                                            variant: 'outline',
                                                                            size: 'sm',
                                                                        }),
                                                                    )}
                                                                >
                                                                    <Receipt className="size-4" />
                                                                </a>
                                                            )}

                                                        {/* EL CARNET EN PDF, desde que está
                                                            firmado. Un revocado no se imprime
                                                            —el controlador lo rechaza igual— así
                                                            que tampoco se ofrece. */}
                                                        {puede('carnets.imprimir') &&
                                                            c.ya_fue_aprobado &&
                                                            c.estado !== 'revocado' && (
                                                                <a
                                                                    href={route(
                                                                        'carnets.imprimir',
                                                                        c.id,
                                                                    )}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    title="Imprimir el carnet"
                                                                    aria-label={`Imprimir el carnet de ${c.beneficiario ?? 'la persona'}`}
                                                                    className={cn(
                                                                        buttonVariants({
                                                                            variant: 'dorado',
                                                                            size: 'sm',
                                                                        }),
                                                                    )}
                                                                >
                                                                    <Printer className="size-4" />
                                                                </a>
                                                            )}

                                                        <Button
                                                            variant="ver"
                                                            size="sm"
                                                            title="Ver"
                                                            aria-label={`Ver el carnet de ${c.beneficiario ?? 'la persona'}`}
                                                            onClick={() =>
                                                                router.visit(route('carnets.show', c.id))
                                                            }
                                                        >
                                                            <Eye className="size-4" />
                                                        </Button>

                                                        {puede('carnets.editar') && c.puede_editarse && (
                                                            <Button
                                                                variant="editar"
                                                                size="sm"
                                                                title="Editar"
                                                                aria-label={`Editar el carnet de ${c.beneficiario ?? 'la persona'}`}
                                                                onClick={() =>
                                                                    router.visit(route('carnets.edit', c.id))
                                                                }
                                                            >
                                                                <Pencil className="size-4" />
                                                            </Button>
                                                        )}

                                                        {puede('carnets.eliminar') &&
                                                            c.puede_eliminarse && (
                                                                <Button
                                                                    variant="eliminar"
                                                                    size="sm"
                                                                    title="Eliminar"
                                                                    aria-label={`Eliminar el carnet de ${c.beneficiario ?? 'la persona'}`}
                                                                    onClick={() => setEliminando(c)}
                                                                >
                                                                    <Trash2 className="size-4" />
                                                                </Button>
                                                            )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <Paginacion paginado={carnets} />
                        </>
                    )}
                </CardContent>
            </Card>

            {/*
                ELIMINAR PIDE MOTIVO Y CASILLA, igual que en la ficha: la fila
                desaparece de los listados y lo único que queda es la línea de
                auditoría.
            */}
            <ConfirmarConMotivo
                abierto={eliminando !== null}
                titulo="Eliminar este carnet"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El carnet <strong>{eliminando?.codigo}</strong> de{' '}
                            <strong>{eliminando?.beneficiario ?? 'el titular'}</strong> desaparece de
                            los listados. Se elimina solo porque está PENDIENTE y sin cobrar.
                        </p>
                        <p>
                            <strong>El código no se libera:</strong> ese número pudo alcanzar a
                            imprimirse.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la eliminación"
                ayuda="Queda en la auditoría con su nombre. Es lo único que va a explicar el hueco."
                placeholder="Cargado por error: la persona ya tenía carnet de esta gestión."
                confirmacion="Entiendo que el carnet desaparece de los listados y que el código queda quemado."
                textoConfirmar="Eliminar carnet"
                valor={borrado.data.motivo}
                onCambiar={(v) => borrado.setData('motivo', v)}
                error={borrado.errors.motivo}
                procesando={borrado.processing}
                onCancelar={() => {
                    setEliminando(null);
                    borrado.reset();
                }}
                onConfirmar={() =>
                    eliminando &&
                    borrado.delete(route('carnets.destroy', eliminando.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setEliminando(null);
                            borrado.reset();
                        },
                    })
                }
            />
        </LayoutPanel>
    );
}

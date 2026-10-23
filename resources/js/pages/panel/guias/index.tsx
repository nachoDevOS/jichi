import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Eye, Fish, Pencil, Plus, Printer, Receipt, Search, Trash2, Truck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { abrirDirecto } from '@/components/panel/comunes/ficha';
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
import { bs, cn, fechaHora, hace } from '@/lib/utils';
import type { OpcionEnum, PageProps, Paginado } from '@/types';
import type { GuiaFila } from '@/types/guias';

/**
 *  Listado de guías de movimiento
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
    const [eliminando, setEliminando] = useState<GuiaFila | null>(null);
    const borrado = useForm({ motivo: '' });

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
                    {/* La barra de arriba de la tabla, la misma del padrón de beneficiarios. */}
                    <div className="grid grid-cols-1 gap-3 border-b border-border p-4 sm:grid-cols-12 sm:items-end">
                        <label className="flex items-center gap-2 text-sm text-muted-foreground sm:col-span-3">
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

                        <div className="flex flex-wrap items-center justify-end gap-2 sm:col-span-9">
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

                            <Select
                                className="w-auto min-w-36"
                                value={filtros.piscicultura === null ? '' : String(filtros.piscicultura)}
                                onChange={(e) =>
                                    filtrar({ piscicultura: e.target.value === '' ? null : e.target.value })
                                }
                                aria-label="Filtrar por origen"
                            >
                                <option value="">Todo origen</option>
                                <option value="1">Piscicultura</option>
                                <option value="0">De río</option>
                            </Select>

                            {/* Un <form> propio para que el Enter lo envíe: no busca mientras se teclea. */}
                            <form
                                className="relative min-w-48 flex-1 sm:max-w-sm"
                                onSubmit={(e: FormEvent) => {
                                    e.preventDefault();
                                    filtrar({});
                                }}
                            >
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    className="pl-9"
                                    placeholder="Buscar…"
                                    value={buscar}
                                    onChange={(e) => setBuscar(e.target.value)}
                                    aria-label="Buscar por número, persona, origen o destino"
                                />
                            </form>
                        </div>
                    </div>

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
                                    <thead className="border-y border-border text-left text-xs tracking-wide text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">N°</th>
                                            <th className="px-5 py-2.5 font-medium">Comercializador</th>
                                            <th className="px-5 py-2.5 font-medium">Ruta</th>
                                            <th className="px-5 py-2.5 font-medium">Pescado de</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Carga</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Arancel</th>
                                            <th className="px-5 py-2.5 font-medium">Vence</th>
                                            {/* Registrado y Estado pegados a los botones, como en faenas y carnets. */}
                                            <th className="px-5 py-2.5 font-medium">Registrado</th>
                                            <th className="px-5 py-2.5 font-medium">Estado</th>
                                            <th className="px-5 py-2.5" />
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {guias.data.map((g) => (
                                            <tr key={g.id} className="hover:bg-secondary/50">
                                                <td className="px-5 py-2.5">
                                                    <Link
                                                        href={route('guias.show', g.id)}
                                                        className="font-mono font-medium text-primary tabular-nums hover:underline"
                                                    >
                                                        {g.numero_legible}
                                                    </Link>
                                                </td>

                                                {/* Foto, nombre, cédula y carnet: el mismo bloque que en faenas y carnets. */}
                                                <td className="px-5 py-2.5">
                                                    <div className="flex items-center gap-3">
                                                        <Retrato
                                                            url={g.foto_url}
                                                            nombre={g.comercializador ?? 'Sin nombre'}
                                                        />
                                                        <div className="min-w-0">
                                                            <p className="font-medium">{g.comercializador ?? '—'}</p>
                                                            <p className="text-xs text-muted-foreground tabular-nums">
                                                                {g.documento ?? '—'}
                                                                {g.asociacion && ` · ${g.asociacion}`}
                                                            </p>
                                                            <p className="font-mono text-xs text-muted-foreground">
                                                                Carnet N° {g.carnet_registro ?? '—'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <span className="inline-flex items-center gap-1.5 whitespace-nowrap">
                                                        {g.origen ?? '—'}
                                                        <ArrowRight className="size-3.5 text-muted-foreground" />
                                                        {g.destino ?? '—'}
                                                    </span>
                                                </td>

                                                {/* Su autorización es la que descontó los kilos; sin pescador, es de criadero. */}
                                                <td className="px-5 py-2.5">
                                                    {g.pescador ? (
                                                        <span className="inline-flex items-center gap-1.5">
                                                            <Fish className="size-3.5 shrink-0 text-primary" />
                                                            {g.pescador}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">—</span>
                                                    )}
                                                </td>

                                                {/* Piscicultura va junto a la carga: explica por qué cobró la mitad. */}
                                                <td className="px-5 py-2.5 text-right whitespace-nowrap tabular-nums">
                                                    <span className="font-medium">{g.peso_total_kg} kg</span>
                                                    {g.es_piscicultura && (
                                                        <Badge color="sky" className="ml-2">
                                                            criadero
                                                        </Badge>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5 text-right text-base tabular-nums">
                                                    {bs(g.monto, institucion.moneda)}
                                                    {g.puede_verificar_pago ? (
                                                        <p className="text-xs text-amber-700 dark:text-amber-300">
                                                            por pagar
                                                        </p>
                                                    ) : (
                                                        <p className="text-xs text-emerald-700 dark:text-emerald-400">
                                                            Pagado
                                                        </p>
                                                    )}
                                                </td>

                                                {/* En NULL mientras es borrador: el vencimiento lo escribe la aprobación. */}
                                                <td className="px-5 py-2.5 whitespace-nowrap">
                                                    {g.fecha_vencimiento ? fechaHora(g.fecha_vencimiento) : '—'}
                                                    {g.vigente && (
                                                        <p className="text-xs text-emerald-700 dark:text-emerald-400">
                                                            en camino
                                                        </p>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5 text-xs text-muted-foreground">
                                                    {fechaHora(g.registrado_en)}
                                                    <span className="block">{hace(g.registrado_en)}</span>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <Badge color={g.estado_color}>{g.estado_etiqueta}</Badge>
                                                    {g.caducada && (
                                                        <Badge color="amber" className="ml-1">
                                                            vencida
                                                        </Badge>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <div className="flex justify-end gap-1">
                                                        {puede('recibos.imprimir') && g.recibo_id !== null && (
                                                            <a
                                                                href={route('recibos.imprimir', g.recibo_id)}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                onClick={abrirDirecto(g.recibo_enlace)}
                                                                title={`Imprimir el recibo ${g.recibo_numero}`}
                                                                aria-label={`Imprimir el recibo de la guía ${g.numero_legible}`}
                                                                className={cn(
                                                                    buttonVariants({ variant: 'outline', size: 'sm' }),
                                                                )}
                                                            >
                                                                <Receipt className="size-4" />
                                                            </a>
                                                        )}

                                                        {/* La guía en papel sale recién aprobada. Abre pestaña: vuelve un PDF. */}
                                                        {puede('guias.imprimir') && g.ya_fue_aprobada && (
                                                            <a
                                                                href={route('guias.imprimir', g.id)}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                onClick={abrirDirecto(g.pdf_enlace)}
                                                                title="Imprimir la guía"
                                                                aria-label={`Imprimir la guía ${g.numero_legible}`}
                                                                className={cn(
                                                                    buttonVariants({ variant: 'outline', size: 'sm' }),
                                                                )}
                                                            >
                                                                <Printer className="size-4" />
                                                            </a>
                                                        )}

                                                        {/* Corregir y eliminar solo sobre el borrador: las banderas llegan resueltas. */}
                                                        {puede('guias.editar') && g.puede_editarse && (
                                                            <Button
                                                                variant="editar"
                                                                size="sm"
                                                                onClick={() => router.visit(route('guias.edit', g.id))}
                                                                aria-label={`Corregir la guía ${g.numero_legible}`}
                                                                title="Editar"
                                                            >
                                                                <Pencil className="size-4" />
                                                            </Button>
                                                        )}

                                                        {puede('guias.eliminar') && g.puede_eliminarse && (
                                                            <Button
                                                                variant="eliminar"
                                                                size="sm"
                                                                onClick={() => setEliminando(g)}
                                                                aria-label={`Eliminar la guía ${g.numero_legible}`}
                                                                title="Eliminar"
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        )}

                                                        {/* Ver va último: es la más usada y queda pegada al borde. */}
                                                        <Link href={route('guias.show', g.id)}>
                                                            <Button variant="ver" size="sm" title="Ver">
                                                                <Eye className="size-4" />
                                                            </Button>
                                                        </Link>
                                                    </div>
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

            {/* Motivo obligatorio: el número del talonario queda quemado y alguien va a tener que explicarlo. */}
            <ConfirmarConMotivo
                abierto={eliminando !== null}
                titulo="Eliminar esta guía de transporte"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            Se da de baja la guía <strong>N° {eliminando?.numero_legible ?? '—'}</strong> de{' '}
                            <strong>{eliminando?.comercializador ?? 'el comercializador'}</strong>:{' '}
                            {eliminando?.peso_total_kg ?? 0} kg.
                        </p>
                        <p>
                            Solo se puede porque está <strong>pendiente</strong>. Sus kilos vuelven a la autorización
                            del pescador, pero el número del talonario <strong>no se reutiliza</strong>.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la eliminación"
                ayuda="Queda en la auditoría con su nombre, y es lo que va a explicar el hueco en la serie."
                placeholder="Cargada por error: el traslado corresponde a otro comercializador."
                textoConfirmar="Eliminar guía"
                confirmacion="Entiendo que el número del talonario queda quemado y que esto no se deshace desde el panel."
                valor={borrado.data.motivo}
                onCambiar={(v) => borrado.setData('motivo', v)}
                error={borrado.errors.motivo}
                procesando={borrado.processing}
                onCancelar={() => {
                    setEliminando(null);
                    borrado.reset();
                }}
                onConfirmar={() => {
                    if (eliminando === null) return;

                    borrado.delete(route('guias.destroy', eliminando.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setEliminando(null);
                            borrado.reset();
                        },
                    });
                }}
            />
        </LayoutPanel>
    );
}

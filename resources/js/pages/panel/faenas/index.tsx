import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus, Printer, Receipt, Search, Ship, Trash2 } from 'lucide-react';
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
    const [eliminando, setEliminando] = useState<FaenaFila | null>(null);
    const borrado = useForm({ motivo: '' });

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
            descripcion="Una por salida. Descuenta kilos de la bolsa madre del pescador recién cuando se aprueba."
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
                                            <th className="px-5 py-2.5 font-medium">Beneficiario</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Kilos</th>
                                            <th className="px-5 py-2.5 font-medium">Estado</th>
                                            <th className="px-5 py-2.5 font-medium">Salida</th>
                                            <th className="px-5 py-2.5 font-medium">Desembarque</th>
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
                                                        {f.numero_legible}
                                                    </Link>
                                                </td>

                                                {/* --- Foto, nombre, cédula y carnet.
                                                    Mismo bloque que en carnets y aprovechamientos:
                                                    el hueco de la silueta se dibuja igual sin foto,
                                                    así las filas no cambian de alto. */}
                                                <td className="px-5 py-2.5">
                                                    <div className="flex items-center gap-3">
                                                        <Retrato
                                                            url={f.foto_url}
                                                            nombre={f.beneficiario ?? 'Sin nombre'}
                                                        />

                                                        <div className="min-w-0">
                                                            <p className="font-medium">
                                                                {f.beneficiario ?? '—'}
                                                            </p>
                                                            <p className="tabular-nums text-xs text-muted-foreground">
                                                                {f.documento ?? '—'}
                                                            </p>
                                                            {/* EL CARNET, por su número de LIBRO y
                                                                no por su código: dieciséis
                                                                caracteres al azar no le dicen nada
                                                                a nadie en un listado —el código
                                                                sirve para verificar, y está en la
                                                                ficha—. Mismo criterio que el
                                                                listado de carnets. */}
                                                            <p className="font-mono text-xs text-muted-foreground">
                                                                Carnet N° {f.carnet_registro ?? '—'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {/*
                                                        Tachado cuando NO consume cupo: todavía sin
                                                        firmar, o vencida. Es lo que hace que la
                                                        suma cuadre con el saldo del
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
                                                    {fecha(f.fecha_desembarque)}
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
                                                        {/* El recibo existe desde el ENVÍO.
                                                            Mismo estilo que en el listado de
                                                            aprovechamientos: los dos papeles se
                                                            imprimen desde la tabla, sin pasar por
                                                            la ficha. */}
                                                        {puede('recibos.imprimir') && f.recibo_id !== null && (
                                                            <a
                                                                href={route('recibos.imprimir', f.recibo_id)}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                title={`Imprimir el recibo ${f.recibo_numero}`}
                                                                aria-label={`Imprimir el recibo de la faena ${f.numero_legible}`}
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

                                                        {/* EL PERMISO EN PAPEL sale recién con la
                                                            faena aprobada: hasta la firma no hay
                                                            nada que autorizar. Abre pestaña porque
                                                            lo que vuelve es un PDF. */}
                                                        {puede('faenas.imprimir') && f.ya_fue_aprobada && (
                                                            <a
                                                                href={route('faenas.imprimir', f.id)}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                title="Imprimir el permiso de faena"
                                                                aria-label={`Imprimir el permiso de la faena ${f.numero_legible}`}
                                                                className={cn(
                                                                    buttonVariants({
                                                                        variant: 'outline',
                                                                        size: 'sm',
                                                                    }),
                                                                )}
                                                            >
                                                                <Printer className="size-4" />
                                                            </a>
                                                        )}

                                                        {/* CORREGIR Y ELIMINAR SOLO SOBRE EL
                                                            BORRADOR: las dos banderas llegan
                                                            resueltas del servidor y miran el estado
                                                            Y que no haya entrado un peso. */}
                                                        {puede('faenas.editar') && f.puede_editarse && (
                                                            <Button
                                                                variant="editar"
                                                                size="sm"
                                                                onClick={() =>
                                                                    router.visit(route('faenas.edit', f.id))
                                                                }
                                                                aria-label={`Corregir la faena ${f.numero_legible}`}
                                                                title="Editar"
                                                            >
                                                                <Pencil className="size-4" />
                                                            </Button>
                                                        )}

                                                        {puede('faenas.eliminar') && f.puede_eliminarse && (
                                                            <Button
                                                                variant="eliminar"
                                                                size="sm"
                                                                onClick={() => setEliminando(f)}
                                                                aria-label={`Eliminar la faena ${f.numero_legible}`}
                                                                title="Eliminar"
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        )}

                                                        {/* VER VA ÚLTIMO: es la acción más usada y
                                                            queda pegada al borde de la fila, donde
                                                            cae el dedo sin tener que apuntar. */}
                                                        <Link href={route('faenas.show', f.id)}>
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

                            <Paginacion paginado={faenas} />
                        </>
                    )}
                </CardContent>
            </Card>

            {/* MOTIVO OBLIGATORIO Y CASILLA DE CONSENTIMIENTO, igual que en el
                cupo: el número del talonario queda quemado y la serie con un
                hueco, así que alguien va a tener que explicarlo. */}
            <ConfirmarConMotivo
                abierto={eliminando !== null}
                titulo="Eliminar este permiso de faena"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            Se da de baja la faena{' '}
                            <strong>N° {eliminando?.numero_legible ?? '—'}</strong> de{' '}
                            <strong>{eliminando?.beneficiario ?? 'el pescador'}</strong>:{' '}
                            {eliminando?.kilos_extraidos ?? 0} kg.
                        </p>
                        <p>
                            Solo se puede porque está <strong>pendiente</strong> y sin ningún
                            depósito cargado. Sus kilos vuelven a la bolsa madre, pero el número del
                            talonario <strong>no se reutiliza</strong>.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la eliminación"
                ayuda="Queda en la auditoría con su nombre, y es lo que va a explicar el hueco en la serie dentro de seis meses."
                placeholder="Cargada por error: la salida corresponde a otro pescador."
                textoConfirmar="Eliminar faena"
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

                    borrado.delete(route('faenas.destroy', eliminando.id), {
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

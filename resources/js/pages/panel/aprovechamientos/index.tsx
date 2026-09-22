import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Eye,
    Pencil,
    Plus,
    Printer,
    Receipt,
    Search,
    Trash2,
    TriangleAlert,
    Waves,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { BarraSaldo } from '@/components/panel/aprovechamientos/barra-saldo';
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
import type { CupoFila } from '@/types/aprovechamientos';

/**
 *  LISTADO DE CUPOS DE PESCA
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
     */
    modoEstricto: boolean;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    /*
     * Se guarda la FILA entera y no solo el id: la ventana de confirmación
     * muestra de quién es el cupo y de cuántos kilos, y con el id habría que
     * volver a buscarla en el arreglo cada vez que se dibuja.
     */
    const [eliminando, setEliminando] = useState<CupoFila | null>(null);
    const borrado = useForm({ motivo: '' });

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
            titulo="Autorización de Pesca para Aprovechamiento Pesquero"
            descripcion="La bolsa madre: el volumen anual que se le autoriza a cada pescador."
            acciones={
                puede('aprovechamientos.crear') && (
                    <Button onClick={() => router.visit(route('aprovechamientos.create'))}>
                        <Plus className="size-4" />
                        Registrar aprovechamiento
                    </Button>
                )
            }
        >
            <Head title="Autorizaciones de pesca" />

            {!modoEstricto && <AvisoModoFlexible />}

            {/* `min-w-0`: sin él la tarjeta se estira al ancho de la tabla y el
                que termina con barra de desplazamiento es el documento entero. */}
            <Card className="min-w-0">
                <CardContent className="space-y-4 p-0">
                    {/*
                        LA BARRA DE ARRIBA DE LA TABLA, la misma del padrón de
                        beneficiarios: «Mostrar N» a la izquierda, el filtro de
                        estado al medio y el buscador a la derecha, sobre doce
                        columnas. Ver pages/panel/beneficiarios/index.tsx.
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

                        {/*
                            EL ESTADO VA PEGADO AL BUSCADOR, en el mismo grupo de
                            la derecha: los dos filtran lo mismo —qué filas se
                            ven— y separados por media pantalla se leían como dos
                            controles sin relación.
                        */}
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
                                El buscador es un <form> propio para que el Enter
                                lo envíe. No busca mientras se teclea: cada tecla
                                sería una consulta que recorre la tabla entera.
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
                                    aria-label="Buscar por cédula o nombre"
                                />
                            </form>
                        </div>
                    </div>

                    {cupos.data.length === 0 ? (
                        <EstadoVacio
                            icono={Waves}
                            titulo="Sin autorización de pesca para aprovechamiento pesquero"
                            descripcion={
                                filtros.buscar || filtros.estado
                                    ? 'Ninguno coincide con los filtros.'
                                    : ''
                            }
                        />
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">Beneficiario</th>
                                            <th className="px-5 py-2.5 font-medium">Escala</th>
                                            <th className="px-5 py-2.5 font-medium">Saldo</th>
                                            {/* <th className="px-5 py-2.5 text-right font-medium">Cobro</th> */}
                                            <th className="px-5 py-2.5 font-medium">Solicitado</th>
                                            {/* Vacío mientras no lo firmen: ver
                                                `fecha_emision` en el MER. */}
                                            <th className="px-5 py-2.5 font-medium">Otorgado</th>
                                            <th className="px-5 py-2.5 font-medium">Vence</th>
                                            {/* CUÁNDO SE CARGÓ, antes del estado: es lo que
                                                ordena el trabajo del día —qué entró recién y qué
                                                está esperando desde ayer—. */}
                                            <th className="px-5 py-2.5 font-medium">Registrado</th>
                                            {/* ESTADO AL FINAL, pegado a los botones: es lo que
                                                decide cuáles aparecen, y leerlo al lado de ellos
                                                explica por qué falta el de imprimir. */}
                                            <th className="px-5 py-2.5 font-medium">Estado</th>
                                            {/* Sin rótulo: los iconos se explican
                                                solos y un encabezado «Acciones»
                                                solo gasta ancho. */}
                                            <th className="px-5 py-2.5" />
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {cupos.data.map((c) => (
                                            <tr key={c.id} className="hover:bg-secondary/50">
                                                {/* --- Foto, nombre y cédula, juntos.
                                                    Mismo bloque que el padrón de beneficiarios: el
                                                    hueco de la silueta se dibuja igual cuando no hay
                                                    foto, así las filas no cambian de alto y la
                                                    columna del nombre no se corre entre una y otra. */}
                                                <td className="px-5 py-2.5">
                                                    <div className="flex items-center gap-3">
                                                        <Retrato
                                                            url={c.foto_url}
                                                            nombre={c.beneficiario ?? 'Sin nombre'}
                                                        />

                                                        <div className="min-w-0">
                                                            <Link
                                                                href={route('aprovechamientos.show', c.id)}
                                                                className="font-medium text-primary hover:underline"
                                                            >
                                                                {c.beneficiario ?? '—'}
                                                            </Link>
                                                            {/* tabular-nums: los dígitos ocupan lo
                                                                mismo y las cédulas quedan alineadas
                                                                entre filas. */}
                                                            <p className="tabular-nums text-xs text-muted-foreground">
                                                                {c.documento ?? '—'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* SOLO EL RANGO, sin el número del tramo: es
                                                    un dato interno del catálogo y en la columna
                                                    se leía como un id. «201 Kg Hasta 300 Kg» dice
                                                    lo mismo y se entiende sin la tabla al lado. */}
                                                <td className="px-5 py-2.5">
                                                    {c.descripcion ?? '—'}
                                                </td>

                                                <td className="min-w-40 px-5 py-2.5">
                                                    <BarraSaldo cupo={c} />
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
                                                    LOS TRES BOTONES VAN CON COLOR, y cada uno con
                                                    el suyo: el ojo en azul es CONSULTAR —no cambia
                                                    nada—, el lápiz en ámbar es MODIFICAR y el
                                                    tacho en rojo es DESTRUIR.
                                                */}
                                                <td className="px-5 py-2.5">
                                                    <div className="flex justify-end gap-1">
                                                        {/* EL RECIBO, desde que el trámite se
                                                            presentó: existe a partir del envío, así
                                                            que sale en revisión y sigue después. */}
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

                                                        {/* LA AUTORIZACIÓN EN PDF, sin pasar por la
                                                            ficha: es el papel que la persona viene
                                                            a buscar. Sale con el cupo firmado, y
                                                            abre pestaña porque vuelve un archivo. */}
                                                        {puede('aprovechamientos.imprimir') &&
                                                            c.ya_fue_aprobado && (
                                                                <a
                                                                    href={route(
                                                                        'aprovechamientos.autorizacion',
                                                                        c.id,
                                                                    )}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    title="Autorización de pesca"
                                                                    aria-label={`Imprimir la autorización de ${c.beneficiario ?? 'la persona'}`}
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

                                                        <Button
                                                            variant="ver"
                                                            size="sm"
                                                            onClick={() =>
                                                                router.visit(
                                                                    route('aprovechamientos.show', c.id),
                                                                )
                                                            }
                                                            aria-label={`Ver el cupo de ${c.beneficiario ?? 'la persona'}`}
                                                            title="Ver"
                                                        >
                                                            <Eye className="size-4" />
                                                        </Button>

                                                        {puede('aprovechamientos.editar') &&
                                                            c.puede_editarse && (
                                                                <Button
                                                                    variant="editar"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        router.visit(
                                                                            route(
                                                                                'aprovechamientos.edit',
                                                                                c.id,
                                                                            ),
                                                                        )
                                                                    }
                                                                    aria-label={`Editar el cupo de ${c.beneficiario ?? 'la persona'}`}
                                                                    title="Editar"
                                                                >
                                                                    <Pencil className="size-4" />
                                                                </Button>
                                                            )}

                                                        {puede('aprovechamientos.eliminar') &&
                                                            c.puede_eliminarse && (
                                                                <Button
                                                                    variant="eliminar"
                                                                    size="sm"
                                                                    onClick={() => setEliminando(c)}
                                                                    aria-label={`Eliminar el cupo de ${c.beneficiario ?? 'la persona'}`}
                                                                    title="Eliminar"
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

                            <Paginacion paginado={cupos} />
                        </>
                    )}
                </CardContent>
            </Card>

            {/*
                LA MISMA VENTANA QUE EN LA FICHA: motivo obligatorio Y casilla de
                consentimiento.
            */}
            <ConfirmarConMotivo
                abierto={eliminando !== null}
                titulo="Eliminar este aprovechamiento"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            Se da de baja el cupo de{' '}
                            <strong>{eliminando?.beneficiario ?? 'el pescador'}</strong>: escala{' '}
                            {eliminando?.escala ?? '—'}, {eliminando?.volumen_total_kg ?? 0} kg.
                        </p>
                        <p>
                            Solo se puede porque está <strong>pendiente</strong>, sin ningún cobro ni
                            faena encima.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la eliminación"
                ayuda="Queda en la auditoría con su nombre, y es lo que va a explicar la baja dentro de seis meses."
                placeholder="Cargado por error: el tramo corresponde a otro pescador."
                textoConfirmar="Eliminar aprovechamiento"
                confirmacion="Entiendo que el cupo desaparece del sistema y que esto no se deshace desde el panel."
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

                    borrado.delete(route('aprovechamientos.destroy', eliminando.id), {
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

/**
 * EL AVISO DE MODO FLEXIBLE.
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

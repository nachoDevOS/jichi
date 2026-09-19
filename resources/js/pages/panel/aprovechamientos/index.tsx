import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus, Search, Trash2, TriangleAlert, Waves } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { BarraSaldo } from '@/components/panel/aprovechamientos/barra-saldo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
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
                                            <th className="px-5 py-2.5 font-medium">Saldo</th>
                                            <th className="px-5 py-2.5 font-medium">Estado</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Cobro</th>
                                            <th className="px-5 py-2.5 font-medium">Vence</th>
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

                                                <td className="px-5 py-2.5">
                                                    <span className="font-semibold tabular-nums">
                                                        {c.escala ?? '—'}
                                                    </span>
                                                    <p className="text-xs text-muted-foreground">
                                                        {c.descripcion ?? '—'}
                                                    </p>
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

                                                {/*
                                                    LOS TRES BOTONES VAN CON COLOR, y cada uno con
                                                    el suyo: el ojo en azul es CONSULTAR —no cambia
                                                    nada—, el lápiz en ámbar es MODIFICAR y el
                                                    tacho en rojo es DESTRUIR.

                                                    Tres iconos del mismo gris obligan a leer el
                                                    dibujo antes de cada clic, y con el rojo al lado
                                                    del ámbar eso se paga caro.

                                                    El fondo va tenue y el color fuerte en el icono:
                                                    tres botones sólidos en cada fila competirían
                                                    con el dato de la tabla, que es lo que el
                                                    operador vino a mirar.

                                                    EDITAR Y ELIMINAR SOLO APARECEN SOBRE UN
                                                    BORRADOR, y las dos banderas llegan resueltas
                                                    del servidor.

                                                    Ninguna es «el estado es pendiente»:
                                                    `puede_editarse` es eso Y que no haya entrado
                                                    plata; `puede_eliminarse` suma además que no
                                                    tenga faenas. Van junto con el permiso, porque
                                                    esconder un botón es comodidad, no seguridad —
                                                    la ruta lo exige igual, y el servicio lo vuelve
                                                    a comprobar con la fila bloqueada.
                                                */}
                                                <td className="px-5 py-2.5">
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                router.visit(
                                                                    route('aprovechamientos.show', c.id),
                                                                )
                                                            }
                                                            aria-label={`Ver el cupo de ${c.beneficiario ?? 'la persona'}`}
                                                            title="Ver"
                                                            className="text-sky-600 hover:bg-sky-50 hover:text-sky-700 dark:text-sky-400 dark:hover:bg-sky-500/10 dark:hover:text-sky-300"
                                                        >
                                                            <Eye className="size-4" />
                                                        </Button>

                                                        {puede('aprovechamientos.editar') &&
                                                            c.puede_editarse && (
                                                                <Button
                                                                    variant="ghost"
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
                                                                    className="text-amber-600 hover:bg-amber-50 hover:text-amber-700 dark:text-amber-400 dark:hover:bg-amber-500/10 dark:hover:text-amber-300"
                                                                >
                                                                    <Pencil className="size-4" />
                                                                </Button>
                                                            )}

                                                        {puede('aprovechamientos.eliminar') &&
                                                            c.puede_eliminarse && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => setEliminando(c)}
                                                                    aria-label={`Eliminar el cupo de ${c.beneficiario ?? 'la persona'}`}
                                                                    title="Eliminar"
                                                                    className="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-500/10 dark:hover:text-rose-300"
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

                El motivo es lo único que sobrevive —la fila se da de baja y solo
                queda la línea de auditoría— y la casilla frena el clic
                automático: escribir un motivo es una tarea, marcar «entiendo que
                esto no se deshace» es una decisión.

                Se dibuja UNA sola vez fuera de la tabla, no una por fila: con
                cincuenta cupos habría cincuenta ventanas ocultas en el árbol.
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

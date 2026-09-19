import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowUpRight, Banknote, Pencil, Ship, Trash2, TrendingUp, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { BarraSaldo } from '@/components/panel/aprovechamientos/barra-saldo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { CupoFicha, FaenaDelCupo } from '@/types/aprovechamientos';

/**
 * ============================================================================
 *  LA FICHA DE UN CUPO
 * ============================================================================
 *
 * Arriba el saldo, abajo las faenas que lo explican. Ese orden es el punto:
 * «le quedan 20 kg» es un número que hay que creer hasta que se ve de dónde
 * sale.
 *
 * ----------------------------------------------------------------------------
 *  LAS FAENAS VENCIDAS SE MARCAN APARTE
 * ----------------------------------------------------------------------------
 *
 * Una faena vencida LIBERA su volumen: la salida no ocurrió. Sin marcarlas, la
 * suma de la lista no cuadra con el saldo de arriba y parece un error del
 * sistema. Por eso van tachadas y con la aclaración al lado.
 */
export default function VerCupo({
    cupo,
    faenas,
    modoEstricto,
}: {
    cupo: CupoFicha;
    faenas: FaenaDelCupo[];
    /** Lo que dice APROVECHAMIENTO_ESTRICTO: cambia qué significa un saldo en cero. */
    modoEstricto: boolean;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [ampliando, setAmpliando] = useState(false);

    const [eliminando, setEliminando] = useState(false);

    /*
     * DOS `useForm` SEPARADOS, y no uno con los tres campos.
     *
     * Comparten el nombre `motivo` pero no el destino ni las reglas: el del
     * borrado manda un DELETE y el de la ampliación un PATCH con kilos. Con un
     * solo formulario, el error que devuelve uno se pintaría en la ventana del
     * otro —las dos leen `form.errors.motivo`— y el texto escrito para ampliar
     * seguiría ahí al abrir la de eliminar.
     */
    const form = useForm({ kilos_adicionales: '', motivo: '' });
    const borrado = useForm({ motivo: '' });

    return (
        <LayoutPanel
            titulo={cupo.beneficiario ?? 'Cupo de pesca'}
            descripcion={`Escala ${cupo.escala ?? '—'} · ${cupo.descripcion ?? ''}`}
            acciones={
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.visit(route('beneficiarios.show', cupo.beneficiario_id))
                        }
                    >
                        Ver al pescador
                    </Button>

                    {/*
                        COBRAR ES LA ACCIÓN QUE SIGUE A OTORGAR, y por eso el
                        botón aparece mientras quede saldo.

                        Otorgar ya deja al operador en la caja; esto cubre el otro
                        camino: el cupo que quedó a medio pagar y que alguien abre
                        días después. Sin el botón habría que ir a Caja y volver a
                        buscar a la persona a mano.

                        Va con `?beneficiario=` —y no con el id del cupo— porque
                        el formulario de cobro trae TODAS las deudas de esa
                        persona: un mismo recibo cubre el carnet y la autorización
                        si los dos están pendientes, que es lo que hace la
                        ventanilla.
                    */}
                    {/*
                        EDITAR Y ELIMINAR SOLO SOBRE EL BORRADOR.

                        Las dos banderas llegan resueltas del servidor: no son
                        «el estado es pendiente» sino eso Y que no haya entrado
                        plata —y para eliminar, además, que no tenga faenas—.
                        Deducirlas acá sería una segunda copia de tres reglas.
                    */}
                    {puede('aprovechamientos.editar') && cupo.puede_editarse && (
                        <Button
                            variant="outline"
                            onClick={() => router.visit(route('aprovechamientos.edit', cupo.id))}
                        >
                            <Pencil className="size-4" />
                            Corregir
                        </Button>
                    )}

                    {puede('aprovechamientos.eliminar') && cupo.puede_eliminarse && (
                        <Button variant="outline" onClick={() => setEliminando(true)}>
                            <Trash2 className="size-4" />
                            Eliminar
                        </Button>
                    )}

                    {puede('caja.cobrar') && !cupo.pagado && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.visit(route('caja.create', { beneficiario: cupo.beneficiario_id }))
                            }
                        >
                            <Banknote className="size-4" />
                            Cobrar
                        </Button>
                    )}

                    {/*
                        `puede_ampliarse` y NO `vigente`. Un cupo AGOTADO no está
                        vigente —su estado no habilita— y es justamente el que hay
                        que poder ampliar: se le acabaron los kilos, no el tiempo.
                        Decidiendo con `vigente`, el botón se escondería en el
                        único caso en que hace falta.

                        Dos cosas lo esconden, y las dos vienen resueltas adentro
                        de esa bandera: la FECHA pasada —sumarle kilos a un cupo
                        vencido daría volumen que las faenas no van a poder usar— y
                        la MODALIDAD, porque una especie especial no se amplía
                        nunca. Ofrecer el botón sobre un cupo de paiche y que el
                        servidor lo rechazara sería peor que no ofrecerlo.
                    */}
                    {puede('aprovechamientos.ampliar') && cupo.puede_ampliarse && (
                        <Button onClick={() => setAmpliando(true)}>
                            <TrendingUp className="size-4" />
                            Ampliar cupo
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={`Cupo · ${cupo.beneficiario ?? ''}`} />

            <div className="grid gap-6 lg:grid-cols-3">
                {/* ------------------------------------------------ El saldo */}
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Volumen</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        <BarraSaldo cupo={cupo} />

                        {/*
                            EL RÉGIMEN VA JUNTO AL VOLUMEN porque es lo que explica
                            qué se puede hacer cuando ese volumen se acabe: en la
                            escala general se amplía, en la especie especial hay que
                            tramitar de nuevo.
                        */}
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge color={cupo.modalidad_color}>{cupo.modalidad_etiqueta}</Badge>

                            <span className="text-sm text-muted-foreground">
                                {cupo.modalidad === 'especie_especial'
                                    ? 'Cuota específica de la especie: no se amplía. Agotada, hay que tramitar un cupo nuevo.'
                                    : 'Cupo acumulativo: las faenas lo descuentan y se puede ampliar.'}
                            </span>
                        </div>

                        <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                            <Dato etiqueta="Otorgado" valor={`${cupo.volumen_total_kg} kg`} />
                            <Dato etiqueta="Consumido" valor={`${cupo.kilos_consumidos} kg`} />
                            <Dato etiqueta="Disponible" valor={`${cupo.saldo_kg} kg`} />
                            <Dato etiqueta="Usado" valor={`${cupo.porcentaje_usado}%`} />
                        </dl>

                        {/*
                            EL EXCESO SOLO PUEDE EXISTIR EN MODO FLEXIBLE: con la
                            validación encendida la emisión frena antes. Cuando
                            aparece es un hecho consumado —el pescado ya se
                            extrajo— así que se muestra como dato, no como un error
                            que alguien pueda corregir desde acá.
                        */}
                        {/*
                            PENDIENTE NO ES UN DETALLE DE COLOR: el cupo existe,
                            está en fecha y con el volumen entero, y aun así NO
                            autoriza a pescar. Sin decirlo acá, la única señal
                            sería una etiqueta celeste y el operador emitiría una
                            faena para descubrirlo recién con el error.
                        */}
                        {cupo.estado === 'pendiente' && (
                            <p className="flex items-start gap-2 rounded-md bg-sky-50 p-3 text-sm text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">
                                <Banknote className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    <strong>Pendiente de pago.</strong> Todavía no autoriza a pescar:
                                    lo que habilita es la concesión cobrada. Mientras tanto se puede
                                    corregir o eliminar.
                                </span>
                            </p>
                        )}

                        {cupo.excedido && (
                            <p className="flex items-start gap-2 rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    Las faenas emitidas suman{' '}
                                    <strong>{cupo.kilos_excedidos} kg por encima</strong> del volumen
                                    otorgado. El control de cupo está desactivado, así que la emisión
                                    no lo frenó.
                                </span>
                            </p>
                        )}

                        {/*
                            UNA AMPLIACIÓN NO CREA UNA FILA PROPIA: suma sobre el
                            volumen otorgado. Sin este aviso, la diferencia entre
                            lo que la escala daba y lo que la persona tiene sería
                            invisible en la pantalla.
                        */}
                        {cupo.fue_ampliado && cupo.escala_rango && (
                            <p className="flex items-start gap-2 rounded-md bg-secondary/50 p-3 text-sm">
                                <ArrowUpRight className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <span>
                                    Este cupo fue <strong>ampliado</strong>: la escala{' '}
                                    {cupo.escala} otorgaba {cupo.escala_rango[1]} kg y hoy tiene{' '}
                                    {cupo.volumen_total_kg}. El motivo quedó registrado en la
                                    auditoría.
                                </span>
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* ------------------------------------------------ Estado y cobro */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Situación</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-3 text-sm">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Estado</span>
                            <Badge color={cupo.estado_color}>{cupo.estado_etiqueta}</Badge>
                        </div>

                        {/*
                            EL RENGLÓN «Tipo de Embarcación» DEL TALONARIO. Se
                            distingue el NULL de una cadena vacía: «no declarada»
                            dice que nadie lo llenó, un renglón en blanco no dice
                            nada.
                        */}
                        <Dato
                            etiqueta="Embarcación"
                            valor={cupo.tipo_embarcacion ?? 'No declarada'}
                        />

                        <Dato etiqueta="Otorgado el" valor={fecha(cupo.fecha_emision)} />
                        <Dato etiqueta="Vence el" valor={fecha(cupo.fecha_vencimiento)} />
                        <Dato etiqueta="Monto" valor={bs(cupo.monto, institucion.moneda)} />

                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Cobro</span>
                            {cupo.pagado ? (
                                <span className="font-medium text-emerald-700 dark:text-emerald-400">
                                    Pagado
                                </span>
                            ) : (
                                <span className="font-medium text-amber-700 dark:text-amber-400">
                                    debe {bs(cupo.saldo_pendiente, institucion.moneda)}
                                </span>
                            )}
                        </div>

                        {/*
                            La conclusión, ya resuelta por el servidor: las tres
                            condiciones —vigente, con saldo, sin agotar— juntas.
                            La pantalla no las vuelve a evaluar.
                        */}
                        <p
                            className={
                                cupo.puede_emitir_faena
                                    ? 'rounded-md bg-emerald-50 p-3 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200'
                                    : 'rounded-md bg-amber-50 p-3 text-amber-800 dark:bg-amber-500/10 dark:text-amber-200'
                            }
                        >
                            {cupo.puede_emitir_faena
                                ? modoEstricto
                                    ? 'Habilitado para emitir faenas.'
                                    : 'Habilitado para emitir faenas. El control de saldo está desactivado: se siguen emitiendo aunque el cupo se agote.'
                                : 'No se le pueden emitir faenas: el cupo está vencido o sin saldo.'}
                        </p>
                    </CardContent>
                </Card>

                {/* ------------------------------------------------ Las faenas */}
                <Card className="min-w-0 lg:col-span-3">
                    <CardHeader>
                        <CardTitle>Faenas emitidas</CardTitle>
                    </CardHeader>

                    <CardContent className="p-0">
                        {faenas.length === 0 ? (
                            <EstadoVacio
                                icono={Ship}
                                titulo="Sin faenas"
                                descripcion="Todavía no se emitió ninguna salida contra este cupo."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">N°</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Kilos</th>
                                            <th className="px-5 py-2.5 font-medium">Estado</th>
                                            <th className="px-5 py-2.5 font-medium">Salida</th>
                                            <th className="px-5 py-2.5 font-medium">Límite</th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {faenas.map((f) => (
                                            <tr key={f.id} className="hover:bg-secondary/50">
                                                <td className="px-5 py-2.5 font-mono tabular-nums">
                                                    {String(f.numero_faena).padStart(4, '0')}
                                                </td>

                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {/*
                                                        Tachado cuando NO consume cupo: es lo que
                                                        hace que la suma de la columna cuadre con
                                                        el saldo de arriba.
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
                                                    {!f.consume_cupo && (
                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                            liberados
                                                        </span>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <Badge color={f.estado_color}>{f.estado_etiqueta}</Badge>
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
                        )}
                    </CardContent>
                </Card>
            </div>

            {/*
                AMPLIAR PIDE MOTIVO POR ESCRITO. Es dar más kilos de los que la
                escala otorgaba —lo que el cupo viene a limitar— así que sin el
                motivo, dentro de seis meses nadie puede explicar por qué esta
                persona tuvo 800 kg cuando su tramo daba 500.
            */}
            {/*
                ================================================================
                 ELIMINAR PIDE MOTIVO **Y** CASILLA DE CONSENTIMIENTO
                ================================================================

                Las dos cosas, y cada una tapa algo distinto:

                  - EL MOTIVO es lo único que sobrevive. La fila se borra de
                    verdad, así que dentro de seis meses la única respuesta
                    posible a «¿y el cupo de Fulano?» es la línea de auditoría.
                    Sin texto ahí, esa respuesta es «alguien lo borró».

                  - LA CASILLA frena el clic automático. Escribir un motivo es
                    una tarea; marcar «entiendo que esto no se deshace» es una
                    decisión, y son dos actos distintos a propósito.

                El mínimo de 10 caracteres es el mismo que exige
                EliminarCupoRequest: si acá fuera menor, el botón se habilitaría
                y el servidor rechazaría igual.
            */}
            <ConfirmarConMotivo
                abierto={eliminando}
                titulo="Eliminar este aprovechamiento"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            Se da de baja el cupo de{' '}
                            <strong>{cupo.beneficiario ?? 'el pescador'}</strong>: escala{' '}
                            {cupo.escala ?? '—'}, {cupo.volumen_total_kg} kg.
                        </p>
                        <p>
                            Solo se puede porque está <strong>pendiente de pago</strong>, sin ningún
                            cobro ni faena encima. No se deshace.
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
                    setEliminando(false);
                    borrado.reset();
                }}
                onConfirmar={() =>
                    borrado.delete(route('aprovechamientos.destroy', cupo.id), {
                        preserveScroll: true,
                        // Sin onSuccess: al borrarse, el servidor redirige al
                        // listado y esta pantalla deja de existir.
                        onError: () => setEliminando(true),
                    })
                }
            />

            <ConfirmarConMotivo
                abierto={ampliando}
                titulo="Ampliar el cupo"
                descripcion={
                    <div className="space-y-3">
                        <p>
                            Se SUMAN kilos al volumen otorgado. Hoy tiene{' '}
                            <strong>{cupo.volumen_total_kg} kg</strong>, con {cupo.saldo_kg}{' '}
                            disponibles.
                        </p>

                        <div className="space-y-2">
                            <Label htmlFor="kilos_adicionales">Kilos a sumar</Label>
                            <Input
                                id="kilos_adicionales"
                                type="number"
                                step="0.01"
                                min={0}
                                value={form.data.kilos_adicionales}
                                onChange={(e) => form.setData('kilos_adicionales', e.target.value)}
                                aria-invalid={Boolean(form.errors.kilos_adicionales)}
                            />
                            {form.errors.kilos_adicionales && (
                                <p className="text-sm text-destructive">
                                    {form.errors.kilos_adicionales}
                                </p>
                            )}
                        </div>
                    </div>
                }
                etiquetaMotivo="Motivo de la ampliación"
                ayuda="Queda en la auditoría con su nombre. Explique la resolución o la decisión que la respalda."
                placeholder="Resolución administrativa 042/2026: ampliación por temporada de paiche."
                textoConfirmar="Ampliar cupo"
                valor={form.data.motivo}
                onCambiar={(v) => form.setData('motivo', v)}
                error={form.errors.motivo}
                procesando={form.processing}
                onCancelar={() => setAmpliando(false)}
                onConfirmar={() =>
                    form.patch(route('aprovechamientos.ampliar', cupo.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setAmpliando(false);
                            form.reset();
                        },
                    })
                }
            />
        </LayoutPanel>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted-foreground">{etiqueta}</span>
            <span className="text-right font-medium tabular-nums">{valor}</span>
        </div>
    );
}

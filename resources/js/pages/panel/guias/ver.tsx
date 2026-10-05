import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Ban, CalendarX, Clock, IdCard, Pencil, Printer, Receipt, Trash2, Truck, User } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { TextoCopiable } from '@/components/comunes/texto-copiable';
import { TarjetaRecaudaciones } from '@/components/panel/pagos/tarjeta-recaudaciones';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha, fechaHora } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { ReciboDelCupo } from '@/types/aprovechamientos';
import type { GuiaFicha } from '@/types/guias';

/**
 *  La ficha de una guía
 *
 * El traslado se paga en SIREB como el carnet, la autorización y la faena
 * —pendiente → aprobada—, así que la tarjeta de pagos es la misma.
 */
export default function VerGuia({
    guia,
    recibo,
}: {
    guia: GuiaFicha;
    recibo: ReciboDelCupo | null;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;

    const [eliminando, setEliminando] = useState(false);
    const [anulando, setAnulando] = useState(false);

    const borrado = useForm({ motivo: '' });
    const baja = useForm({ motivo: '' });

    return (
        <LayoutPanel
            titulo={guia.etiqueta}
            descripcion={`${guia.comercializador ?? '—'} · ${guia.ruta}`}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {guia.beneficiario_id !== null && (
                        <Button
                            variant="ver"
                            onClick={() => router.visit(route('beneficiarios.show', guia.beneficiario_id!))}
                        >
                            <User className="size-4" />
                            Beneficiario
                        </Button>
                    )}

                    {guia.carnet_id !== null && (
                        <Button
                            variant="ver"
                            onClick={() => router.visit(route('carnets.show', guia.carnet_id!))}
                        >
                            <IdCard className="size-4" />
                            Ver carnet
                        </Button>
                    )}

                    {/* Corregir y eliminar solo sobre el borrador. Las dos
                        banderas llegan resueltas: miran el estado Y que no haya
                        entrado un depósito. */}
                    {puede('guias.editar') && guia.puede_editarse && (
                        <Button variant="editar" onClick={() => router.visit(route('guias.edit', guia.id))}>
                            <Pencil className="size-4" />
                            Editar
                        </Button>
                    )}

                    {puede('guias.eliminar') && guia.puede_eliminarse && (
                        <Button variant="eliminar" onClick={() => setEliminando(true)}>
                            <Trash2 className="size-4" />
                            Eliminar
                        </Button>
                    )}



                    {/*
                        LA GUÍA EN PAPEL sale recién aprobada: hasta la firma no
                        hay nada que amparar. `ya_fue_aprobada` llega resuelto.
                    */}
                    {puede('guias.imprimir') && guia.ya_fue_aprobada && (
                        <a
                            href={route('guias.imprimir', guia.id)}
                            target="_blank"
                            rel="noreferrer"
                            className={cn(buttonVariants({ variant: 'outline' }))}
                        >
                            <Printer className="size-4" />
                            Guía
                        </a>
                    )}

                    {/* El recibo existe desde el ENVÍO: antes no hay papel. */}
                    {puede('recibos.imprimir') && guia.recibo_id !== null && (
                        <a
                            href={route('recibos.imprimir', guia.recibo_id)}
                            target="_blank"
                            rel="noreferrer"
                            className={cn(buttonVariants({ variant: 'outline' }))}
                        >
                            <Receipt className="size-4" />
                            Recibo
                        </a>
                    )}

                    {puede('guias.anular') && guia.puede_anularse && (
                        <Button variant="eliminar" onClick={() => setAnulando(true)}>
                            <Ban className="size-4" />
                            Anular
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={guia.etiqueta} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="min-w-0 space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>El traslado</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            <Situacion guia={guia} />

                            <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                                <Dato etiqueta="Carga" valor={`${guia.peso_total_kg} kg`} />
                                <Dato etiqueta="Arancel" valor={bs(guia.monto, institucion.moneda)} />
                                <Dato etiqueta="Solicitada" valor={fecha(guia.fecha_solicitud)} />
                                {/* La emisión la escribe la aprobación: hasta
                                    entonces esto es una solicitud. */}
                                <Dato etiqueta="Aprobada" valor={fechaHora(guia.fecha_emision)} />
                                <Dato etiqueta="Vence" valor={fechaHora(guia.fecha_vencimiento)} />
                                <Dato etiqueta="Asociación" valor={guia.asociacion_nombre ?? guia.asociacion ?? '—'} />
                                {/* De qué carnet cuelga. El número del libro es
                                    cómo se lo nombra; el código de 16 caracteres
                                    es la llave con la que se verifica. */}
                                <Dato etiqueta="Carnet" valor={`N° ${guia.carnet_registro ?? '—'}`} />
                                <Dato
                                    etiqueta="Código del carnet"
                                    valor={guia.carnet_codigo ? <TextoCopiable texto={guia.carnet_codigo} className="-ml-1.5" /> : '—'}
                                />
                                {/* La llave del QR de la guía impresa. */}
                                <Dato
                                    etiqueta="Código"
                                    valor={guia.codigo ? <TextoCopiable texto={guia.codigo} className="-ml-1.5" /> : '—'}
                                />
                            </dl>

                            {guia.es_piscicultura && (
                                <p className="rounded-md bg-secondary p-3 text-xs text-muted-foreground">
                                    Producto de <strong>piscicultura</strong>: el arancel se cobró al{' '}
                                    {Math.round(guia.factor_arancel * 100)}%.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/*
                        LOS RENGLONES DEL TALONARIO. Se muestran aunque estén
                        vacíos: el papel los tiene igual, y un hueco acá es la
                        señal de que falta completarlo antes de imprimir.
                    */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Ubicación y transporte</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                                <Dato etiqueta="Origen" valor={guia.origen} />
                                <Dato etiqueta="Departamento" valor={guia.origen_departamento ?? '—'} />
                                <Dato etiqueta="Provincia" valor={guia.origen_provincia ?? '—'} />
                                <Dato etiqueta="Distrito o cuenca" valor={guia.origen_distrito ?? '—'} />

                                <Dato etiqueta="Destino" valor={guia.destino} />
                                <Dato etiqueta="Departamento" valor={guia.destino_departamento ?? '—'} />
                                <Dato etiqueta="Provincia" valor={guia.destino_provincia ?? '—'} />
                                <Dato etiqueta="Distrito o cuenca" valor={guia.destino_distrito ?? '—'} />
                            </dl>

                            <dl className="grid grid-cols-2 gap-4 border-t border-border pt-4 text-sm sm:grid-cols-4">
                                <Dato etiqueta="Medio" valor={guia.medio_transporte_etiqueta ?? '—'} />
                                <Dato etiqueta="Vehículo" valor={guia.tipo_transporte_etiqueta ?? '—'} />
                                <Dato etiqueta="Nombre o tipo" valor={guia.transporte_nombre ?? '—'} />
                                <Dato etiqueta="Placa" valor={guia.transporte_placa ?? '—'} />
                                <Dato
                                    etiqueta="Cap. máxima"
                                    valor={
                                        guia.transporte_capacidad_kg !== null
                                            ? `${guia.transporte_capacidad_kg} kg`
                                            : '—'
                                    }
                                />
                            </dl>

                            {guia.observaciones && (
                                <div className="border-t border-border pt-4">
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        Observaciones
                                    </p>
                                    <p className="whitespace-pre-line text-sm">{guia.observaciones}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* El cuadro D: lo que un control lee en la ruta. */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Productos hidrobiológicos</CardTitle>
                        </CardHeader>

                        <CardContent>
                            {/* `min-w-0` en el elemento de grilla, arriba: sin él
                                la tabla estira la tarjeta y el que termina con
                                barra es el documento entero. Ver CLAUDE.md. */}
                            <div className="min-w-0 overflow-x-auto">
                                <table className="w-full min-w-[520px] text-sm">
                                    <thead>
                                        <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <th className="py-2 pr-2 font-medium">Especie</th>
                                            <th className="py-2 pr-2 font-medium">Condición</th>
                                            <th className="py-2 pr-2 text-right font-medium">Kg</th>
                                            <th className="py-2 pr-2 text-right font-medium">Bs/kg</th>
                                            <th className="py-2 text-right font-medium">Importe</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {guia.detalles.map((d) => (
                                            <tr key={d.id} className="border-b border-border/60 last:border-0">
                                                <td className="py-2 pr-2 font-medium">{d.especie}</td>
                                                <td className="py-2 pr-2 text-muted-foreground">
                                                    {d.condicion_etiqueta}
                                                </td>
                                                <td className="py-2 pr-2 text-right tabular-nums">
                                                    {d.cantidad_kg.toFixed(2)}
                                                </td>
                                                <td className="py-2 pr-2 text-right tabular-nums">
                                                    {d.precio_kg.toFixed(2)}
                                                </td>
                                                <td className="py-2 text-right tabular-nums">
                                                    {d.importe_total.toFixed(2)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>

                                    <tfoot>
                                        <tr className="border-t border-border font-medium">
                                            <td className="py-2 pr-2" colSpan={2}>
                                                Totales
                                            </td>
                                            <td className="py-2 pr-2 text-right tabular-nums">
                                                {guia.peso_total_kg.toFixed(2)}
                                            </td>
                                            <td />
                                            <td className="py-2 text-right tabular-nums">
                                                {guia.detalles
                                                    .reduce((s, d) => s + d.importe_total, 0)
                                                    .toFixed(2)}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    {/* El cobro: se paga en SIREB, igual que los otros tres documentos. */}
                    <TarjetaRecaudaciones
                        monto={guia.monto}
                        sireb={guia.sireb}
                        recibo={recibo}
                        puedeVerificar={guia.puede_verificar_pago}
                        rutaVerificar={route('guias.verificar-pago', guia.id)}
                        puedeCargar={guia.puede_cargar_pago}
                        rutaCargar={route('guias.cargar-pago', guia.id)}
                        permiso="guias.crear"
                    />
                </div>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Quién traslada</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-3 text-sm">
                        <Dato etiqueta="Comercializador" valor={guia.comercializador ?? '—'} />
                        <Dato etiqueta="Documento" valor={guia.documento ?? '—'} />
                        <Dato etiqueta="N° de guía" valor={guia.numero_legible} />
                        <Dato etiqueta="Recibo" valor={guia.recibo_numero ?? '—'} />

                        <p className="text-xs text-muted-foreground">
                            {guia.vigente && guia.horas_restantes !== null
                                ? `Quedan ${guia.horas_restantes} h de validez.`
                                : (guia.motivo_sin_amparar ?? 'La guía ya no ampara ningún traslado.')}
                        </p>
                    </CardContent>
                </Card>
            </div>



            {/* ELIMINAR: solo el borrador, y el número queda quemado igual. */}
            <ConfirmarConMotivo
                abierto={eliminando}
                titulo="Eliminar esta guía"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            Se da de baja la guía <strong>N° {guia.numero_legible}</strong> de{' '}
                            <strong>{guia.comercializador ?? 'el comerciante'}</strong>:{' '}
                            {guia.peso_total_kg} kg, {guia.ruta}.
                        </p>
                        <p>
                            Solo se puede porque está <strong>pendiente</strong> y sin ningún
                            depósito cargado. El número del talonario{' '}
                            <strong>no se reutiliza</strong>.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la eliminación"
                ayuda="Queda en la auditoría con su nombre, y es lo que va a explicar el hueco en la serie dentro de seis meses."
                placeholder="Cargada por error: el traslado corresponde a otro comerciante."
                textoConfirmar="Eliminar guía"
                confirmacion="Entiendo que el número del talonario queda quemado y que esto no se deshace desde el panel."
                valor={borrado.data.motivo}
                onCambiar={(v) => borrado.setData('motivo', v)}
                error={borrado.errors.motivo}
                procesando={borrado.processing}
                onCancelar={() => {
                    setEliminando(false);
                    borrado.reset();
                }}
                onConfirmar={() =>
                    borrado.delete(route('guias.destroy', guia.id), {
                        onSuccess: () => {
                            setEliminando(false);
                            borrado.reset();
                        },
                    })
                }
            />

            {/* ANULAR: sobre una guía YA APROBADA. No es lo mismo que eliminar. */}
            <ConfirmarConMotivo
                abierto={anulando}
                titulo="¿Anular la guía?"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El papel deja de amparar el traslado al instante. El número{' '}
                            <strong>{guia.numero_legible}</strong> queda ocupado para siempre: la
                            hoja del talonario se gastó.
                        </p>
                        <p>No se desanula. Si hace falta, se emite otra.</p>
                    </div>
                }
                etiquetaMotivo="Motivo de la anulación"
                ayuda="Queda en la auditoría con su nombre. Deja un hueco en la serie que alguien va a tener que explicar."
                placeholder="Se emitió con el destino equivocado; se reemplaza por la guía N° 000310."
                textoConfirmar="Anular guía"
                confirmacion="Entiendo que el número queda quemado y que esto no se puede revertir."
                valor={baja.data.motivo}
                onCambiar={(v) => baja.setData('motivo', v)}
                error={baja.errors.motivo}
                procesando={baja.processing}
                onCancelar={() => setAnulando(false)}
                onConfirmar={() =>
                    baja.patch(route('guias.anular', guia.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setAnulando(false);
                            baja.reset();
                        },
                    })
                }
            />
        </LayoutPanel>
    );
}

/**
 * En qué situación está el traslado, en una frase.
 */
function Situacion({ guia }: { guia: GuiaFicha }) {
    /*
     * Mientras nadie la firmó, la guía no ampara nada, y el porqué llega
     * RESUELTO del servidor —`motivo_sin_amparar`—. React no vuelve a evaluar
     * el estado: así nacieron los carteles que decían «venció» sobre un
     * expediente que recién se estaba armando.
     */
    if (guia.estado === 'pendiente') {
        return (
            <Marco
                clase="bg-sky-50 text-sky-900 dark:bg-sky-500/10 dark:text-sky-200"
                icono={<Clock className="mt-0.5 size-5 shrink-0" />}
                titulo={guia.estado_etiqueta}
                texto={guia.motivo_sin_amparar ?? 'Todavía no ampara el traslado.'}
            />
        );
    }

    if (guia.estado === 'anulada') {
        return (
            <Marco
                clase="bg-rose-50 text-rose-900 dark:bg-rose-500/10 dark:text-rose-200"
                icono={<Ban className="mt-0.5 size-5 shrink-0" />}
                titulo="Anulada"
                texto="Este papel no ampara ningún traslado. El número queda ocupado: la hoja del talonario se gastó."
            />
        );
    }

    if (guia.caducada) {
        return (
            <Marco
                clase="bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200"
                icono={<CalendarX className="mt-0.5 size-5 shrink-0" />}
                titulo="Vencida"
                texto="Pasaron sus días de validez: ya no ampara ningún traslado. Si la carga sigue en camino, necesita una guía nueva."
            />
        );
    }

    if (guia.vigente) {
        return (
            <Marco
                clase="bg-sky-50 text-sky-900 dark:bg-sky-500/10 dark:text-sky-200"
                icono={<Truck className="mt-0.5 size-5 shrink-0" />}
                titulo="En camino"
                texto={`Ampara el traslado hasta el ${fechaHora(guia.fecha_vencimiento)}${
                    guia.horas_restantes !== null ? ` — quedan ${guia.horas_restantes} h` : ''
                }.`}
            />
        );
    }

    return (
        <Marco
            clase="bg-rose-50 text-rose-900 dark:bg-rose-500/10 dark:text-rose-200"
            icono={<Ban className="mt-0.5 size-5 shrink-0" />}
            titulo={guia.estado_etiqueta}
            texto={guia.motivo_sin_amparar ?? 'No ampara el traslado.'}
        />
    );
}

function Marco({
    clase,
    icono,
    titulo,
    texto,
}: {
    clase: string;
    icono: ReactNode;
    titulo: string;
    texto: string;
}) {
    return (
        <div className={`flex items-start gap-3 rounded-md p-4 text-sm ${clase}`}>
            {icono}
            <div>
                <p className="font-medium">{titulo}</p>
                <p className="opacity-80">{texto}</p>
            </div>
        </div>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: ReactNode }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">{etiqueta}</dt>
            <dd className="font-medium tabular-nums">{valor}</dd>
        </div>
    );
}

import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Ban,
    CalendarX,
    Check,
    CheckCheck,
    Clock,
    IdCard,
    Pencil,
    Printer,
    Receipt,
    Send,
    Trash2,
    Truck,
    Undo2,
    User,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { TarjetaPagos } from '@/components/panel/pagos/tarjeta-pagos';
import { Button, buttonVariants } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarAccion } from '@/components/ui/confirmar-accion';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { Input } from '@/components/ui/input';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha, fechaHora } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { PagoDelCupo, ReciboDelCupo } from '@/types/aprovechamientos';
import type { GuiaFicha } from '@/types/guias';

/**
 *  LA FICHA DE UNA GUÍA
 *
 * El traslado se cobra y se firma como el carnet, el cupo y la faena
 * —pendiente → en revisión → activa—, así que la tarjeta de pagos y los
 * botones del circuito son los mismos componentes.
 */
export default function VerGuia({
    guia,
    pagos,
    recibo,
}: {
    guia: GuiaFicha;
    pagos: PagoDelCupo[];
    recibo: ReciboDelCupo | null;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;

    const [cerrando, setCerrando] = useState(false);
    const [aprobando, setAprobando] = useState(false);
    const [rechazando, setRechazando] = useState(false);
    const [eliminando, setEliminando] = useState(false);
    const [anulando, setAnulando] = useState(false);

    const cierre = useForm({ peso_total_kg: String(guia.peso_total_kg) });
    const envio = useForm({});
    const rechazo = useForm({ motivo: '' });
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
                            Ver al beneficiario
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

                    {/* CORREGIR Y ELIMINAR SOLO SOBRE EL BORRADOR. Las dos
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
                        EL CIRCUITO, igual que en la faena: presentar es de
                        ventanilla y firmar es de supervisión. Las tres banderas
                        llegan resueltas del servidor.
                    */}
                    {puede('guias.enviar') && guia.puede_enviarse && (
                        <Button
                            onClick={() => envio.post(route('guias.enviar', guia.id), { preserveScroll: true })}
                            disabled={envio.processing}
                        >
                            <Send className="size-4" />
                            Enviar a revisión
                        </Button>
                    )}

                    {puede('guias.aprobar') && guia.puede_revisarse && (
                        <>
                            {/* Apagado mientras falte validar alguna boleta, y
                                el title dice cuántas: el servidor lo exige
                                igual, y un botón que promete y falla es peor. */}
                            <Button
                                onClick={() => setAprobando(true)}
                                disabled={envio.processing || !guia.puede_aprobarse}
                                title={
                                    guia.puede_aprobarse
                                        ? undefined
                                        : `Faltan ${guia.pagos_sin_validar} depósito(s) por validar`
                                }
                                className="bg-emerald-600 text-white hover:bg-emerald-700"
                            >
                                <Check className="size-4" />
                                Aprobar
                            </Button>

                            <Button variant="eliminar" onClick={() => setRechazando(true)}>
                                <Undo2 className="size-4" />
                                Rechazar
                            </Button>
                        </>
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
                            Imprimir guía
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
                            Imprimir recibo
                        </a>
                    )}

                    {puede('guias.cerrar') && guia.puede_cerrarse && (
                        <Button onClick={() => setCerrando((v) => !v)}>
                            <CheckCheck className="size-4" />
                            Registrar llegada
                        </Button>
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
                    {cerrando && guia.puede_cerrarse && (
                        <Card className="border-emerald-300 bg-emerald-50/50 dark:border-emerald-500/40 dark:bg-emerald-500/5">
                            <CardHeader>
                                <CardTitle>Registrar la llegada</CardTitle>
                            </CardHeader>

                            <CardContent>
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        cierre.patch(route('guias.cerrar', guia.id), {
                                            preserveScroll: true,
                                            onSuccess: () => setCerrando(false),
                                        });
                                    }}
                                    className="space-y-4"
                                >
                                    <Campo
                                        etiqueta="Peso descargado (kg)"
                                        htmlFor="peso_total_kg"
                                        error={cierre.errors.peso_total_kg}
                                        ayuda="Viene con lo declarado al salir. Corríjalo solo si la balanza de destino dijo otra cosa."
                                        className="max-w-xs"
                                    >
                                        <Input
                                            id="peso_total_kg"
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            value={cierre.data.peso_total_kg}
                                            onChange={(e) => cierre.setData('peso_total_kg', e.target.value)}
                                            aria-invalid={Boolean(cierre.errors.peso_total_kg)}
                                        />
                                    </Campo>

                                    <div className="flex gap-2">
                                        <Button type="submit" disabled={cierre.processing}>
                                            Cerrar guía
                                        </Button>

                                        <Button type="button" variant="outline" onClick={() => setCerrando(false)}>
                                            Cancelar
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    )}

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
                                {/* DE QUÉ CARNET CUELGA. El número del libro es
                                    cómo se lo nombra; el código de 16 caracteres
                                    es la llave con la que se verifica. */}
                                <Dato etiqueta="Carnet" valor={`N° ${guia.carnet_registro ?? '—'}`} />
                                <Dato etiqueta="Código del carnet" valor={guia.carnet_codigo ?? '—'} />
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

                    {/* EL CUADRO D: lo que un control lee en la ruta. */}
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

                    {/* LA MISMA TARJETA que el carnet, el cupo y la faena. */}
                    <TarjetaPagos
                        pagos={pagos}
                        recibo={recibo}
                        saldoPendiente={guia.saldo_pendiente}
                        titular={guia.comercializador ?? 'el titular'}
                        admitePagos={guia.admite_pagos}
                        rutaPagar={route('guias.pagar', guia.id)}
                        permisoEnviar="guias.enviar"
                        textoAlEnviar="La guía pasa a EN REVISIÓN y se emite el recibo con el total; ampara el traslado recién cuando esté aprobada."
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

            {/* APROBAR PIDE CASILLA: es la FIRMA. Desde acá la guía ampara. */}
            <ConfirmarAccion
                abierto={aprobando}
                tono="afirmativo"
                titulo="Aprobar la guía"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            La <strong>{guia.etiqueta}</strong> de{' '}
                            <strong>{guia.comercializador ?? 'el titular'}</strong> queda APROBADA y
                            ampara el traslado desde este momento.
                        </p>
                        <p>
                            Los días de validez empiezan a correr AHORA, no desde que se cargó el
                            borrador. <strong>No se puede deshacer.</strong>
                        </p>
                    </div>
                }
                confirmacion="Verifiqué las boletas contra el extracto del banco y el expediente está completo."
                textoConfirmar="Aprobar"
                procesando={envio.processing}
                onCancelar={() => setAprobando(false)}
                onConfirmar={() =>
                    envio.patch(route('guias.aprobar', guia.id), {
                        preserveScroll: true,
                        onSuccess: () => setAprobando(false),
                    })
                }
            />

            {/* RECHAZAR PIDE MOTIVO Y CASILLA: es la otra mitad de la firma. */}
            <ConfirmarConMotivo
                abierto={rechazando}
                titulo="Rechazar y devolver a ventanilla"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            La guía vuelve a <strong>PENDIENTE</strong>.
                        </p>
                        <p>
                            Los depósitos quedan intactos y el recibo ya emitido sigue valiendo: se
                            corrige lo observado y se vuelve a presentar.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo del rechazo"
                ayuda="Es lo que va a leer quien tenga que corregirlo. Queda en la auditoría con su nombre."
                placeholder="La boleta 0012345 no figura en el extracto del banco."
                confirmacion="El expediente vuelve a ventanilla con este motivo escrito, y queda registrado a mi nombre."
                textoConfirmar="Rechazar"
                valor={rechazo.data.motivo}
                onCambiar={(v) => rechazo.setData('motivo', v)}
                error={rechazo.errors.motivo}
                procesando={rechazo.processing}
                onCancelar={() => {
                    setRechazando(false);
                    rechazo.reset();
                }}
                onConfirmar={() =>
                    rechazo.patch(route('guias.rechazar', guia.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setRechazando(false);
                            rechazo.reset();
                        },
                    })
                }
            />

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
     * MIENTRAS NADIE LA FIRMÓ, la guía no ampara nada, y el porqué llega
     * RESUELTO del servidor —`motivo_sin_amparar`—. React no vuelve a evaluar
     * el estado: así nacieron los carteles que decían «venció» sobre un
     * expediente que recién se estaba armando.
     */
    if (guia.estado === 'pendiente' || guia.estado === 'en_revision') {
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
                titulo="Venció sin cerrarse"
                texto="Pasaron los días de validez y nadie registró la llegada. Si la carga sigue en camino, está viajando sin amparo."
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
            clase="bg-emerald-50 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200"
            icono={<CheckCheck className="mt-0.5 size-5 shrink-0" />}
            titulo="Cerrada"
            texto="La carga llegó a destino. Esta guía amparó el traslado y ya no se puede anular."
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

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">{etiqueta}</dt>
            <dd className="font-medium tabular-nums">{valor}</dd>
        </div>
    );
}

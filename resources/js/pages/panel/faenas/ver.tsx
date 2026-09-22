import { Head, router, useForm } from '@inertiajs/react';
import { BadgeCheck, CalendarX, Check, CheckCheck, Clock, IdCard, Pencil, Printer, Receipt, Send, Trash2, Undo2, User, Waves } from 'lucide-react';
import { useState } from 'react';
import { BarraSaldo } from '@/components/panel/aprovechamientos/barra-saldo';
import { TarjetaPagos } from '@/components/panel/pagos/tarjeta-pagos';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarAccion } from '@/components/ui/confirmar-accion';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { Input } from '@/components/ui/input';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha } from '@/lib/utils';
import type { PagoDelCupo, ReciboDelCupo } from '@/types/aprovechamientos';
import type { FaenaFicha } from '@/types/faenas';

/**
 *  LA FICHA DE UNA FAENA
 *
 * La salida se cobra y se firma como el carnet y el aprovechamiento —pendiente
 * → en revisión → aprobada—, así que la tarjeta de pagos y los botones del
 * circuito son los mismos componentes.
 */
export default function VerFaena({
    faena,
    pagos,
    recibo,
}: {
    faena: FaenaFicha;
    pagos: PagoDelCupo[];
    recibo: ReciboDelCupo | null;
}) {
    const { puede } = usePermisos();
    const [cerrando, setCerrando] = useState(false);
    const [aprobando, setAprobando] = useState(false);
    const [rechazando, setRechazando] = useState(false);
    const [eliminando, setEliminando] = useState(false);

    const form = useForm({ kilos_extraidos: String(faena.kilos_extraidos) });
    const envio = useForm({});
    const rechazo = useForm({ motivo: '' });
    const borrado = useForm({ motivo: '' });

    return (
        <LayoutPanel
            titulo={faena.etiqueta}
            descripcion={`${faena.beneficiario ?? '—'} · Carnet N° ${faena.carnet_registro ?? '—'}`}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {faena.beneficiario_id !== null && (
                        <Button
                            variant="ver"
                            onClick={() => router.visit(route('beneficiarios.show', faena.beneficiario_id!))}
                        >
                            <User className="size-4" />
                            Ver al beneficiario
                        </Button>
                    )}

                    {faena.carnet_id !== null && (
                        <Button
                            variant="ver"
                            onClick={() => router.visit(route('carnets.show', faena.carnet_id!))}
                        >
                            <IdCard className="size-4" />
                            Ver carnet
                        </Button>
                    )}

                    {faena.cupo && (
                        <Button
                            variant="ver"
                            onClick={() => router.visit(route('aprovechamientos.show', faena.cupo!.id))}
                        >
                            <Waves className="size-4" />
                            Ver autorización
                        </Button>
                    )}

                    {/* CORREGIR Y ELIMINAR SOLO SOBRE EL BORRADOR. Las dos
                        banderas llegan resueltas: miran el estado Y que no
                        haya entrado un peso. */}
                    {puede('faenas.editar') && faena.puede_editarse && (
                        <Button
                            variant="editar"
                            onClick={() => router.visit(route('faenas.edit', faena.id))}
                        >
                            <Pencil className="size-4" />
                            Editar
                        </Button>
                    )}

                    {puede('faenas.eliminar') && faena.puede_eliminarse && (
                        <Button variant="eliminar" onClick={() => setEliminando(true)}>
                            <Trash2 className="size-4" />
                            Eliminar
                        </Button>
                    )}

                    {/*
                        EL CIRCUITO, igual que en el carnet y el cupo: presentar
                        es de ventanilla y firmar es de supervisión. Las tres
                        banderas llegan resueltas del servidor.
                    */}
                    {puede('faenas.enviar') && faena.puede_enviarse && (
                        <Button
                            onClick={() =>
                                envio.post(route('faenas.enviar', faena.id), { preserveScroll: true })
                            }
                            disabled={envio.processing}
                        >
                            <Send className="size-4" />
                            Enviar a revisión
                        </Button>
                    )}

                    {puede('faenas.aprobar') && faena.puede_revisarse && (
                        <>
                            {/* Apagado mientras falte validar alguna boleta, y
                                el title dice cuántas: el servidor lo exige
                                igual, y un botón que promete y falla es peor. */}
                            <Button
                                onClick={() => setAprobando(true)}
                                disabled={envio.processing || !faena.puede_aprobarse}
                                title={
                                    faena.puede_aprobarse
                                        ? undefined
                                        : `Faltan ${faena.pagos_sin_validar} depósito(s) por validar`
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
                        EL PERMISO EN PAPEL sale recién con la faena aprobada:
                        hasta la firma no hay nada que autorizar. `ya_fue_aprobada`
                        llega resuelto del servidor.
                    */}
                    {puede('faenas.imprimir') && faena.ya_fue_aprobada && (
                        <a
                            href={route('faenas.imprimir', faena.id)}
                            target="_blank"
                            rel="noreferrer"
                            className={cn(buttonVariants({ variant: 'outline' }))}
                        >
                            <Printer className="size-4" />
                            Imprimir permiso
                        </a>
                    )}

                    {/* El recibo existe desde el ENVÍO: antes no hay papel. */}
                    {puede('recibos.imprimir') && faena.recibo_id !== null && (
                        <a
                            href={route('recibos.imprimir', faena.recibo_id)}
                            target="_blank"
                            rel="noreferrer"
                            className={cn(buttonVariants({ variant: 'outline' }))}
                        >
                            <Receipt className="size-4" />
                            Imprimir recibo
                        </a>
                    )}

                    {/*
                        `puede_completarse` llega resuelto: exige que la faena
                        esté EN CURSO. Sobre una vencida no se puede — al vencer
                        ya devolvió los kilos, y completarla los volvería a
                        descontar de un cupo que se repuso.
                    */}
                    {puede('faenas.completar') && faena.puede_completarse && (
                        <Button onClick={() => setCerrando((v) => !v)}>
                            <CheckCheck className="size-4" />
                            Registrar la vuelta
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={faena.etiqueta} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {cerrando && faena.puede_completarse && (
                        <Card className="border-emerald-300 bg-emerald-50/50 dark:border-emerald-500/40 dark:bg-emerald-500/5">
                            <CardHeader>
                                <CardTitle>Registrar la vuelta</CardTitle>
                            </CardHeader>

                            <CardContent>
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        form.patch(route('faenas.completar', faena.id), {
                                            preserveScroll: true,
                                            onSuccess: () => setCerrando(false),
                                        });
                                    }}
                                    className="space-y-4"
                                >
                                    <Campo
                                        etiqueta="Kilos descargados"
                                        htmlFor="kilos_extraidos"
                                        error={form.errors.kilos_extraidos}
                                        ayuda="Viene con lo declarado al salir. Corríjalo solo si la balanza dijo otra cosa; hacia arriba se vuelve a comprobar el cupo."
                                        className="max-w-xs"
                                    >
                                        <Input
                                            id="kilos_extraidos"
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            value={form.data.kilos_extraidos}
                                            onChange={(e) => form.setData('kilos_extraidos', e.target.value)}
                                            aria-invalid={Boolean(form.errors.kilos_extraidos)}
                                        />
                                    </Campo>

                                    <div className="flex gap-2">
                                        <Button type="submit" disabled={form.processing}>
                                            Completar faena
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setCerrando(false)}
                                        >
                                            Cancelar
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>La salida</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            <Situacion faena={faena} />

                            <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                                <Dato etiqueta="Kilos" valor={`${faena.kilos_extraidos} kg`} />
                                <Dato etiqueta="Solicitada" valor={fecha(faena.fecha_solicitud)} />
                                <Dato etiqueta="Salida" valor={fecha(faena.fecha_salida)} />
                                {/* La del papel: cuándo vuelve. El límite es el
                                    techo que calcula el sistema. */}
                                <Dato etiqueta="Desembarque" valor={fecha(faena.fecha_desembarque)} />
                                <Dato etiqueta="Límite" valor={fecha(faena.fecha_limite)} />
                                {/* La emisión la escribe la aprobación: hasta
                                    entonces esto es una solicitud. */}
                                <Dato etiqueta="Aprobada" valor={fecha(faena.fecha_emision)} />
                                <Dato etiqueta="Arancel" valor={bs(faena.monto)} />
                                <Dato etiqueta="Asociación" valor={faena.asociacion ?? '—'} />
                                {/* DE QUÉ CARNET CUELGA. El número del libro es
                                    cómo se lo nombra; el código de 16 caracteres
                                    va al lado porque es la llave con la que se
                                    verifica el plástico. */}
                                <Dato
                                    etiqueta="Carnet"
                                    valor={`N° ${faena.carnet_registro ?? '—'}`}
                                />
                                <Dato etiqueta="Código del carnet" valor={faena.carnet_codigo ?? '—'} />
                            </dl>
                        </CardContent>
                    </Card>

                    {/*
                        LOS RENGLONES DEL TALONARIO. Se muestran aunque estén
                        vacíos: el papel los tiene igual, y un hueco acá es la
                        señal de que falta completarlo antes de imprimir.
                    */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Datos del talonario</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                                <Dato etiqueta="La embarcación" valor={faena.embarcacion ?? '—'} />
                                <Dato etiqueta="De propiedad de" valor={faena.propietario ?? '—'} />
                                <Dato
                                    etiqueta="Comandante de barco"
                                    valor={faena.comandante_barco ?? '—'}
                                />
                                <Dato
                                    etiqueta="Matrícula naval"
                                    valor={faena.matricula_naval ?? '—'}
                                />
                                <Dato etiqueta="N° Kardex" valor={faena.nro_kardex ?? '—'} />
                                <Dato
                                    etiqueta="Región"
                                    valor={
                                        faena.region_desde || faena.region_hasta
                                            ? `${faena.region_desde ?? '—'} → ${faena.region_hasta ?? '—'}`
                                            : '—'
                                    }
                                />
                            </dl>
                        </CardContent>
                    </Card>

                    {/* LA MISMA TARJETA que el carnet y el cupo: se cobra igual. */}
                    <TarjetaPagos
                        pagos={pagos}
                        recibo={recibo}
                        saldoPendiente={faena.saldo_pendiente}
                        titular={faena.beneficiario ?? 'el titular'}
                        admitePagos={faena.admite_pagos}
                        rutaPagar={route('faenas.pagar', faena.id)}
                        permisoEnviar="faenas.enviar"
                        textoAlEnviar="La faena pasa a EN REVISIÓN y se emite el recibo con el total; autoriza la salida recién cuando esté aprobada."
                    />
                </div>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Cupo del que salió</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-3 text-sm">
                        {faena.cupo === null ? (
                            <p className="text-muted-foreground">Sin cupo asociado.</p>
                        ) : (
                            <>
                                <Dato etiqueta="Escala" valor={String(faena.cupo.escala ?? '—')} />
                                <BarraSaldo cupo={faena.cupo} />

                                {/*
                                    Se dice explícitamente si ESTA faena está
                                    pesando sobre ese saldo: una vencida no, y sin
                                    la aclaración el número de arriba parece no
                                    cuadrar con la lista de faenas.
                                */}
                                <p className="text-xs text-muted-foreground">
                                    {faena.consume_cupo
                                        ? 'Esta faena está descontando sus kilos del saldo.'
                                        : faena.estado === 'vencido'
                                          ? 'Esta faena venció: sus kilos volvieron al cupo.'
                                          : 'Todavía no descuenta: la bolsa se mueve recién cuando se aprueba.'}
                                </p>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* APROBAR PIDE CASILLA: es la FIRMA. Desde acá la faena autoriza. */}
            <ConfirmarAccion
                abierto={aprobando}
                tono="afirmativo"
                titulo="Aprobar la faena"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            La <strong>{faena.etiqueta}</strong> de{' '}
                            <strong>{faena.beneficiario ?? 'el titular'}</strong> queda APROBADA y
                            autoriza la salida hasta el <strong>{fecha(faena.fecha_limite)}</strong>.
                        </p>
                        <p>
                            <strong>No se puede deshacer.</strong>
                        </p>
                    </div>
                }
                confirmacion="Verifiqué las boletas contra el extracto del banco y el expediente está completo."
                textoConfirmar="Aprobar"
                procesando={envio.processing}
                onCancelar={() => setAprobando(false)}
                onConfirmar={() =>
                    envio.patch(route('faenas.aprobar', faena.id), {
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
                            La faena vuelve a <strong>PENDIENTE</strong>.
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
                    rechazo.patch(route('faenas.rechazar', faena.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setRechazando(false);
                            rechazo.reset();
                        },
                    })
                }
            />

            {/* ELIMINAR: la misma ventana que en el listado. */}
            <ConfirmarConMotivo
                abierto={eliminando}
                titulo="Eliminar este permiso de faena"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            Se da de baja la faena <strong>N° {faena.numero_legible}</strong> de{' '}
                            <strong>{faena.beneficiario ?? 'el pescador'}</strong>:{' '}
                            {faena.kilos_extraidos} kg.
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
                    setEliminando(false);
                    borrado.reset();
                }}
                onConfirmar={() =>
                    borrado.delete(route('faenas.destroy', faena.id), {
                        onSuccess: () => {
                            setEliminando(false);
                            borrado.reset();
                        },
                    })
                }
            />
        </LayoutPanel>
    );
}

/**
 * En qué situación está la salida, en una frase.
 */
function Situacion({ faena }: { faena: FaenaFicha }) {
    /*
     * MIENTRAS NADIE LA FIRMÓ, la faena no autoriza nada, y el porqué llega
     * RESUELTO del servidor —`motivo_sin_autorizar`—. React no vuelve a
     * evaluar el estado: así nacieron los carteles que decían «venció» sobre
     * un expediente que recién se estaba armando.
     */
    if (faena.estado === 'pendiente' || faena.estado === 'en_revision') {
        return (
            <Marco
                clase="bg-sky-50 text-sky-900 dark:bg-sky-500/10 dark:text-sky-200"
                icono={<Clock className="mt-0.5 size-5 shrink-0" />}
                titulo={faena.estado_etiqueta}
                texto={faena.motivo_sin_autorizar ?? 'Todavía no autoriza la salida.'}
            />
        );
    }

    if (faena.caducada) {
        return (
            <Marco
                clase="bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200"
                icono={<CalendarX className="mt-0.5 size-5 shrink-0" />}
                titulo="Venció sin cerrarse"
                texto="El pescador se llevó el papel y nadie registró la vuelta. Sus kilos ya volvieron al cupo; el número del talonario queda ocupado igual."
            />
        );
    }

    if (faena.vigente) {
        return (
            <Marco
                clase="bg-sky-50 text-sky-900 dark:bg-sky-500/10 dark:text-sky-200"
                icono={<BadgeCheck className="mt-0.5 size-5 shrink-0" />}
                titulo="En curso"
                texto={`Autoriza a pescar hasta el ${fecha(faena.fecha_limite)}. Sus kilos ya están descontados del cupo.`}
            />
        );
    }

    return (
        <Marco
            clase="bg-emerald-50 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200"
            icono={<CheckCheck className="mt-0.5 size-5 shrink-0" />}
            titulo={faena.estado_etiqueta}
            texto={
                faena.estado === 'completado'
                    ? 'El pescador volvió y descargó. El volumen quedó firme contra el cupo.'
                    : 'La salida ya no autoriza nada.'
            }
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
    icono: React.ReactNode;
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

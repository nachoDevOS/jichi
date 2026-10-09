import { Head, router, useForm } from '@inertiajs/react';
import { BadgeCheck, Ban, CalendarX, CheckCheck, Clock, IdCard, Pencil, Printer, Receipt, Trash2, User, Waves } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { TextoCopiable } from '@/components/comunes/texto-copiable';
import { Retrato } from '@/components/comunes/retrato';
import { BarraSaldo } from '@/components/panel/aprovechamientos/barra-saldo';
import { TarjetaRecaudaciones } from '@/components/panel/pagos/tarjeta-recaudaciones';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha } from '@/lib/utils';
import type { ReciboDelCupo } from '@/types/aprovechamientos';
import type { FaenaFicha } from '@/types/faenas';
import { EnlacePermitido } from '@/components/panel/comunes/enlace-permitido';

/**
 *  La ficha de una faena
 *
 * La salida se paga en SIREB como el carnet y la autorización —pendiente →
 * aprobada—, así que la tarjeta de pagos es la misma.
 */
export default function VerFaena({
    faena,
    recibo,
}: {
    faena: FaenaFicha;
    recibo: ReciboDelCupo | null;
}) {
    const { puede } = usePermisos();
    const [eliminando, setEliminando] = useState(false);
    const [revocando, setRevocando] = useState(false);

    const borrado = useForm({ motivo: '' });
    const baja = useForm({ motivo: '' });

    return (
        <LayoutPanel
            titulo={faena.etiqueta}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {puede('beneficiarios.ver') && faena.beneficiario_id !== null && (
                        <Button
                            variant="ver"
                            onClick={() => router.visit(route('beneficiarios.show', faena.beneficiario_id!))}
                        >
                            <User className="size-4" />
                            Beneficiario
                        </Button>
                    )}

                    {puede('carnets.ver') && faena.carnet_id !== null && (
                        <Button
                            variant="ver"
                            onClick={() => router.visit(route('carnets.show', faena.carnet_id!))}
                        >
                            <IdCard className="size-4" />
                            Ver carnet
                        </Button>
                    )}

                    {puede('aprovechamientos.ver') && faena.cupo && (
                        <Button
                            variant="ver"
                            onClick={() => router.visit(route('aprovechamientos.show', faena.cupo!.id))}
                        >
                            <Waves className="size-4" />
                            Autorización
                        </Button>
                    )}

                    {/* Corregir y eliminar solo sobre el borrador. Las dos
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

                    {puede('faenas.revocar') && faena.puede_revocarse && (
                        <Button variant="eliminar" onClick={() => setRevocando(true)}>
                            <Ban className="size-4" />
                            Revocar
                        </Button>
                    )}



                    {/*
                        EL PERMISO EN PAPEL sale recién con la faena aprobada:
                        hasta la firma no hay nada que autorizar. `ya_fue_aprobada`
                        llega resuelto del servidor.
                    */}
                    {puede('faenas.imprimir') && faena.puede_imprimirse && (
                        <a
                            href={route('faenas.imprimir', faena.id)}
                            target="_blank"
                            rel="noreferrer"
                            className={cn(buttonVariants({ variant: 'outline' }))}
                        >
                            <Printer className="size-4" />
                            Faena
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
                            Recibo
                        </a>
                    )}
                </div>
            }
        >
            <Head title={faena.etiqueta} />

            {/* El titular, con su foto: mismo bloque que la autorización de pesca. */}
            <Card className="mb-6 min-w-0">
                <CardContent className="flex flex-wrap items-center gap-4 p-4">
                    <Retrato url={faena.foto_url} nombre={faena.beneficiario ?? 'Sin nombre'} className="size-16" />

                    <div className="min-w-0">
                        {faena.beneficiario_id !== null ? (
                            <EnlacePermitido
                                permiso="beneficiarios.ver"
                                href={route('beneficiarios.show', faena.beneficiario_id)}
                                className="text-lg font-semibold text-primary hover:underline"
                            >
                                {faena.beneficiario ?? '—'}
                            </EnlacePermitido>
                        ) : (
                            <p className="text-lg font-semibold">{faena.beneficiario ?? '—'}</p>
                        )}

                        <p className="tabular-nums text-sm text-muted-foreground">C.I. {faena.documento ?? '—'}</p>
                    </div>
                </CardContent>
            </Card>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>La salida</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            <Situacion faena={faena} />

                            <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                                <Dato etiqueta="Kilos" valor={`${faena.kilos_extraidos} kg`} />
                                <Dato etiqueta="Asociación" valor={faena.asociacion ?? '—'} />
                                {/* De qué carnet cuelga. El número del libro es
                                    cómo se lo nombra; el código de 16 caracteres
                                    va al lado porque es la llave con la que se
                                    verifica el plástico. */}
                                <Dato
                                    etiqueta="Carnet"
                                    valor={`N° ${faena.carnet_registro ?? '—'}`}
                                />
                                <Dato
                                    etiqueta="Código del carnet"
                                    valor={faena.carnet_codigo ? <TextoCopiable texto={faena.carnet_codigo} className="-ml-1.5" /> : '—'}
                                />
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
                </div>

                <div className="space-y-6">
                    {/* Mismo bloque lateral que la ficha del carnet y la del cupo. */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Situación</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-3 text-sm">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-muted-foreground">Estado</span>
                                <Badge color={faena.estado_color}>{faena.estado_etiqueta}</Badge>
                            </div>

                            {/* La llave del QR del permiso impreso. */}
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">Código</span>
                                {faena.codigo ? <TextoCopiable texto={faena.codigo} className="-mr-1.5" /> : <span>—</span>}
                            </div>

                            <Renglon etiqueta="N° de faena" valor={faena.numero_legible} />
                            <Renglon etiqueta="Solicitada el" valor={fecha(faena.fecha_solicitud)} />
                            {/* Las dos las escribe la aprobación: en «—» hasta la firma. */}
                            <Renglon etiqueta="Salida el" valor={fecha(faena.fecha_salida)} />
                            <Renglon etiqueta="Desembarque el" valor={fecha(faena.fecha_desembarque)} />
                            <Renglon etiqueta="Arancel" valor={bs(faena.monto)} destacado />

                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Cupo del que salió</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-3 text-sm">
                            {faena.cupo === null ? (
                                <p className="text-muted-foreground">Sin cupo asociado.</p>
                            ) : (
                                <>
                                    <BarraSaldo cupo={faena.cupo} />

                                    <Renglon etiqueta="Otorgado" valor={`${faena.cupo.volumen_total_kg} kg`} />
                                    <Renglon etiqueta="Disponible hoy" valor={`${faena.cupo.saldo_kg} kg`} />
                                    <Renglon etiqueta="Esta faena" valor={`${faena.kilos_extraidos} kg`} />
                                    {/* Sin firmar todavía: cuánto quedaría si se aprueba. */}
                                    {faena.estado === 'pendiente' && (
                                        <Renglon
                                            etiqueta="Queda al aprobar"
                                            valor={`${Math.max(0, Math.round((faena.cupo.saldo_kg - faena.kilos_extraidos) * 100) / 100)} kg`}
                                        />
                                    )}

                                    {/*
                                        Se dice explícitamente si ESTA faena está
                                        pesando sobre ese saldo: una vencida no, y sin
                                        la aclaración el número de arriba parece no
                                        cuadrar con la lista de faenas.
                                    */}
                                    <p className="text-xs text-muted-foreground">
                                        {faena.estado === 'revocado'
                                            ? 'Esta faena fue revocada: ya no vale, pero sus kilos siguen descontados del saldo.'
                                            : faena.consume_cupo
                                              ? 'Esta faena está descontando sus kilos del saldo.'
                                              : 'Todavía no descuenta: la bolsa se mueve recién cuando se aprueba.'}
                                    </p>
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* El cobro: se paga en SIREB, igual que los otros tres documentos. */}
                <TarjetaRecaudaciones
                    className="lg:col-span-3"
                    monto={faena.monto}
                    pago={{
                        concepto: 'Permiso de faena',
                        numero: faena.numero_legible,
                        titular: faena.beneficiario,
                        documento: faena.documento,
                        detalle: `${faena.kilos_extraidos} kg`,
                    }}
                    sireb={faena.sireb}
                    recibo={recibo}
                    puedeVerificar={faena.puede_verificar_pago}
                    rutaVerificar={route('faenas.verificar-pago', faena.id)}
                    puedeCargar={faena.puede_cargar_pago}
                    rutaCargar={route('faenas.cargar-pago', faena.id)}
                    rutaConsultarQr={route('faenas.consultar-qr', faena.id)}
                    rutaRenovar={route('faenas.renovar-liquidacion', faena.id)}
                    documento="faenas"
                />
            </div>



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

            {/* REVOCAR: sobre una faena YA APROBADA. No es lo mismo que eliminar. */}
            <ConfirmarConMotivo
                abierto={revocando}
                titulo="¿Revocar el permiso de faena?"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            La faena <strong>N° {faena.numero_legible}</strong> deja de autorizar la salida al
                            instante. Sus {faena.kilos_extraidos} kg <strong>siguen descontados</strong> del cupo: no
                            vuelven a la autorización.
                        </p>
                        <p>Queda en el historial como revocada y no vuelve atrás. Si hace falta, se emite otra.</p>
                    </div>
                }
                etiquetaMotivo="Motivo de la revocación"
                ayuda="Queda en la auditoría con su nombre."
                placeholder="El pescador incumplió la veda; se le retira el permiso de esta salida."
                textoConfirmar="Revocar faena"
                confirmacion="Entiendo que la faena deja de valer y que esto no se puede revertir."
                valor={baja.data.motivo}
                onCambiar={(v) => baja.setData('motivo', v)}
                error={baja.errors.motivo}
                procesando={baja.processing}
                onCancelar={() => {
                    setRevocando(false);
                    baja.reset();
                }}
                onConfirmar={() =>
                    baja.patch(route('faenas.revocar', faena.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setRevocando(false);
                            baja.reset();
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
     * Mientras nadie la firmó, la faena no autoriza nada, y el porqué llega
     * RESUELTO del servidor —`motivo_sin_autorizar`—. React no vuelve a
     * evaluar el estado: así nacieron los carteles que decían «venció» sobre
     * un expediente que recién se estaba armando.
     */
    if (faena.estado === 'pendiente') {
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
                texto="El pescador se llevó el papel y nadie registró la vuelta. Sus kilos siguen descontados del cupo; el número del talonario queda ocupado igual."
            />
        );
    }

    if (faena.vigente) {
        return (
            <Marco
                clase="bg-sky-50 text-sky-900 dark:bg-sky-500/10 dark:text-sky-200"
                icono={<BadgeCheck className="mt-0.5 size-5 shrink-0" />}
                titulo="En curso"
                texto={`Autoriza a pescar hasta el ${fecha(faena.fecha_desembarque)}. Sus kilos ya están descontados del cupo.`}
            />
        );
    }

    if (faena.estado === 'revocado') {
        return (
            <Marco
                clase="bg-rose-50 text-rose-900 dark:bg-rose-500/10 dark:text-rose-200"
                icono={<CalendarX className="mt-0.5 size-5 shrink-0" />}
                titulo="Revocada"
                texto={faena.motivo_sin_autorizar ?? 'Ya no autoriza la salida.'}
            />
        );
    }

    return (
        <Marco
            clase="bg-emerald-50 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200"
            icono={<CheckCheck className="mt-0.5 size-5 shrink-0" />}
            titulo={faena.estado_etiqueta}
            texto="La salida ya no autoriza nada."
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

/** Renglón «etiqueta ····· valor» de la columna lateral. */
function Renglon({ etiqueta, valor, destacado = false }: { etiqueta: string; valor: string; destacado?: boolean }) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <span className="text-muted-foreground">{etiqueta}</span>
            <span className={destacado ? 'text-right text-base font-medium tabular-nums' : 'text-right font-medium tabular-nums'}>{valor}</span>
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

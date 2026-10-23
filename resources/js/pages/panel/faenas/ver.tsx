import { Head, router, useForm } from '@inertiajs/react';
import {
    Anchor,
    BadgeCheck,
    Ban,
    CalendarClock,
    CalendarX,
    CheckCheck,
    Clock,
    Fish,
    IdCard,
    MapPin,
    Pencil,
    Printer,
    Receipt,
    Ship,
    Trash2,
    User,
    Wallet,
    Waves,
} from 'lucide-react';
import { useState } from 'react';
import {
    Aviso,
    avisoVencimiento,
    BandaTitular,
    BarraDisponible,
    Chip,
    Cifra,
    Conteo,
    EnlacePdf,
    EnlaceVer,
    kg,
    LineaTiempo,
    nivelDisponible,
    porcentaje,
    TarjetaCodigo,
} from '@/components/panel/comunes/ficha';
import { EstadoPdf, type PdfEmitido } from '@/components/panel/comunes/pdf-emitido';
import { TarjetaRecaudaciones } from '@/components/panel/pagos/tarjeta-recaudaciones';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { ReciboDelCupo } from '@/types/aprovechamientos';
import type { FaenaFicha } from '@/types/faenas';

/**
 *  La ficha de una faena: se paga en SIREB como los otros tres trámites.
 */
export default function VerFaena({
    faena,
    recibo,
    pdf,
    pdf_recibo,
}: {
    faena: FaenaFicha;
    recibo: ReciboDelCupo | null;
    pdf: PdfEmitido | null;
    pdf_recibo: PdfEmitido | null;
}) {
    const { puede } = usePermisos();
    const [eliminando, setEliminando] = useState(false);
    const [revocando, setRevocando] = useState(false);
    const borrado = useForm({ motivo: '' });
    const baja = useForm({ motivo: '' });

    const desembarque = avisoVencimiento(faena.dias_para_desembarque, faena.ya_fue_aprobada, 5);
    const cupo = faena.cupo;
    const nivel = cupo
        ? nivelDisponible(porcentaje(cupo.kilos_disponibles, cupo.volumen_total_kg), cupo.ya_fue_aprobado)
        : null;
    const region =
        faena.region_desde || faena.region_hasta ? `${faena.region_desde ?? '—'} → ${faena.region_hasta ?? '—'}` : null;

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
                    {puede('carnets.ver') && (
                        <Button variant="ver" onClick={() => router.visit(route('carnets.show', faena.carnet_id))}>
                            <IdCard className="size-4" />
                            Carnet
                        </Button>
                    )}
                    {puede('faenas.imprimir') && faena.puede_imprimirse && (
                        <EnlacePdf href={route('faenas.imprimir', faena.id)} directo={pdf?.enlace} icono={Printer}>
                            Faena
                        </EnlacePdf>
                    )}
                    {puede('recibos.imprimir') && faena.recibo_id !== null && (
                        <EnlacePdf
                            href={route('recibos.imprimir', faena.recibo_id)}
                            directo={pdf_recibo?.enlace}
                            icono={Receipt}
                        >
                            Recibo
                        </EnlacePdf>
                    )}
                    {puede('faenas.editar') && faena.puede_editarse && (
                        <Button variant="editar" onClick={() => router.visit(route('faenas.edit', faena.id))}>
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
                </div>
            }
        >
            <Head title={faena.etiqueta} />

            <BandaTitular
                foto={faena.foto_url}
                nombre={faena.beneficiario}
                beneficiarioId={faena.beneficiario_id}
                linea={`C.I. ${faena.documento ?? '—'} · Faena N° ${faena.numero_legible}`}
                estado={{ color: faena.estado_color, etiqueta: faena.estado_etiqueta }}
            >
                {faena.embarcacion && <Chip icono={Ship}>{faena.embarcacion}</Chip>}
                {region && <Chip icono={MapPin}>{region}</Chip>}
                {faena.fecha_desembarque && (
                    <Chip icono={CalendarClock} tono={desembarque.tono}>
                        Desembarque {fecha(faena.fecha_desembarque)}
                        {desembarque.texto && <span className="font-semibold">· {desembarque.texto}</span>}
                    </Chip>
                )}
            </BandaTitular>

            <div className="mt-4">
                <Situacion faena={faena} />
            </div>

            <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Cifra
                    icono={Fish}
                    etiqueta="Kilos de la salida"
                    valor={<Conteo valor={faena.kilos_extraidos} formato={kg} />}
                    pie="Lo disponible al emitirla"
                    destacada
                />
                {cupo ? (
                    <Cifra
                        icono={Waves}
                        etiqueta="Disponible hoy"
                        valor={<Conteo valor={cupo.kilos_disponibles} formato={kg} />}
                        pie={`de ${kg(cupo.volumen_total_kg)}${nivel?.texto ? ` · ${nivel.texto}` : ''}`}
                        tono={nivel?.tono}
                    />
                ) : (
                    <Cifra icono={Waves} etiqueta="Disponible hoy" valor="—" pie="Sin autorización asociada" />
                )}
                <Cifra
                    icono={IdCard}
                    etiqueta="Carnet"
                    valor={`N° ${faena.carnet_registro ?? '—'}`}
                    pie={faena.asociacion ?? 'Sin asociación'}
                />
                <Cifra
                    icono={Wallet}
                    etiqueta="Arancel"
                    valor={<Conteo valor={faena.monto} formato={(n) => bs(n)} />}
                    pie={faena.recibo_numero ? `Recibo N° ${faena.recibo_numero}` : 'Sin recibo todavía'}
                />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <Card className="min-w-0 lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Datos del talonario</CardTitle>
                        <p className="text-sm text-muted-foreground">Lo que va impreso en el permiso por faena.</p>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
                            <Dato icono={Ship} etiqueta="Embarcación" valor={faena.embarcacion} />
                            <Dato icono={User} etiqueta="De propiedad de" valor={faena.propietario} />
                            <Dato icono={Anchor} etiqueta="Comandante" valor={faena.comandante_barco} />
                            <Dato icono={BadgeCheck} etiqueta="Matrícula naval" valor={faena.matricula_naval} />
                            <Dato icono={IdCard} etiqueta="N° Kardex" valor={faena.nro_kardex} />
                            <Dato icono={MapPin} etiqueta="Región" valor={region} />
                        </dl>

                        {cupo && (
                            <div className="space-y-3 rounded-xl border border-border p-4">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Waves className="size-4 shrink-0 text-primary" />
                                    <span className="text-sm font-medium">Autorización de la que salió</span>
                                    <span className="text-sm text-muted-foreground">{cupo.descripcion ?? ''}</span>
                                    {puede('aprovechamientos.ver') && (
                                        <span className="ml-auto">
                                            <EnlaceVer href={route('aprovechamientos.show', cupo.id)} />
                                        </span>
                                    )}
                                </div>
                                <BarraDisponible
                                    disponible={cupo.kilos_disponibles}
                                    total={cupo.volumen_total_kg}
                                    aprobada={cupo.ya_fue_aprobado}
                                />
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>El permiso</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5 text-sm">
                        <TarjetaCodigo
                            titulo="Código del permiso"
                            codigo={faena.codigo}
                            numero={faena.numero_legible}
                            ayuda="Va en el QR del permiso. Con él un control comprueba en el río si la salida está autorizada."
                        />

                        {/* La salida y el desembarque los escribe la aprobación. */}
                        <LineaTiempo
                            hitos={[
                                { etiqueta: 'Solicitada', fecha: faena.fecha_solicitud, hecho: true },
                                { etiqueta: 'Salida', fecha: faena.fecha_salida, hecho: faena.fecha_salida !== null },
                                {
                                    etiqueta: 'Desembarque',
                                    fecha: faena.fecha_desembarque,
                                    hecho: (faena.dias_para_desembarque ?? 0) < 0,
                                },
                            ]}
                        />

                        <EstadoPdf pdf={pdf} aprobado={faena.ya_fue_aprobada} />
                    </CardContent>
                </Card>
            </div>

            <TarjetaRecaudaciones
                reciboDirecto={pdf_recibo?.enlace}
                className="mt-6"
                monto={faena.monto}
                pago={{
                    concepto: 'Permiso de faena',
                    numero: faena.numero_legible,
                    titular: faena.beneficiario,
                    documento: faena.documento,
                    detalle: kg(faena.kilos_extraidos),
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

            <ConfirmarConMotivo
                abierto={eliminando}
                titulo="Eliminar este permiso de faena"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            Se da de baja la faena <strong>N° {faena.numero_legible}</strong> de{' '}
                            <strong>{faena.beneficiario ?? 'el pescador'}</strong>: {kg(faena.kilos_extraidos)}.
                        </p>
                        <p>
                            Solo se puede porque está <strong>pendiente de pago</strong>. El número del talonario{' '}
                            <strong>no se reutiliza</strong>.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la eliminación"
                ayuda="Queda en la auditoría con su nombre: es lo que explica el hueco en la serie."
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

            <ConfirmarConMotivo
                abierto={revocando}
                titulo="¿Revocar el permiso de faena?"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            La faena <strong>N° {faena.numero_legible}</strong> deja de autorizar la salida al instante.
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

/** En qué situación está la salida. El porqué de un pendiente llega resuelto del servidor. */
function Situacion({ faena }: { faena: FaenaFicha }) {
    if (faena.estado === 'pendiente') {
        return (
            <Aviso tono="info" icono={Clock} titulo="Pendiente de pago">
                {faena.motivo_sin_autorizar ?? 'Todavía no autoriza la salida.'}
            </Aviso>
        );
    }
    if (faena.estado === 'revocado') {
        return (
            <Aviso tono="peligro" icono={Ban} titulo="Revocada">
                {faena.motivo_sin_autorizar ?? 'Ya no autoriza la salida.'}
            </Aviso>
        );
    }
    if (faena.caducada) {
        return (
            <Aviso tono="aviso" icono={CalendarX} titulo="Desembarque cumplido">
                Pasó la fecha de desembarque: el permiso ya no autoriza a pescar.
            </Aviso>
        );
    }
    if (faena.vigente) {
        return (
            <Aviso tono="ok" icono={CheckCheck} titulo="En curso">
                Autoriza a pescar hasta el {fecha(faena.fecha_desembarque)}.
            </Aviso>
        );
    }

    return (
        <Aviso tono="peligro" icono={Ban} titulo={faena.estado_etiqueta}>
            {faena.motivo_sin_autorizar ?? 'La salida no está autorizada.'}
        </Aviso>
    );
}

function Dato({ icono: Icono, etiqueta, valor }: { icono: typeof Ship; etiqueta: string; valor: string | null }) {
    return (
        <div className="flex min-w-0 items-start gap-2.5">
            <Icono className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div className="min-w-0">
                <dt className="text-xs tracking-wide text-muted-foreground uppercase">{etiqueta}</dt>
                <dd className={valor ? 'truncate font-medium' : 'text-muted-foreground'}>{valor ?? '—'}</dd>
            </div>
        </div>
    );
}

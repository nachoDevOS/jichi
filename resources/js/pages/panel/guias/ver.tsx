import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Ban,
    CalendarClock,
    CalendarX,
    Clock,
    Fish,
    Hourglass,
    IdCard,
    MapPin,
    Package,
    Pencil,
    Printer,
    Receipt,
    Trash2,
    Truck,
    User,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import {
    Aviso,
    BandaTitular,
    Chip,
    Cifra,
    Conteo,
    EnlacePdf,
    EnlaceVer,
    kg,
    LineaTiempo,
    Tabla,
    TarjetaCodigo,
    Td,
    type TonoChip,
} from '@/components/panel/comunes/ficha';
import { EstadoPdf, type PdfEmitido } from '@/components/panel/comunes/pdf-emitido';
import { TarjetaRecaudaciones } from '@/components/panel/pagos/tarjeta-recaudaciones';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fechaHora } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { ReciboDelCupo } from '@/types/aprovechamientos';
import type { GuiaFicha } from '@/types/guias';

/**
 *  La ficha de una guía: se paga en SIREB como los otros tres trámites.
 */
export default function VerGuia({
    guia,
    recibo,
    pdf,
    pdf_recibo,
}: {
    guia: GuiaFicha;
    recibo: ReciboDelCupo | null;
    pdf: PdfEmitido | null;
    pdf_recibo: PdfEmitido | null;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [eliminando, setEliminando] = useState(false);
    const [revocando, setRevocando] = useState(false);
    const borrado = useForm({ motivo: '' });
    const baja = useForm({ motivo: '' });

    // La guía vale por horas: ámbar el último día, rojo vencida.
    const tonoVence: TonoChip = guia.caducada
        ? 'vencido'
        : guia.vigente && (guia.horas_restantes ?? 99) <= 24
          ? 'aviso'
          : 'normal';
    const importe = guia.detalles.reduce((suma, d) => suma + d.importe_total, 0);
    const vehiculo = [guia.tipo_transporte_etiqueta, guia.transporte_placa].filter(Boolean).join(' · ');

    return (
        <LayoutPanel
            titulo={guia.etiqueta}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {puede('beneficiarios.ver') && guia.beneficiario_id !== null && (
                        <Button
                            variant="ver"
                            onClick={() => router.visit(route('beneficiarios.show', guia.beneficiario_id!))}
                        >
                            <User className="size-4" />
                            Beneficiario
                        </Button>
                    )}
                    {puede('carnets.ver') && guia.carnet_id !== null && (
                        <Button variant="ver" onClick={() => router.visit(route('carnets.show', guia.carnet_id!))}>
                            <IdCard className="size-4" />
                            Carnet
                        </Button>
                    )}
                    {puede('guias.imprimir') && guia.ya_fue_aprobada && (
                        <EnlacePdf href={route('guias.imprimir', guia.id)} directo={pdf?.enlace} icono={Printer}>
                            Guía
                        </EnlacePdf>
                    )}
                    {puede('recibos.imprimir') && guia.recibo_id !== null && (
                        <EnlacePdf
                            href={route('recibos.imprimir', guia.recibo_id)}
                            directo={pdf_recibo?.enlace}
                            icono={Receipt}
                        >
                            Recibo
                        </EnlacePdf>
                    )}
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
                    {puede('guias.revocar') && guia.puede_revocarse && (
                        <Button variant="eliminar" onClick={() => setRevocando(true)}>
                            <Ban className="size-4" />
                            Revocar
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={guia.etiqueta} />

            <BandaTitular
                foto={guia.foto_url}
                nombre={guia.comercializador}
                beneficiarioId={guia.beneficiario_id}
                linea={`C.I. ${guia.documento ?? '—'} · Guía N° ${guia.numero_legible}`}
                estado={{ color: guia.estado_color, etiqueta: guia.estado_etiqueta }}
            >
                <Chip icono={MapPin}>{guia.ruta}</Chip>
                {vehiculo && <Chip icono={Truck}>{vehiculo}</Chip>}
                {guia.fecha_vencimiento && (
                    <Chip icono={CalendarClock} tono={tonoVence}>
                        Vence {fechaHora(guia.fecha_vencimiento)}
                        {guia.vigente && guia.horas_restantes !== null && (
                            <span className="font-semibold">· quedan {guia.horas_restantes} h</span>
                        )}
                        {guia.caducada && <span className="font-semibold">· vencida</span>}
                    </Chip>
                )}
            </BandaTitular>

            <div className="mt-4">
                <Situacion guia={guia} />
            </div>

            <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Cifra
                    icono={Package}
                    etiqueta="Carga"
                    valor={<Conteo valor={guia.peso_total_kg} formato={kg} />}
                    pie={`${guia.detalles.length} ${guia.detalles.length === 1 ? 'producto' : 'productos'}`}
                    destacada
                />
                <Cifra
                    icono={Fish}
                    etiqueta="Pescado de"
                    valor={
                        // Un nombre largo baja a dos líneas: cortado no se sabe de quién es el pescado.
                        <span className="line-clamp-2 block text-lg leading-tight whitespace-normal">
                            {guia.procedencia?.pescador ?? (guia.es_piscicultura ? 'Piscicultura' : '—')}
                        </span>
                    }
                    pie={guia.procedencia ? `Autorización N° ${guia.procedencia.numero}` : 'Sin pescador declarado'}
                />
                <Cifra
                    icono={Hourglass}
                    etiqueta="Validez"
                    valor={guia.vigente && guia.horas_restantes !== null ? `${guia.horas_restantes} h` : '—'}
                    pie={
                        guia.vigente
                            ? 'Quedan para el traslado'
                            : guia.ya_fue_aprobada
                              ? 'Ya no ampara el traslado'
                              : 'Corre desde la aprobación'
                    }
                    tono={tonoVence === 'aviso' ? 'aviso' : 'normal'}
                />
                <Cifra
                    icono={Wallet}
                    etiqueta="Arancel"
                    valor={<Conteo valor={guia.monto} formato={(n) => bs(n, institucion.moneda)} />}
                    pie={guia.recibo_numero ? `Recibo N° ${guia.recibo_numero}` : 'Sin recibo todavía'}
                />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <div className="min-w-0 space-y-6 lg:col-span-2">
                    {/* El cuadro D: lo que un control lee en la ruta. */}
                    <Card className="min-w-0 overflow-hidden">
                        <CardHeader className="border-b border-border">
                            <CardTitle className="flex items-center gap-2">
                                <Package className="size-4 text-primary" />
                                Productos hidrobiológicos
                            </CardTitle>
                        </CardHeader>
                        <Tabla
                            columnas={['Especie', 'Condición', '>Kilos', '>Bs/kg', '>Importe']}
                            pie={
                                <tr>
                                    <Td colSpan={2}>Totales</Td>
                                    <Td derecha>{kg(guia.peso_total_kg)}</Td>
                                    <Td />
                                    <Td derecha>{bs(importe, institucion.moneda)}</Td>
                                </tr>
                            }
                        >
                            {guia.detalles.map((d) => (
                                <tr key={d.id} className="hover:bg-secondary/50">
                                    <Td>
                                        <span className="font-medium">{d.especie}</span>
                                    </Td>
                                    <Td apagada>{d.condicion_etiqueta}</Td>
                                    <Td derecha>{kg(d.cantidad_kg)}</Td>
                                    <Td derecha>{d.precio_kg.toFixed(2)}</Td>
                                    <Td derecha>{bs(d.importe_total, institucion.moneda)}</Td>
                                </tr>
                            ))}
                        </Tabla>
                        {guia.es_piscicultura && (
                            <p className="border-t border-border px-5 py-3 text-xs text-muted-foreground">
                                Producto de <strong>piscicultura</strong>: el arancel se cobró al{' '}
                                {Math.round(guia.factor_arancel * 100)}%.
                            </p>
                        )}
                    </Card>

                    <Card className="min-w-0">
                        <CardHeader>
                            <CardTitle>Ruta y transporte</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-5 text-sm">
                            <div className="grid items-center gap-3 sm:grid-cols-[1fr_auto_1fr]">
                                <Lugar
                                    titulo="Origen"
                                    lugar={guia.origen}
                                    detalle={[guia.origen_provincia, guia.origen_departamento, guia.origen_distrito]}
                                />
                                <ArrowRight className="mx-auto size-5 rotate-90 text-primary sm:rotate-0" />
                                <Lugar
                                    titulo="Destino"
                                    lugar={guia.destino}
                                    detalle={[guia.destino_provincia, guia.destino_departamento, guia.destino_distrito]}
                                />
                            </div>

                            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 border-t border-border pt-4 sm:grid-cols-3">
                                <Dato etiqueta="Medio" valor={guia.medio_transporte_etiqueta} />
                                <Dato etiqueta="Vehículo" valor={guia.tipo_transporte_etiqueta} />
                                <Dato etiqueta="Nombre o tipo" valor={guia.transporte_nombre} />
                                <Dato etiqueta="Placa" valor={guia.transporte_placa} />
                                <Dato
                                    etiqueta="Capacidad máxima"
                                    valor={
                                        guia.transporte_capacidad_kg !== null ? kg(guia.transporte_capacidad_kg) : null
                                    }
                                />
                                <Dato etiqueta="Asociación" valor={guia.asociacion_nombre ?? guia.asociacion} />
                            </dl>

                            {guia.observaciones && (
                                <div className="border-t border-border pt-4">
                                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                        Observaciones
                                    </p>
                                    <p className="whitespace-pre-line">{guia.observaciones}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>La guía</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5 text-sm">
                        <TarjetaCodigo
                            titulo="Código de la guía"
                            codigo={guia.codigo}
                            numero={guia.numero_legible}
                            ayuda="Va en el QR de la guía. Con él un control en la ruta comprueba si el traslado está amparado."
                        />

                        <LineaTiempo
                            hitos={[
                                { etiqueta: 'Solicitada', fecha: guia.fecha_solicitud, hecho: true },
                                {
                                    etiqueta: 'Aprobada',
                                    fecha: guia.fecha_emision,
                                    hecho: guia.fecha_emision !== null,
                                    conHora: true,
                                },
                                {
                                    etiqueta: 'Vence',
                                    fecha: guia.fecha_vencimiento,
                                    hecho: guia.caducada,
                                    conHora: true,
                                },
                            ]}
                        />

                        <EstadoPdf pdf={pdf} aprobado={guia.ya_fue_aprobada} />

                        {/* De quién es el pescado: sus kilos se descontaron de esa autorización. */}
                        {guia.procedencia && (
                            <div className="space-y-1 rounded-xl border border-border p-4">
                                <p className="text-xs tracking-wide text-muted-foreground uppercase">Pescado de</p>
                                <p className="font-medium">{guia.procedencia.pescador ?? '—'}</p>
                                <p className="text-xs text-muted-foreground">
                                    C.I. {guia.procedencia.documento ?? '—'} · Autorización N° {guia.procedencia.numero}
                                    {guia.procedencia.escala ? ` · ${guia.procedencia.escala}` : ''}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Descontó {kg(guia.peso_total_kg)} de su cupo.
                                </p>
                                {puede('aprovechamientos.ver') && (
                                    <div className="pt-1">
                                        <EnlaceVer
                                            href={route('aprovechamientos.show', guia.procedencia.aprovechamiento_id)}
                                        >
                                            Ver autorización
                                        </EnlaceVer>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <TarjetaRecaudaciones
                reciboDirecto={pdf_recibo?.enlace}
                className="mt-6"
                monto={guia.monto}
                pago={{
                    concepto: 'Guía única de transporte',
                    numero: guia.numero_legible,
                    titular: guia.comercializador,
                    documento: guia.documento,
                    detalle: `${kg(guia.peso_total_kg)} · ${guia.ruta}`,
                }}
                sireb={guia.sireb}
                recibo={recibo}
                puedeVerificar={guia.puede_verificar_pago}
                rutaVerificar={route('guias.verificar-pago', guia.id)}
                puedeCargar={guia.puede_cargar_pago}
                rutaCargar={route('guias.cargar-pago', guia.id)}
                rutaConsultarQr={route('guias.consultar-qr', guia.id)}
                rutaRenovar={route('guias.renovar-liquidacion', guia.id)}
                documento="guias"
            />

            <ConfirmarConMotivo
                abierto={eliminando}
                titulo="Eliminar esta guía"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            Se da de baja la guía <strong>N° {guia.numero_legible}</strong> de{' '}
                            <strong>{guia.comercializador ?? 'el comercializador'}</strong>: {kg(guia.peso_total_kg)},{' '}
                            {guia.ruta}.
                        </p>
                        <p>
                            Solo se puede porque está <strong>pendiente de pago</strong>. Sus kilos vuelven al pescador
                            y el número del talonario <strong>no se reutiliza</strong>.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la eliminación"
                ayuda="Queda en la auditoría con su nombre: es lo que explica el hueco en la serie."
                placeholder="Cargada por error: el traslado corresponde a otro comercializador."
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

            <ConfirmarConMotivo
                abierto={revocando}
                titulo="¿Revocar la guía?"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El papel deja de amparar el traslado al instante. El número{' '}
                            <strong>{guia.numero_legible}</strong> queda ocupado para siempre.
                        </p>
                        <p>Los kilos no vuelven al pescador: el pescado ya viajó. No se deshace.</p>
                    </div>
                }
                etiquetaMotivo="Motivo de la revocación"
                ayuda="Queda en la auditoría con su nombre."
                placeholder="Se emitió con el destino equivocado; se reemplaza por la guía N° 000310."
                textoConfirmar="Revocar guía"
                confirmacion="Entiendo que el número queda quemado y que esto no se puede revertir."
                valor={baja.data.motivo}
                onCambiar={(v) => baja.setData('motivo', v)}
                error={baja.errors.motivo}
                procesando={baja.processing}
                onCancelar={() => {
                    setRevocando(false);
                    baja.reset();
                }}
                onConfirmar={() =>
                    baja.patch(route('guias.revocar', guia.id), {
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

/** En qué situación está el traslado. El porqué de un pendiente llega resuelto del servidor. */
function Situacion({ guia }: { guia: GuiaFicha }) {
    if (guia.estado === 'pendiente') {
        return (
            <Aviso tono="info" icono={Clock} titulo="Pendiente de pago">
                {guia.motivo_sin_amparar ?? 'Todavía no ampara el traslado.'}
            </Aviso>
        );
    }
    if (guia.estado === 'revocado') {
        return (
            <Aviso tono="peligro" icono={Ban} titulo="Revocada">
                Este papel no ampara ningún traslado. El número queda ocupado.
            </Aviso>
        );
    }
    if (guia.caducada) {
        return (
            <Aviso tono="aviso" icono={CalendarX} titulo="Vencida">
                Pasó su plazo: si la carga sigue en camino, necesita una guía nueva.
            </Aviso>
        );
    }
    if (guia.vigente) {
        return (
            <Aviso tono="ok" icono={Truck} titulo="En camino">
                Ampara el traslado hasta el {fechaHora(guia.fecha_vencimiento)}.
            </Aviso>
        );
    }

    return (
        <Aviso tono="peligro" icono={Ban} titulo={guia.estado_etiqueta}>
            {guia.motivo_sin_amparar ?? 'No ampara el traslado.'}
        </Aviso>
    );
}

function Lugar({ titulo, lugar, detalle }: { titulo: string; lugar: string; detalle: (string | null)[] }) {
    return (
        <div className="min-w-0 rounded-xl bg-secondary/60 p-3">
            <p className="flex items-center gap-1.5 text-xs tracking-wide text-muted-foreground uppercase">
                <MapPin className="size-3.5" />
                {titulo}
            </p>
            <p className="truncate font-semibold">{lugar}</p>
            <p className="truncate text-xs text-muted-foreground">{detalle.filter(Boolean).join(' · ') || '—'}</p>
        </div>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string | null }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs tracking-wide text-muted-foreground uppercase">{etiqueta}</dt>
            <dd className={valor ? 'truncate font-medium' : 'text-muted-foreground'}>{valor ?? '—'}</dd>
        </div>
    );
}

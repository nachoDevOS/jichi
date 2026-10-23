import { Head, router, useForm } from '@inertiajs/react';
import {
    Ban,
    CalendarClock,
    ExternalLink,
    Fish,
    IdCard,
    Paperclip,
    Pencil,
    Plus,
    Printer,
    Receipt,
    Ship,
    Trash2,
    Truck,
    User,
    Users,
    Wallet,
    Waves,
} from 'lucide-react';
import { useState } from 'react';
import {
    Aviso,
    avisoVencimiento,
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
} from '@/components/panel/comunes/ficha';
import { TarjetaRecaudaciones } from '@/components/panel/pagos/tarjeta-recaudaciones';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha, fechaHora } from '@/lib/utils';
import type { ReciboDelCupo } from '@/types/aprovechamientos';
import type { CarnetFicha, FaenaDelCarnet, GuiaDelCarnet } from '@/types/carnets';

export default function VerCarnet({
    carnet,
    recibo,
    faenas,
    guias,
}: {
    carnet: CarnetFicha;
    recibo: ReciboDelCupo | null;
    faenas: FaenaDelCarnet[];
    guias: GuiaDelCarnet[];
}) {
    const { puede } = usePermisos();
    const [eliminando, setEliminando] = useState(false);
    const [revocando, setRevocando] = useState(false);
    const borrado = useForm({ motivo: '' });
    const revocacion = useForm({ motivo: '' });

    const pescador = carnet.tipo_actor === 'pescador';
    const habilita = pescador ? carnet.puede_emitir_faenas : carnet.puede_emitir_guias;
    const vence = avisoVencimiento(carnet.dias_para_vencer, carnet.ya_fue_aprobado);
    const numero = carnet.registro ? `${carnet.registro}${carnet.gestion ? ` / ${carnet.gestion}` : ''}` : null;
    const cupo = carnet.cupo;
    const kilosTrasladados = guias.reduce((suma, g) => suma + g.peso_total_kg, 0);

    return (
        <LayoutPanel
            titulo={carnet.tipo ?? 'Carnet'}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {puede('beneficiarios.ver') && (
                        <Button
                            variant="ver"
                            onClick={() => router.visit(route('beneficiarios.show', carnet.beneficiario_id))}
                        >
                            <User className="size-4" />
                            Beneficiario
                        </Button>
                    )}
                    {puede('carnets.imprimir') && carnet.puede_imprimirse && (
                        <EnlacePdf href={route('carnets.imprimir', carnet.id)} icono={Printer}>
                            Carnet
                        </EnlacePdf>
                    )}
                    {puede('recibos.imprimir') && recibo && (
                        <EnlacePdf href={route('recibos.imprimir', recibo.id)} icono={Receipt}>
                            Recibo
                        </EnlacePdf>
                    )}
                    {puede('carnets.editar') && carnet.puede_editarse && (
                        <Button variant="editar" onClick={() => router.visit(route('carnets.edit', carnet.id))}>
                            <Pencil className="size-4" />
                            Editar
                        </Button>
                    )}
                    {puede('carnets.eliminar') && carnet.puede_eliminarse && (
                        <Button variant="eliminar" onClick={() => setEliminando(true)}>
                            <Trash2 className="size-4" />
                            Eliminar
                        </Button>
                    )}
                    {puede('carnets.revocar') && carnet.puede_revocarse && (
                        <Button variant="eliminar" onClick={() => setRevocando(true)}>
                            <Ban className="size-4" />
                            Revocar
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={`Carnet ${carnet.codigo}`} />

            <BandaTitular
                foto={carnet.foto_url}
                nombre={carnet.beneficiario}
                beneficiarioId={carnet.beneficiario_id}
                linea={`C.I. ${carnet.documento_identidad ?? '—'} · ${numero ? `Carnet N° ${numero}` : 'El N° se asigna al aprobar'}`}
                estado={{ color: carnet.estado_color, etiqueta: carnet.estado_etiqueta }}
            >
                <Chip icono={pescador ? Fish : Truck}>{carnet.tipo_actor_etiqueta}</Chip>
                {carnet.asociacion_sigla && <Chip icono={Users}>{carnet.asociacion_sigla}</Chip>}
                <Chip icono={CalendarClock} tono={vence.tono}>
                    Vence el {fecha(carnet.fecha_vencimiento)}
                    {vence.texto && <span className="font-semibold">· {vence.texto}</span>}
                </Chip>
            </BandaTitular>

            {carnet.estado === 'pendiente' && (
                <div className="mt-4">
                    <Aviso tono="info" icono={Wallet} titulo="Pendiente de pago">
                        Todavía no habilita: falta que se pague y se valide en Recaudaciones. Mientras tanto se puede
                        corregir o eliminar.
                    </Aviso>
                </div>
            )}

            <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                {pescador && cupo ? (
                    // El carnet muestra lo que lleva impreso: el volumen autorizado, no el saldo.
                    <Cifra
                        icono={Fish}
                        etiqueta="Volumen autorizado"
                        valor={<Conteo valor={cupo.volumen_total_kg} formato={kg} />}
                        pie={`Vence el ${fecha(cupo.fecha_vencimiento)}`}
                        destacada
                    />
                ) : (
                    <Cifra
                        icono={Truck}
                        etiqueta="Guías emitidas"
                        valor={<Conteo valor={guias.length} formato={(n) => String(n)} />}
                        pie="Traslados con este carnet"
                        destacada
                    />
                )}
                {pescador ? (
                    <Cifra
                        icono={Ship}
                        etiqueta="Faenas emitidas"
                        valor={<Conteo valor={faenas.length} formato={(n) => String(n)} />}
                        pie="Salidas con este carnet"
                    />
                ) : (
                    <Cifra
                        icono={Waves}
                        etiqueta="Kilos trasladados"
                        valor={<Conteo valor={kilosTrasladados} formato={kg} />}
                        pie="Suma de sus guías"
                    />
                )}
                <Cifra
                    icono={Users}
                    etiqueta="Asociación"
                    valor={carnet.asociacion_sigla ?? '—'}
                    pie={carnet.asociacion_nombre ?? 'Sin asociación'}
                />
                <Cifra
                    icono={Wallet}
                    etiqueta="Monto"
                    valor={<Conteo valor={carnet.monto} formato={(n) => bs(n)} />}
                    pie={recibo ? `Recibo N° ${recibo.numero_recibo}` : 'Sin recibo todavía'}
                />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <Card className="min-w-0 lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Qué habilita</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        <Aviso
                            tono={habilita ? 'ok' : 'aviso'}
                            icono={pescador ? Ship : Truck}
                            titulo={pescador ? 'Emitir permisos de faena' : 'Emitir guías de transporte'}
                        >
                            {habilita ? 'Habilitado hoy.' : (carnet.motivo_sin_permisos ?? 'No habilitado.')}
                        </Aviso>

                        {/* El pescador trabaja con los kilos de su autorización; el comercializador no lleva una. */}
                        {cupo && (
                            <div className="space-y-3 rounded-xl border border-border p-4">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Waves className="size-4 shrink-0 text-primary" />
                                    <span className="text-sm font-medium">
                                        Autorización de Pesca para Aprovechamiento Pesquero
                                    </span>
                                    <Badge color={cupo.estado_color}>{cupo.estado_etiqueta}</Badge>
                                    {puede('aprovechamientos.ver') && (
                                        <span className="ml-auto">
                                            <EnlaceVer href={route('aprovechamientos.show', cupo.id)} />
                                        </span>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {kg(cupo.volumen_total_kg)} autorizados · vence el {fecha(cupo.fecha_vencimiento)}
                                </p>
                            </div>
                        )}

                        {/* Los papeles que la persona trajo al mostrador. */}
                        <div className="space-y-2">
                            <p className="flex items-center gap-2 text-sm font-medium">
                                <Paperclip className="size-4 text-muted-foreground" />
                                Respaldos de la emisión
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Respaldo url={carnet.archivo_ci_url}>Cédula del titular</Respaldo>
                                <Respaldo url={carnet.archivo_asociacion_url}>Documento de la asociación</Respaldo>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>El carnet</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5 text-sm">
                        <TarjetaCodigo
                            titulo="Código del carnet"
                            codigo={carnet.codigo}
                            numero={numero}
                            ayuda="Va en el QR del plástico. Con él cualquiera comprueba si el carnet está vigente."
                        />

                        <LineaTiempo
                            hitos={[
                                { etiqueta: 'Solicitado', fecha: carnet.fecha_solicitud, hecho: true },
                                {
                                    etiqueta: 'Emitido',
                                    fecha: carnet.fecha_emision,
                                    hecho: carnet.fecha_emision !== null,
                                },
                                {
                                    etiqueta: 'Vence',
                                    fecha: carnet.fecha_vencimiento,
                                    hecho: carnet.ya_fue_aprobado && (carnet.dias_para_vencer ?? 0) < 0,
                                },
                            ]}
                        />
                    </CardContent>
                </Card>
            </div>

            <TarjetaRecaudaciones
                className="mt-6"
                monto={carnet.monto}
                pago={{
                    concepto: `Carnet de ${carnet.tipo_actor_etiqueta.toLowerCase()} · gestión ${carnet.gestion}`,
                    numero: carnet.registro,
                    titular: carnet.beneficiario,
                    documento: carnet.documento,
                    detalle: carnet.tipo,
                }}
                sireb={carnet.sireb}
                recibo={recibo}
                puedeVerificar={carnet.puede_verificar_pago}
                rutaVerificar={route('carnets.verificar-pago', carnet.id)}
                puedeCargar={carnet.puede_cargar_pago}
                rutaCargar={route('carnets.cargar-pago', carnet.id)}
                rutaConsultarQr={route('carnets.consultar-qr', carnet.id)}
                rutaRenovar={route('carnets.renovar-liquidacion', carnet.id)}
                documento="carnets"
            />

            {/* Lo que se emitió con este carnet, desde la aprobación. Revocarlo no lo anula. */}
            {carnet.ya_fue_aprobado && (
                <Card className="mt-6 min-w-0 overflow-hidden">
                    <CardHeader className="flex-row items-center justify-between gap-2 space-y-0 border-b border-border">
                        <CardTitle className="flex items-center gap-2">
                            {pescador ? (
                                <Ship className="size-4 text-primary" />
                            ) : (
                                <Truck className="size-4 text-primary" />
                            )}
                            {pescador ? 'Faenas emitidas' : 'Guías emitidas'}
                        </CardTitle>
                        {pescador && puede('faenas.crear') && carnet.puede_emitir_faenas && (
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.visit(route('faenas.create', { beneficiario: carnet.beneficiario_id }))
                                }
                            >
                                <Plus className="size-4" />
                                Emitir faena
                            </Button>
                        )}
                        {!pescador && puede('guias.crear') && carnet.puede_emitir_guias && (
                            <Button
                                size="sm"
                                onClick={() => router.visit(route('guias.create', { carnet: carnet.id }))}
                            >
                                <Plus className="size-4" />
                                Emitir guía
                            </Button>
                        )}
                    </CardHeader>

                    {pescador &&
                        (faenas.length === 0 ? (
                            <EstadoVacio
                                icono={Ship}
                                titulo="Sin faenas"
                                descripcion="Todavía no se emitió ninguna faena con este carnet."
                            />
                        ) : (
                            <Tabla columnas={['N°', '>Kilos', 'Región', 'Salida', 'Desembarque', 'Estado', '']}>
                                {faenas.map((f) => (
                                    <tr key={f.id} className="hover:bg-secondary/50">
                                        <Td>
                                            <span className="font-mono font-medium">{f.numero_legible}</span>
                                        </Td>
                                        <Td derecha>{kg(f.kilos_extraidos)}</Td>
                                        <Td apagada>
                                            {f.region_desde || f.region_hasta
                                                ? `${f.region_desde ?? '—'} → ${f.region_hasta ?? '—'}`
                                                : '—'}
                                        </Td>
                                        <Td apagada>{fecha(f.fecha_salida)}</Td>
                                        <Td apagada>{fecha(f.fecha_desembarque)}</Td>
                                        <Td>
                                            <Badge color={f.estado_color}>{f.estado_etiqueta}</Badge>
                                        </Td>
                                        <Td>
                                            <div className="flex justify-end">
                                                {puede('faenas.ver') && <EnlaceVer href={route('faenas.show', f.id)} />}
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </Tabla>
                        ))}

                    {!pescador &&
                        (guias.length === 0 ? (
                            <EstadoVacio
                                icono={Truck}
                                titulo="Sin guías"
                                descripcion="Todavía no se emitió ninguna guía con este carnet."
                            />
                        ) : (
                            <Tabla columnas={['N°', 'Ruta', '>Peso', 'Aprobada', 'Vence', 'Estado', '']}>
                                {guias.map((g) => (
                                    <tr key={g.id} className="hover:bg-secondary/50">
                                        <Td>
                                            <span className="font-mono font-medium">{g.numero_legible}</span>
                                        </Td>
                                        <Td apagada>
                                            {g.origen} → {g.destino}
                                        </Td>
                                        <Td derecha>{kg(g.peso_total_kg)}</Td>
                                        <Td apagada>{fechaHora(g.fecha_emision)}</Td>
                                        <Td apagada>{fechaHora(g.fecha_vencimiento)}</Td>
                                        <Td>
                                            <Badge color={g.estado_color}>{g.estado_etiqueta}</Badge>
                                        </Td>
                                        <Td>
                                            <div className="flex justify-end">
                                                {puede('guias.ver') && <EnlaceVer href={route('guias.show', g.id)} />}
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </Tabla>
                        ))}
                </Card>
            )}

            <ConfirmarConMotivo
                abierto={eliminando}
                titulo="Eliminar este carnet"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El carnet <strong>{carnet.codigo}</strong> desaparece de los listados. Se elimina solo
                            porque está pendiente de pago; el cobro se anula en Recaudaciones.
                        </p>
                        <p>
                            <strong>El código no se libera:</strong> pudo alcanzar a imprimirse.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la eliminación"
                ayuda="Queda en la auditoría con su nombre."
                placeholder="Cargado por error: la persona ya tenía carnet de esta gestión."
                confirmacion="Entiendo que el carnet desaparece de los listados y que el código queda quemado."
                textoConfirmar="Eliminar carnet"
                valor={borrado.data.motivo}
                onCambiar={(v) => borrado.setData('motivo', v)}
                error={borrado.errors.motivo}
                procesando={borrado.processing}
                onCancelar={() => {
                    setEliminando(false);
                    borrado.reset();
                }}
                onConfirmar={() =>
                    borrado.delete(route('carnets.destroy', carnet.id), {
                        // Borrado va al listado; con el pago ya validado vuelve acá aprobado.
                        onSuccess: () => {
                            setEliminando(false);
                            borrado.reset();
                        },
                    })
                }
            />

            <ConfirmarConMotivo
                abierto={revocando}
                titulo="Revocar este carnet"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El carnet <strong>{carnet.codigo}</strong> de{' '}
                            <strong>{carnet.beneficiario ?? 'el titular'}</strong> deja de valer, y al escanearlo figura
                            como revocado. <strong>No se revierte.</strong>
                        </p>
                        <p>
                            Sus {pescador ? 'faenas' : 'guías'} quedan sin efecto hasta que se apruebe un carnet nuevo
                            de la misma actividad.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la revocación"
                ayuda="Queda en la auditoría con su nombre."
                placeholder="Extravío del carnet: el titular tramita la reposición."
                confirmacion="Entiendo que el carnet deja de valer y que la revocación no se revierte."
                textoConfirmar="Revocar carnet"
                valor={revocacion.data.motivo}
                onCambiar={(v) => revocacion.setData('motivo', v)}
                error={revocacion.errors.motivo}
                procesando={revocacion.processing}
                onCancelar={() => {
                    setRevocando(false);
                    revocacion.reset();
                }}
                onConfirmar={() =>
                    revocacion.patch(route('carnets.revocar', carnet.id), {
                        onSuccess: () => {
                            setRevocando(false);
                            revocacion.reset();
                        },
                    })
                }
            />
        </LayoutPanel>
    );
}

/** Un adjunto de la emisión, o el aviso de que no se cargó. */
function Respaldo({ url, children }: { url: string | null; children: string }) {
    return url ? (
        <a href={url} target="_blank" rel="noreferrer" className={cn(buttonVariants({ variant: 'ver', size: 'sm' }))}>
            <ExternalLink className="size-4" />
            {children}
        </a>
    ) : (
        <span className="flex items-center gap-1.5 rounded-md border border-dashed border-border px-2.5 py-1 text-xs text-muted-foreground">
            <IdCard className="size-3.5" />
            Sin {children.toLowerCase()}
        </span>
    );
}

import { Head, router, useForm } from '@inertiajs/react';
import { Ban, ExternalLink, Paperclip, Pencil, Printer, Receipt, Ship, Trash2, Truck, User, Waves } from 'lucide-react';
import { useState } from 'react';
import { TextoCopiable } from '@/components/comunes/texto-copiable';
import { Retrato } from '@/components/comunes/retrato';
import { BarraSaldo } from '@/components/panel/aprovechamientos/barra-saldo';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TarjetaRecaudaciones } from '@/components/panel/pagos/tarjeta-recaudaciones';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { cn, fecha, fechaHora } from '@/lib/utils';
import type { ReciboDelCupo } from '@/types/aprovechamientos';
import type { CarnetFicha, FaenaDelCarnet, GuiaDelCarnet } from '@/types/carnets';
import { EnlacePermitido } from '@/components/panel/comunes/enlace-permitido';

/**
 *  La ficha de un carnet
 */
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

    return (
        <LayoutPanel
            /*
             * El encabezado no repite al titular, igual que en la ficha del
             * cupo: el nombre, la cédula y el código están en la tarjeta de
             * abajo, con la foto al lado. Arriba quedan las acciones.
             */
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

                    {/*
                        IMPRIMIR abre en una pestaña aparte y no en un iframe: con
                        un PDF, `iframe.onLoad` no dispara nunca —medido— así que
                        un «cargando…» que dependa de él se queda colgado, y
                        `contentWindow.print()` sobre un PDF lo ignora o lo bloquea
                        según el navegador. La barra del visor propio funciona.
                    */}
                    {/*
                        CORREGIR Y ELIMINAR SOLO SOBRE EL BORRADOR. Las dos
                        banderas llegan resueltas: miran el estado Y que no
                        haya entrado un peso.
                    */}
                    {puede('carnets.editar') && carnet.puede_editarse && (
                        <Button
                            variant="editar"
                            onClick={() => router.visit(route('carnets.edit', carnet.id))}
                        >
                            <Pencil className="size-4" />
                            Editar
                        </Button>
                    )}

                    {puede('carnets.revocar') && carnet.puede_revocarse && (
                        <Button variant="eliminar" onClick={() => setRevocando(true)}>
                            <Ban className="size-4" />
                            Revocar
                        </Button>
                    )}

                    {puede('carnets.eliminar') && carnet.puede_eliminarse && (
                        <Button variant="eliminar" onClick={() => setEliminando(true)}>
                            <Trash2 className="size-4" />
                            Eliminar
                        </Button>
                    )}



                    {/* El plástico sale recién con el carnet firmado. */}
                    {puede('carnets.imprimir') && carnet.puede_imprimirse && (
                        <a href={route('carnets.imprimir', carnet.id)} target="_blank" rel="noopener">
                            <Button variant="outline">
                                <Printer className="size-4" />
                                Carnet
                            </Button>
                        </a>
                    )}

                    {/* EL RECIBO, arriba y no solo dentro de «Pagos»: mismo
                        botón y mismo PDF que en el aprovechamiento. Sale
                        cuando el recibo ya se emitió, o sea desde que el
                        carnet pasó a revisión. */}
                    {puede('recibos.imprimir') && recibo && (
                        <a
                            href={route('recibos.imprimir', recibo.id)}
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
            <Head title={`Carnet ${carnet.codigo}`} />

            {/* El titular, con su foto. Sobre un carnet la primera pregunta
                es de quién es, y la cara al lado del nombre es lo que deja
                confirmarlo contra la persona que está en el mostrador. */}
            <Card className="mb-6 min-w-0">
                <CardContent className="flex flex-wrap items-center gap-4 p-4">
                    <Retrato
                        url={carnet.foto_url}
                        nombre={carnet.beneficiario ?? 'Sin nombre'}
                        className="size-16"
                    />

                    <div className="min-w-0">
                        <EnlacePermitido
                            permiso="beneficiarios.ver"
                            href={route('beneficiarios.show', carnet.beneficiario_id)}
                            className="text-lg font-semibold text-primary hover:underline"
                        >
                            {carnet.beneficiario ?? '—'}
                        </EnlacePermitido>

                        <p className="tabular-nums text-sm text-muted-foreground">
                            C.I. {carnet.documento_identidad ?? '—'}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* ------------------------------------------ Qué habilita hoy */}
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Qué habilita</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        <Habilitacion
                            icono={carnet.tipo_actor === 'pescador' ? Ship : Truck}
                            puede={
                                carnet.tipo_actor === 'pescador'
                                    ? carnet.puede_emitir_faenas
                                    : carnet.puede_emitir_guias
                            }
                            titulo={
                                carnet.tipo_actor === 'pescador'
                                    ? 'Emitir permisos de faena'
                                    : 'Emitir guías de movimiento'
                            }
                            razon={carnet.motivo_sin_permisos}
                        />

                        {/*
                            El cupo solo aparece si el carnet lo lleva. En un
                            comercializador no es que «falte»: la comercialización
                            no se autoriza por volumen.
                        */}
                        {carnet.cupo && (
                            <div className="space-y-3 rounded-md border border-border p-4">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Waves className="size-4 shrink-0 text-muted-foreground" />

                                    {/* El nombre verdadero del documento, el
                                        mismo que imprime el recibo y encabeza
                                        su ficha. «Cupo de pesca» era jerga
                                        nuestra. Ver App\Enums\ConceptoRecibo. */}
                                    <span className="text-sm font-medium">
                                        Autorización de Pesca para Aprovechamiento Pesquero
                                    </span>

                                    <Badge color={carnet.cupo.estado_color}>
                                        {carnet.cupo.estado_etiqueta}
                                    </Badge>

                                    {/*
                                        EN UNA PESTAÑA APARTE. El cupo se mira
                                        para contrastarlo con lo que se está
                                        haciendo sobre el carnet —cuántos kilos
                                        quedan, hasta cuándo vale— y salir de la
                                        ficha obliga a volver y buscarla de
                                        nuevo. Es un `<a>` y no `router.visit`:
                                        Inertia navega en la misma pestaña.
                                    */}
                                    {puede('aprovechamientos.ver') && (
                                        <a
                                            href={route('aprovechamientos.show', carnet.cupo.id)}
                                            target="_blank"
                                            rel="noreferrer"
                                            title="Abrir la autorización en otra pestaña"
                                            className={cn(
                                                'ml-auto',
                                                buttonVariants({ variant: 'ver', size: 'sm' }),
                                            )}
                                        >
                                            <ExternalLink className="size-4" />
                                            Ver
                                        </a>
                                    )}
                                </div>

                                <p className="text-sm text-muted-foreground">
                                    {carnet.cupo.descripcion ?? '—'}
                                </p>

                                <BarraSaldo cupo={carnet.cupo} />

                                {/*
                                    LA CAPACIDAD Y LAS FECHAS, que es lo que un
                                    control pregunta: cuánto autoriza y hasta
                                    cuándo. «Otorgado el» va vacío mientras el
                                    cupo no esté firmado.
                                */}
                                {/* Apilado y no con `Dato`: ese pone rótulo y
                                    valor en la misma línea, y en una grilla de
                                    cuatro columnas quedan pegados. */}
                                <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
                                    <DatoApilado
                                        etiqueta="Capacidad"
                                        valor={`${carnet.cupo.volumen_total_kg} kg`}
                                    />

                                    {carnet.cupo.ya_fue_aprobado && carnet.cupo.kilos_consumidos > 0 && (
                                        <DatoApilado
                                            etiqueta="Disponible"
                                            valor={`${carnet.cupo.saldo_kg} kg`}
                                        />
                                    )}

                                    <DatoApilado
                                        etiqueta="Solicitado el"
                                        valor={fecha(carnet.cupo.fecha_solicitud)}
                                    />

                                    <DatoApilado
                                        etiqueta="Otorgado el"
                                        valor={
                                            carnet.cupo.fecha_emision
                                                ? fecha(carnet.cupo.fecha_emision)
                                                : '—'
                                        }
                                    />

                                    <DatoApilado
                                        etiqueta="Vence el"
                                        valor={fecha(carnet.cupo.fecha_vencimiento)}
                                    />
                                </dl>
                            </div>
                        )}
                        {/*
                            LOS RESPALDOS DE LA EMISIÓN. Son los papeles que la
                            persona trajo al mostrador: tenerlos acá evita ir a
                            buscar la carpeta cuando alguien pregunta con qué se
                            emitió. Abren en otra pestaña —son archivos— y quien
                            no tenga permiso de ver la ficha no llega hasta acá.
                        */}
                        <div className="space-y-2 rounded-md border border-border p-4">
                            <div className="flex items-center gap-2">
                                <Paperclip className="size-4 text-muted-foreground" />
                                <span className="text-sm font-medium">Respaldos de la emisión</span>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {carnet.archivo_ci_url ? (
                                    <a
                                        href={carnet.archivo_ci_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className={cn(buttonVariants({ variant: 'ver', size: 'sm' }))}
                                    >
                                        <ExternalLink className="size-4" />
                                        Cédula del titular
                                    </a>
                                ) : (
                                    <span className="text-sm text-muted-foreground">
                                        Sin la cédula adjunta
                                    </span>
                                )}

                                {carnet.archivo_asociacion_url ? (
                                    <a
                                        href={carnet.archivo_asociacion_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className={cn(buttonVariants({ variant: 'ver', size: 'sm' }))}
                                    >
                                        <ExternalLink className="size-4" />
                                        Documento de la asociación
                                    </a>
                                ) : (
                                    <span className="text-sm text-muted-foreground">
                                        Sin el documento de la asociación
                                    </span>
                                )}
                            </div>

                            {/* Los carnets viejos —los cargados para poner al
                                día lo emitido en papel— no tienen escaneos, y
                                eso no es un error: conviene decirlo. */}
                            {!carnet.archivo_ci_url && !carnet.archivo_asociacion_url && (
                                <p className="text-xs text-muted-foreground">
                                    Este carnet se cargó sin adjuntos. Se pueden subir corrigiéndolo,
                                    mientras siga PENDIENTE.
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* ------------------------------------------ Datos y cobro */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>La credencial</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-3 text-sm">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Estado</span>
                            <Badge color={carnet.estado_color}>{carnet.estado_etiqueta}</Badge>
                        </div>

                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Actividad</span>
                            <Badge color={carnet.tipo_actor_color}>{carnet.tipo_actor_etiqueta}</Badge>
                        </div>

                        {/* El registro primero: es el número que va impreso en
                            el carnet y el que se dicta. El código largo es la
                            llave de la verificación pública. */}
                        <Dato
                            etiqueta="Registro"
                            valor={
                                carnet.registro
                                    ? `${carnet.registro}${carnet.gestion ? ` / ${carnet.gestion}` : ''}`
                                    : 'Se asigna al aprobar'
                            }
                            mono
                        />

                        <div className="flex items-center justify-between gap-3">
                            <span className="text-muted-foreground">Código</span>
                            <TextoCopiable texto={carnet.codigo} className="-mr-1.5" />
                        </div>
                        <Dato etiqueta="Documento" valor={carnet.documento_identidad ?? '—'} mono />
                        <Dato etiqueta="Tipo" valor={carnet.tipo ?? '—'} />
                        <Dato etiqueta="Asociación" valor={carnet.asociacion_nombre ?? '—'} />
                        <Dato etiqueta="Solicitado el" valor={fecha(carnet.fecha_solicitud)} />

                        {/* Vacío hasta la firma: recién ahí hay carnet emitido. */}
                        {carnet.fecha_emision !== null && (
                            <Dato etiqueta="Emitido el" valor={fecha(carnet.fecha_emision)} />
                        )}
                        <Dato etiqueta="Vence el" valor={fecha(carnet.fecha_vencimiento)} />

                        {/*
                            El aviso de vencimiento cercano. `dias_para_vencer`
                            llega calculado del servidor: la pantalla no resta
                            fechas, porque `new Date('2026-12-31')` en JavaScript
                            se interpreta como medianoche UTC y en UTC-4 devuelve
                            el día anterior.
                        */}
                        {carnet.vigente &&
                            carnet.dias_para_vencer !== null &&
                            carnet.dias_para_vencer <= 30 && (
                                <p className="rounded-md bg-amber-50 p-3 text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                                    Vence en {carnet.dias_para_vencer} día(s). Conviene avisarle al
                                    titular para que renueve.
                                </p>
                            )}
                    </CardContent>
                </Card>
            </div>

            {/* El cobro: se paga en SIREB, igual que los otros tres documentos. */}
            <div className="mt-6">
                <TarjetaRecaudaciones
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
            </div>

            {/* Las salidas de este carnet, recién desde la aprobación. Revocarlo no las anula. */}
            {carnet.tipo_actor === 'pescador' && carnet.ya_fue_aprobado && (
                <Card className="mt-6 min-w-0">
                    <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle>Faenas emitidas</CardTitle>

                        {/* `puede_emitir_faenas` exige carnet vigente y cupo con saldo:
                            sin eso el formulario lo rechazaría. */}
                        {puede('faenas.crear') && carnet.puede_emitir_faenas && (
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.visit(
                                        route('faenas.create', { beneficiario: carnet.beneficiario_id }),
                                    )
                                }
                            >
                                <Ship className="size-4" />
                                Emitir faena
                            </Button>
                        )}
                    </CardHeader>

                    {faenas.length === 0 ? (
                        <CardContent>
                            <p className="text-sm text-muted-foreground">Todavía no se emitió ninguna faena con este carnet.</p>
                        </CardContent>
                    ) : (
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-2.5 font-medium">N°</th>
                                        <th className="px-5 py-2.5 font-medium">Estado</th>
                                        <th className="px-5 py-2.5 text-right font-medium">Kilos</th>
                                        <th className="px-5 py-2.5 font-medium">Región</th>
                                        <th className="px-5 py-2.5 font-medium">Salida</th>
                                        <th className="px-5 py-2.5 font-medium">Desembarque</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {faenas.map((f) => (
                                        <tr key={f.id} className="border-b border-border last:border-0">
                                            <td className="px-5 py-2.5">
                                                <EnlacePermitido
                                                    permiso="faenas.ver"
                                                    href={route('faenas.show', f.id)}
                                                    className="font-mono text-primary hover:underline"
                                                >
                                                    {f.numero_legible}
                                                </EnlacePermitido>
                                            </td>
                                            <td className="px-5 py-2.5">
                                                <Badge color={f.estado_color}>{f.estado_etiqueta}</Badge>
                                            </td>
                                            <td className="px-5 py-2.5 text-right tabular-nums">{f.kilos_extraidos} kg</td>
                                            <td className="px-5 py-2.5 text-muted-foreground">
                                                {f.region_desde || f.region_hasta
                                                    ? `${f.region_desde ?? '—'} → ${f.region_hasta ?? '—'}`
                                                    : '—'}
                                            </td>
                                            <td className="px-5 py-2.5 text-muted-foreground">{fecha(f.fecha_salida)}</td>
                                            <td className="px-5 py-2.5 text-muted-foreground">
                                                {fecha(f.fecha_desembarque)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    )}
                </Card>
            )}

            {/* Los traslados de este carnet, espejo de las faenas. Revocarlo no los anula. */}
            {carnet.tipo_actor === 'comercializador' && carnet.ya_fue_aprobado && (
                <Card className="mt-6 min-w-0">
                    <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle>Guías emitidas</CardTitle>

                        {/* `puede_emitir_guias` exige el carnet vigente. */}
                        {puede('guias.crear') && carnet.puede_emitir_guias && (
                            <Button
                                size="sm"
                                onClick={() => router.visit(route('guias.create', { carnet: carnet.id }))}
                            >
                                <Truck className="size-4" />
                                Emitir guía
                            </Button>
                        )}
                    </CardHeader>

                    {guias.length === 0 ? (
                        <CardContent>
                            <p className="text-sm text-muted-foreground">Todavía no se emitió ninguna guía con este carnet.</p>
                        </CardContent>
                    ) : (
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-2.5 font-medium">N°</th>
                                        <th className="px-5 py-2.5 font-medium">Estado</th>
                                        <th className="px-5 py-2.5 font-medium">Ruta</th>
                                        <th className="px-5 py-2.5 text-right font-medium">Peso</th>
                                        <th className="px-5 py-2.5 font-medium">Emitida</th>
                                        <th className="px-5 py-2.5 font-medium">Vence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {guias.map((g) => (
                                        <tr key={g.id} className="border-b border-border last:border-0">
                                            <td className="px-5 py-2.5">
                                                <EnlacePermitido
                                                    permiso="guias.ver"
                                                    href={route('guias.show', g.id)}
                                                    className="font-mono text-primary hover:underline"
                                                >
                                                    {g.numero_legible}
                                                </EnlacePermitido>
                                            </td>
                                            <td className="px-5 py-2.5">
                                                <Badge color={g.estado_color}>{g.estado_etiqueta}</Badge>
                                            </td>
                                            <td className="px-5 py-2.5 text-muted-foreground">
                                                {g.origen} → {g.destino}
                                                {g.es_piscicultura && (
                                                    <Badge color="sky" className="ml-2">
                                                        Piscicultura
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-5 py-2.5 text-right tabular-nums">{g.peso_total_kg} kg</td>
                                            <td className="px-5 py-2.5 text-muted-foreground">{fechaHora(g.fecha_emision)}</td>
                                            <td className="px-5 py-2.5 text-muted-foreground">
                                                {fechaHora(g.fecha_vencimiento)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    )}
                </Card>
            )}



            {/*
                ELIMINAR PIDE MOTIVO **Y** CASILLA. La fila desaparece de los
                listados y lo único que queda es la línea de auditoría: sin el
                motivo, dentro de seis meses nadie puede explicar el hueco en la
                serie de códigos.
            */}
            <ConfirmarConMotivo
                abierto={eliminando}
                titulo="Eliminar este carnet"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El carnet <strong>{carnet.codigo}</strong> desaparece de los listados. Se
                            elimina solo porque está PENDIENTE de pago; el cobro se anula en Recaudaciones.
                        </p>
                        <p>
                            <strong>El código no se libera:</strong> el índice es global y ese
                            número pudo alcanzar a imprimirse.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la eliminación"
                ayuda="Queda en la auditoría con su nombre. Es lo único que va a explicar el hueco."
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

            {/* REVOCAR: el carnet deja de valer, también al escanearlo. Sus faenas siguen. */}
            <ConfirmarConMotivo
                abierto={revocando}
                titulo="Revocar este carnet"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El carnet <strong>{carnet.codigo}</strong> de{' '}
                            <strong>{carnet.beneficiario ?? 'el titular'}</strong> deja de valer, y al
                            escanearlo figura como revocado. <strong>No se revierte.</strong>
                        </p>
                        <p>
                            Las faenas ya emitidas con él <strong>siguen vigentes</strong>. Con la misma
                            Autorización de Pesca para Aprovechamiento Pesquero se le puede emitir un carnet nuevo.
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

/**
 * Por qué el carnet no habilita, cuando no habilita.
 */
function Habilitacion({
    icono: Icono,
    puede,
    titulo,
    razon,
}: {
    icono: typeof Ship;
    puede: boolean;
    titulo: string;
    razon: string | null;
}) {
    return (
        <div
            className={
                puede
                    ? 'flex items-start gap-3 rounded-md bg-emerald-50 p-4 dark:bg-emerald-500/10'
                    : 'flex items-start gap-3 rounded-md bg-amber-50 p-4 dark:bg-amber-500/10'
            }
        >
            <Icono
                className={
                    puede
                        ? 'mt-0.5 size-5 shrink-0 text-emerald-700 dark:text-emerald-300'
                        : 'mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300'
                }
            />

            <div className="text-sm">
                <p
                    className={
                        puede
                            ? 'font-medium text-emerald-900 dark:text-emerald-200'
                            : 'font-medium text-amber-900 dark:text-amber-200'
                    }
                >
                    {puede ? titulo : `${titulo}: no`}
                </p>

                <p
                    className={
                        puede
                            ? 'text-emerald-800/80 dark:text-emerald-200/80'
                            : 'text-amber-800/80 dark:text-amber-200/80'
                    }
                >
                    {puede ? 'Habilitado hoy.' : (razon ?? 'No habilitado.')}
                </p>
            </div>
        </div>
    );
}

/** Rótulo arriba y valor abajo: para las grillas de varias columnas. */
function DatoApilado({ etiqueta, valor }: { etiqueta: string; valor: string }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">{etiqueta}</dt>
            <dd className="truncate font-medium tabular-nums">{valor}</dd>
        </div>
    );
}

function Dato({ etiqueta, valor, mono = false }: { etiqueta: string; valor: string; mono?: boolean }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted-foreground">{etiqueta}</span>
            <span className={mono ? 'text-right font-mono font-medium' : 'text-right font-medium'}>
                {valor}
            </span>
        </div>
    );
}

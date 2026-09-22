import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    ExternalLink,
    Paperclip,
    Pencil,
    Printer,
    Receipt,
    Send,
    Ship,
    Trash2,
    Truck,
    Undo2,
    User,
    Waves,
} from 'lucide-react';
import { useState } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { BarraSaldo } from '@/components/panel/aprovechamientos/barra-saldo';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TarjetaPagos } from '@/components/panel/pagos/tarjeta-pagos';
import { ConfirmarAccion } from '@/components/ui/confirmar-accion';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { PagoDelCupo, ReciboDelCupo } from '@/types/aprovechamientos';
import type { CarnetFicha } from '@/types/carnets';

/**
 *  LA FICHA DE UN CARNET
 */
export default function VerCarnet({
    carnet,
    pagos,
    recibo,
}: {
    carnet: CarnetFicha;
    pagos: PagoDelCupo[];
    recibo: ReciboDelCupo | null;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [rechazando, setRechazando] = useState(false);
    const [aprobando, setAprobando] = useState(false);
    const [eliminando, setEliminando] = useState(false);

    const borrado = useForm({ motivo: '' });
    const rechazo = useForm({ motivo: '' });
    const envio = useForm({});

    return (
        <LayoutPanel
            /*
             * EL ENCABEZADO NO REPITE AL TITULAR, igual que en la ficha del
             * cupo: el nombre, la cédula y el código están en la tarjeta de
             * abajo, con la foto al lado. Arriba quedan las acciones.
             */
            titulo={carnet.tipo ?? 'Carnet'}
            acciones={
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="ver"
                        onClick={() => router.visit(route('beneficiarios.show', carnet.beneficiario_id))}
                    >
                        <User className="size-4" />
                        Ver al beneficiario
                    </Button>

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

                    {puede('carnets.eliminar') && carnet.puede_eliminarse && (
                        <Button variant="eliminar" onClick={() => setEliminando(true)}>
                            <Trash2 className="size-4" />
                            Eliminar
                        </Button>
                    )}

                    {/*
                        EL CIRCUITO, igual que en el aprovechamiento: presentar
                        es de ventanilla y firmar es de supervisión. Las tres
                        banderas llegan resueltas del servidor.
                    */}
                    {puede('carnets.enviar') && carnet.puede_enviarse && (
                        <Button
                            onClick={() =>
                                envio.post(route('carnets.enviar', carnet.id), {
                                    preserveScroll: true,
                                })
                            }
                            disabled={envio.processing}
                        >
                            <Send className="size-4" />
                            Enviar a revisión
                        </Button>
                    )}

                    {puede('carnets.aprobar') && carnet.puede_revisarse && (
                        <>
                            {/* Apagado mientras falte validar alguna boleta, y
                                el title dice cuántas: el servidor lo exige
                                igual, y un botón que promete y falla es peor. */}
                            <Button
                                onClick={() => setAprobando(true)}
                                disabled={envio.processing || !carnet.puede_aprobarse}
                                title={
                                    carnet.puede_aprobarse
                                        ? undefined
                                        : `Faltan ${carnet.pagos_sin_validar} depósito(s) por validar`
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

                    {/* El plástico sale recién con el carnet firmado. */}
                    {puede('carnets.imprimir') && carnet.ya_fue_aprobado && (
                        <a href={route('carnets.imprimir', carnet.id)} target="_blank" rel="noopener">
                            <Button variant="outline">
                                <Printer className="size-4" />
                                Imprimir carnet
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
                            Imprimir recibo
                        </a>
                    )}

                    {/*
                        El atajo al paso 4. Solo aparece cuando el carnet
                        REALMENTE habilita: `puede_emitir_faenas` exige carnet
                        vigente, de pescador y con cupo con saldo. Ofrecerlo
                        igual mandaría al operador a un formulario que el
                        servidor va a rechazar.
                    */}
                    {puede('faenas.crear') && carnet.puede_emitir_faenas && (
                        <Button
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

                    {puede('guias.crear') && carnet.puede_emitir_guias && (
                        <Button
                            onClick={() =>
                                router.visit(
                                    route('guias.create', { beneficiario: carnet.beneficiario_id }),
                                )
                            }
                        >
                            <Truck className="size-4" />
                            Emitir guía
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={`Carnet ${carnet.codigo}`} />

            {/* EL TITULAR, CON SU FOTO. Sobre un carnet la primera pregunta
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
                        <Link
                            href={route('beneficiarios.show', carnet.beneficiario_id)}
                            className="text-lg font-semibold text-primary hover:underline"
                        >
                            {carnet.beneficiario ?? '—'}
                        </Link>

                        <p className="tabular-nums text-sm text-muted-foreground">
                            C.I. {carnet.documento_identidad ?? '—'}
                        </p>

                        {/* Solo el código: el estado y la actividad ya están
                            en la tarjeta «Situación», y repetirlos hace dudar
                            de cuál de los dos manda. */}
                        <p className="font-mono text-sm text-muted-foreground">{carnet.codigo}</p>
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

                                    {/* EL NOMBRE VERDADERO del documento, el
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

                        {/* EL REGISTRO PRIMERO: es el número que va impreso en
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

                        <Dato etiqueta="Código" valor={carnet.codigo} mono />
                        <Dato etiqueta="Documento" valor={carnet.documento_identidad ?? '—'} mono />
                        <Dato etiqueta="Tipo" valor={carnet.tipo ?? '—'} />
                        <Dato etiqueta="Asociación" valor={carnet.asociacion_nombre ?? '—'} />
                        <Dato etiqueta="Solicitado el" valor={fecha(carnet.fecha_solicitud)} />

                        {/* Vacío hasta la firma: recién ahí hay carnet emitido. */}
                        {carnet.fecha_emision !== null && (
                            <Dato etiqueta="Emitido el" valor={fecha(carnet.fecha_emision)} />
                        )}
                        <Dato etiqueta="Vence el" valor={fecha(carnet.fecha_vencimiento)} />

                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Cobro</span>
                            {carnet.pagado ? (
                                <span className="font-medium text-emerald-700 dark:text-emerald-400">
                                    Pagado
                                </span>
                            ) : (
                                <span className="font-medium text-amber-700 dark:text-amber-400">
                                    debe {bs(carnet.saldo_pendiente, institucion.moneda)}
                                </span>
                            )}
                        </div>

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

            {/* EL COBRO DEL ARANCEL, con el mismo formulario que el cupo. */}
            <div className="mt-6">
                <TarjetaPagos
                    pagos={pagos}
                    recibo={recibo}
                    saldoPendiente={carnet.saldo_pendiente}
                    titular={carnet.beneficiario ?? 'el titular'}
                    admitePagos={carnet.admite_pagos}
                    rutaPagar={route('carnets.pagar', carnet.id)}
                    permisoEnviar="carnets.enviar"
                    textoAlEnviar="El carnet pasa a EN REVISIÓN y se emite el recibo con el total; el plástico se imprime recién cuando esté aprobado."
                />
            </div>

            {/*
                APROBAR PIDE CASILLA: es la FIRMA. Desde acá el carnet habilita
                a trabajar y se puede imprimir, y no hay «des-aprobar».
            */}
            <ConfirmarAccion
                abierto={aprobando}
                tono="afirmativo"
                titulo="Aprobar el carnet"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El carnet <strong>{carnet.codigo}</strong> de{' '}
                            <strong>{carnet.beneficiario ?? 'el titular'}</strong> queda ACTIVO hasta
                            el <strong>{fecha(carnet.fecha_vencimiento)}</strong>, y desde ese momento
                            se puede imprimir.
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
                    envio.patch(route('carnets.aprobar', carnet.id), {
                        preserveScroll: true,
                        onSuccess: () => setAprobando(false),
                    })
                }
            />

            {/*
                RECHAZAR PIDE MOTIVO Y CASILLA, igual que en el cupo: es la otra
                mitad de la firma y se confirma igual.
            */}
            <ConfirmarConMotivo
                abierto={rechazando}
                titulo="Rechazar y devolver a ventanilla"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El carnet vuelve a <strong>PENDIENTE</strong>.
                        </p>
                        <p>
                            Los depósitos ya cargados <strong>no se tocan</strong>, y el recibo
                            entregado sigue valiendo: ventanilla corrige lo observado y lo vuelve a
                            presentar sin recargar nada.
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
                    rechazo.patch(route('carnets.rechazar', carnet.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setRechazando(false);
                            rechazo.reset();
                        },
                    })
                }
            />

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
                            elimina solo porque está PENDIENTE y sin cobrar.
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
                onConfirmar={() => borrado.delete(route('carnets.destroy', carnet.id))}
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

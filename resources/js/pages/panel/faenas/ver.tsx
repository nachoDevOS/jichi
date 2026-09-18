import { Head, Link, useForm } from '@inertiajs/react';
import { Ban, BadgeCheck, Receipt, Ship, User } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha, fechaHora } from '@/lib/utils';
import type { CarnetDeFaena, FaenaFicha, PagoDePermiso } from '@/types/faenas';

/**
 * La ficha de una faena.
 *
 * ----------------------------------------------------------------------------
 *  LA ÚNICA ACCIÓN ES ANULAR, Y EXIGE MOTIVO
 * ----------------------------------------------------------------------------
 *
 * No hay «editar»: el papel del talonario ya está en manos del pescador, y
 * cambiar el sistema sin poder cambiar el papel deja a los dos diciendo cosas
 * distintas. Tampoco hay «eliminar»: el número ya se gastó y un hueco en la
 * serie no se puede explicar después.
 *
 * El motivo es obligatorio porque es lo ÚNICO que va a quedar explicando por qué
 * ese número dejó de valer.
 */
export default function VerFaena({
    faena,
    beneficiario,
    carnet,
    pagos,
}: {
    faena: FaenaFicha;
    beneficiario: {
        id: number | null;
        nombreCompleto: string | null;
        documento_identidad: string | null;
        foto_url: string | null;
    };
    carnet: CarnetDeFaena;
    pagos: PagoDePermiso[];
}) {
    const { puede } = usePermisos();
    const [confirmando, setConfirmando] = useState(false);

    const form = useForm({ motivo: '' });

    function anular() {
        form.patch(route('faenas.anular', faena.id), {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmando(false);
                form.reset();
            },
        });
    }

    return (
        <LayoutPanel
            titulo={`Faena ${faena.nro_permiso}`}
            descripcion="Permiso por salida de pesca."
            acciones={
                faena.puede_anularse &&
                puede('faenas.anular') && (
                    <Button variant="destructive" onClick={() => setConfirmando(true)}>
                        <Ban className="size-4" />
                        Anular
                    </Button>
                )
            }
        >
            <Head title={`Faena ${faena.nro_permiso}`} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader className="flex-row items-center justify-between gap-3">
                            <CardTitle>Permiso</CardTitle>
                            <div className="flex items-center gap-2">
                                <Badge color={faena.estado_color}>{faena.estado_etiqueta}</Badge>

                                {/*
                                    «Vigente» no es lo mismo que «emitida»: mira
                                    además la ventana de fechas Y el carnet. Un
                                    carnet suspendido en marzo no deja vigentes
                                    las faenas de febrero. Lo decide el servidor.
                                */}
                                {faena.estado === 'emitido' && (
                                    <Badge color={faena.vigente ? 'emerald' : 'slate'}>
                                        {faena.vigente ? 'Autoriza hoy' : 'Fuera de fecha'}
                                    </Badge>
                                )}
                            </div>
                        </CardHeader>

                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Dato etiqueta="Nº de permiso" valor={faena.nro_permiso} mono />
                            <Dato etiqueta="Nº de recibo" valor={faena.nro_recibo} mono />
                            <Dato etiqueta="Salida" valor={fecha(faena.fecha_salida)} />
                            <Dato etiqueta="Desembarque" valor={fecha(faena.fecha_desembarque)} />
                            <Dato
                                etiqueta="Días autorizados"
                                valor={faena.dias_autorizados ? `${faena.dias_autorizados}` : null}
                            />
                            <Dato etiqueta="Cantidad autorizada" valor={faena.cantidad} destacado />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Embarcación y recorrido</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Dato etiqueta="Embarcación" valor={faena.embarcacion} />
                            <Dato etiqueta="Comandante" valor={faena.comandante_barco} />
                            <Dato etiqueta="Propietario" valor={faena.propietario} />
                            <Dato etiqueta="Matrícula naval" valor={faena.matricula_naval} mono />
                            <Dato etiqueta="Nº de kardex" valor={faena.nro_kardex} mono />
                            <Dato etiqueta="Región de salida" valor={faena.region_desde} />
                            <Dato etiqueta="Región de destino" valor={faena.region_hasta} />
                        </CardContent>
                    </Card>

                    {faena.observaciones && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Observaciones</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {/* whitespace-pre-line: el motivo de la anulación
                                    se antepone con un salto de línea. */}
                                <p className="whitespace-pre-line text-sm">{faena.observaciones}</p>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader className="flex-row items-center justify-between gap-3">
                            <CardTitle>Cobro</CardTitle>
                            <Badge color={faena.pagada ? 'emerald' : 'amber'}>
                                {faena.pagada ? 'Cubierto' : `Debe ${bs(faena.saldo)}`}
                            </Badge>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Dato etiqueta="Costo" valor={bs(faena.monto)} />
                                <Dato etiqueta="Cobrado" valor={bs(faena.monto_pagado)} />
                                <Dato etiqueta="Saldo" valor={bs(faena.saldo)} destacado />
                            </div>

                            {pagos.length === 0 ? (
                                /*
                                 * El alta de pagos de faenas todavía no tiene
                                 * pantalla: `PagoTramiteService` solo sabe de
                                 * trámites. Se dice en vez de mostrar un botón
                                 * que no existe, para que nadie lo busque.
                                 */
                                <p className="text-sm text-muted-foreground">
                                    Sin depósitos registrados.
                                </p>
                            ) : (
                                <ul className="divide-y divide-border rounded-md border border-border">
                                    {pagos.map((p) => (
                                        <li
                                            key={p.id}
                                            className="flex items-center justify-between gap-3 p-3 text-sm"
                                        >
                                            <div className="min-w-0">
                                                <p className="font-mono text-xs">{p.nro_transaccion}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {fechaHora(p.fecha_pago)}
                                                </p>
                                            </div>

                                            <span className="tabular-nums">{bs(p.monto)}</span>

                                            {p.comprobante_url && (
                                                <a
                                                    href={p.comprobante_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-primary hover:underline"
                                                >
                                                    Boleta
                                                </a>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Titular</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center gap-3">
                                <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted">
                                    {beneficiario.foto_url ? (
                                        <img
                                            src={beneficiario.foto_url}
                                            alt=""
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <User className="size-5 text-muted-foreground" />
                                    )}
                                </div>

                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {beneficiario.nombreCompleto ?? '—'}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {beneficiario.documento_identidad ?? '—'}
                                    </p>
                                </div>
                            </div>

                            {beneficiario.id && (
                                <Link
                                    href={route('beneficiarios.show', beneficiario.id)}
                                    className="text-sm text-primary hover:underline"
                                >
                                    Ver ficha del beneficiario
                                </Link>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Carnet</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center gap-3">
                                <BadgeCheck className="size-5 shrink-0 text-muted-foreground" />
                                <div className="min-w-0">
                                    <p className="font-mono text-sm">{carnet.registro ?? '—'}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {carnet.rubro} {carnet.gestion}
                                    </p>
                                </div>
                            </div>

                            {/*
                                Si el carnet dejó de valer, la faena tampoco
                                autoriza — aunque sus fechas todavía no hayan
                                pasado. Se avisa acá porque es donde el operador
                                va a buscar la explicación.
                            */}
                            {!carnet.vigente && (
                                <p className="rounded-md bg-amber-50 p-2 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                    El carnet ya no está vigente, así que esta faena tampoco autoriza.
                                </p>
                            )}

                            {carnet.id && (
                                <Link
                                    href={route('carnets.show', carnet.id)}
                                    className="text-sm text-primary hover:underline"
                                >
                                    Ver carnet
                                </Link>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Atajos</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <Link
                                href={route('faenas.index')}
                                className="flex items-center gap-2 text-primary hover:underline"
                            >
                                <Ship className="size-4" />
                                Todas las faenas
                            </Link>

                            <Link
                                href={route('pagos.index', { buscar: '' })}
                                className="flex items-center gap-2 text-primary hover:underline"
                            >
                                <Receipt className="size-4" />
                                Libro de caja
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <ConfirmarConMotivo
                abierto={confirmando}
                titulo={`Anular la faena ${faena.nro_permiso}`}
                descripcion={
                    <>
                        El número del talonario queda usado para siempre: no se puede volver a
                        emitir con él. Para reemplazar este permiso hay que sacar otro con un
                        número nuevo.
                    </>
                }
                etiquetaMotivo="Motivo de la anulación"
                ayuda="Es lo único que va a explicar después por qué este número no vale."
                placeholder="Ej.: el formulario se anuló por error de tipeo en la embarcación."
                textoConfirmar="Anular faena"
                valor={form.data.motivo}
                onCambiar={(v) => form.setData('motivo', v)}
                error={form.errors.motivo}
                procesando={form.processing}
                onConfirmar={anular}
                onCancelar={() => setConfirmando(false)}
            />
        </LayoutPanel>
    );
}

/**
 * Un par etiqueta/valor de la ficha.
 *
 * Vive acá y no en `components/ui/` porque es la forma de ESTA pantalla y de la
 * de guías; subirlo a un componente compartido antes de tener un tercer uso
 * sería inventar una abstracción para dos casos.
 */
function Dato({
    etiqueta,
    valor,
    mono = false,
    destacado = false,
}: {
    etiqueta: string;
    valor: ReactNode;
    /** Para números y códigos: se alinean mejor en monoespaciada. */
    mono?: boolean;
    destacado?: boolean;
}) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{etiqueta}</p>
            <p
                className={[
                    'mt-0.5',
                    mono ? 'font-mono text-sm' : 'text-sm',
                    destacado ? 'font-semibold' : '',
                ].join(' ')}
            >
                {valor || '—'}
            </p>
        </div>
    );
}

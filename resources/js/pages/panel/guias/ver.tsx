import { Head, Link, useForm } from '@inertiajs/react';
import { Ban, BadgeCheck, Pencil, Truck, User } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { filaVacia, GrillaDetalle } from '@/components/panel/guias/grilla-detalle';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha, fechaHora } from '@/lib/utils';
import type { OpcionEnum } from '@/types';
import type { CarnetDeFaena, PagoDePermiso } from '@/types/faenas';
import type { DetalleGuia, FilaDetalleFormulario, GuiaFicha } from '@/types/guias';

/**
 * La ficha de una guía de transporte.
 *
 * ----------------------------------------------------------------------------
 *  LA CABECERA NO SE EDITA; EL DETALLE SÍ
 * ----------------------------------------------------------------------------
 *
 * Y es la única excepción del módulo. Quién traslada, desde dónde y en qué es lo
 * que dice el papel que ya viaja con la carga: cambiarlo dejaría al sistema
 * contradiciendo al documento. La CARGA es distinta — el peso real se conoce en
 * la balanza, y ajustarlo mientras la guía vale es parte del trabajo normal.
 */
export default function VerGuia({
    guia,
    beneficiario,
    carnet,
    detalles,
    condiciones,
    pagos,
}: {
    guia: GuiaFicha;
    beneficiario: {
        id: number | null;
        nombreCompleto: string | null;
        documento_identidad: string | null;
        foto_url: string | null;
    };
    carnet: CarnetDeFaena;
    detalles: DetalleGuia[];
    condiciones: OpcionEnum[];
    pagos: PagoDePermiso[];
}) {
    const { puede } = usePermisos();
    const [anulando, setAnulando] = useState(false);
    const [editandoCarga, setEditandoCarga] = useState(false);

    const formAnular = useForm({ motivo: '' });

    /*
     * La grilla trabaja con texto —sale de <input>— así que lo guardado se
     * convierte al entrar en modo edición. `?? ''` y no `?? '0'`: un precio sin
     * cargar tiene que seguir vacío, no volverse cero al editar.
     */
    const formCarga = useForm<{ detalles: FilaDetalleFormulario[] }>({
        detalles:
            detalles.length > 0
                ? detalles.map((d) => ({
                      especie: d.especie,
                      condicion: d.condicion,
                      cantidad_kg: String(d.cantidad_kg),
                      precio_unitario: d.precio_unitario !== null ? String(d.precio_unitario) : '',
                      imponible: d.imponible !== null ? String(d.imponible) : '',
                  }))
                : [filaVacia()],
    });

    function anular() {
        formAnular.patch(route('guias.anular', guia.id), {
            preserveScroll: true,
            onSuccess: () => {
                setAnulando(false);
                formAnular.reset();
            },
        });
    }

    function guardarCarga() {
        formCarga.put(route('guias.detalle', guia.id), {
            preserveScroll: true,
            onSuccess: () => setEditandoCarga(false),
        });
    }

    return (
        <LayoutPanel
            titulo={`Guía ${guia.nro_guia}`}
            descripcion="Guía única de transporte de productos ictícolas."
            acciones={
                guia.puede_anularse &&
                puede('guias.anular') && (
                    <Button variant="destructive" onClick={() => setAnulando(true)}>
                        <Ban className="size-4" />
                        Anular
                    </Button>
                )
            }
        >
            <Head title={`Guía ${guia.nro_guia}`} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader className="flex-row items-center justify-between gap-3">
                            <CardTitle>Guía</CardTitle>
                            <div className="flex items-center gap-2">
                                <Badge color={guia.estado_color}>{guia.estado_etiqueta}</Badge>
                                <Badge color={guia.transporte_color}>{guia.transporte_etiqueta}</Badge>
                            </div>
                        </CardHeader>

                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Dato etiqueta="Nº de guía" valor={guia.nro_guia} mono />
                            <Dato etiqueta="Nº de recibo" valor={guia.nro_recibo} mono />
                            <Dato etiqueta="Emitida" valor={fecha(guia.fecha)} />
                            <Dato
                                etiqueta="Total declarado"
                                valor={`${guia.total_kg.toFixed(2)} kg`}
                                destacado
                            />
                            <Dato etiqueta="Origen" valor={guia.origen.completo} />
                            <Dato etiqueta="Destino" valor={guia.destino.completo} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Transporte</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Dato etiqueta="Empresa o transportista" valor={guia.transporte_nombre} />
                            <Dato
                                etiqueta={guia.rotulo_identificacion}
                                valor={guia.transporte_placa}
                                mono
                            />
                            <Dato
                                etiqueta="Capacidad"
                                valor={
                                    guia.capacidad_maxima !== null
                                        ? `${guia.capacidad_maxima.toFixed(2)} kg`
                                        : null
                                }
                            />

                            {/*
                                Solo se avisa cuando SÍ excede. `excede_capacidad`
                                es null si no hay capacidad cargada —de una canoa
                                nadie la sabe— y ahí no hay nada que decir.
                            */}
                            {guia.excede_capacidad && (
                                <p className="rounded-md bg-amber-50 p-2 text-xs text-amber-700 sm:col-span-2 dark:bg-amber-500/10 dark:text-amber-300">
                                    La carga declarada supera la capacidad del transporte.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between gap-3">
                            <CardTitle>Carga</CardTitle>

                            {guia.puede_editar_detalle && puede('guias.crear') && !editandoCarga && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setEditandoCarga(true)}
                                >
                                    <Pencil className="size-4" />
                                    Corregir
                                </Button>
                            )}
                        </CardHeader>

                        <CardContent>
                            {editandoCarga ? (
                                <div className="space-y-4">
                                    <GrillaDetalle
                                        filas={formCarga.data.detalles}
                                        condiciones={condiciones}
                                        onCambiar={(filas) => formCarga.setData('detalles', filas)}
                                        errores={formCarga.errors as unknown as Record<string, string>}
                                        deshabilitado={formCarga.processing}
                                    />

                                    <div className="flex justify-end gap-3">
                                        <Button
                                            variant="ghost"
                                            onClick={() => setEditandoCarga(false)}
                                            disabled={formCarga.processing}
                                        >
                                            Cancelar
                                        </Button>
                                        <Button onClick={guardarCarga} disabled={formCarga.processing}>
                                            {formCarga.processing ? 'Guardando…' : 'Guardar carga'}
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="py-2 pr-3 font-medium">Especie</th>
                                                <th className="py-2 pr-3 font-medium">Condición</th>
                                                <th className="py-2 pr-3 text-right font-medium">Kg</th>
                                                <th className="py-2 pr-3 text-right font-medium">
                                                    Precio unit.
                                                </th>
                                                <th className="py-2 text-right font-medium">Importe</th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {detalles.map((d) => (
                                                <tr key={d.id}>
                                                    <td className="py-2 pr-3 font-medium">{d.especie}</td>
                                                    <td className="py-2 pr-3">
                                                        <Badge color={d.condicion_color}>
                                                            {d.condicion_etiqueta}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-2 pr-3 text-right tabular-nums">
                                                        {d.cantidad_kg.toFixed(2)}
                                                    </td>
                                                    <td className="py-2 pr-3 text-right tabular-nums text-muted-foreground">
                                                        {d.precio_unitario !== null
                                                            ? bs(d.precio_unitario)
                                                            : '—'}
                                                    </td>
                                                    <td className="py-2 text-right tabular-nums">
                                                        {bs(d.importe)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>

                                        <tfoot className="border-t border-border">
                                            <tr>
                                                <td
                                                    className="py-2 pr-3 text-xs uppercase tracking-wide text-muted-foreground"
                                                    colSpan={2}
                                                >
                                                    Total
                                                </td>
                                                <td className="py-2 pr-3 text-right font-semibold tabular-nums">
                                                    {guia.total_kg.toFixed(2)}
                                                </td>
                                                <td />
                                                <td className="py-2 text-right font-semibold tabular-nums">
                                                    {bs(guia.monto_requerido ?? 0)}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {guia.observaciones && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Observaciones</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {/* whitespace-pre-line: el motivo de la anulación
                                    se antepone con un salto de línea. */}
                                <p className="whitespace-pre-line text-sm">{guia.observaciones}</p>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Cobro</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Dato
                                    etiqueta="Importe de la carga"
                                    valor={bs(guia.monto_requerido ?? 0)}
                                />
                                <Dato etiqueta="Cobrado" valor={bs(guia.monto_pagado)} destacado />
                            </div>

                            {pagos.length === 0 ? (
                                /*
                                 * El alta de pagos de guías todavía no tiene
                                 * pantalla: `PagoTramiteService` solo sabe de
                                 * trámites. Se dice en vez de mostrar un botón
                                 * que no existe.
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

                            {!carnet.vigente && (
                                <p className="rounded-md bg-amber-50 p-2 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                    El carnet ya no está vigente, así que esta guía tampoco ampara el
                                    traslado.
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
                        <CardContent className="text-sm">
                            <Link
                                href={route('guias.index')}
                                className="flex items-center gap-2 text-primary hover:underline"
                            >
                                <Truck className="size-4" />
                                Todas las guías
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <ConfirmarConMotivo
                abierto={anulando}
                titulo={`Anular la guía ${guia.nro_guia}`}
                descripcion={
                    <>
                        El número del talonario queda usado para siempre. El detalle de la carga
                        NO se borra: es lo que hay que poder mirar si alguien reclama.
                    </>
                }
                etiquetaMotivo="Motivo de la anulación"
                ayuda="Es lo único que va a explicar después por qué este número no vale."
                placeholder="Ej.: el traslado no se realizó y la carga volvió al depósito."
                textoConfirmar="Anular guía"
                valor={formAnular.data.motivo}
                onCambiar={(v) => formAnular.setData('motivo', v)}
                error={formAnular.errors.motivo}
                procesando={formAnular.processing}
                onConfirmar={anular}
                onCancelar={() => setAnulando(false)}
            />
        </LayoutPanel>
    );
}

/** Un par etiqueta/valor. Ver la nota en `faenas/ver.tsx`. */
function Dato({
    etiqueta,
    valor,
    mono = false,
    destacado = false,
}: {
    etiqueta: string;
    valor: ReactNode;
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

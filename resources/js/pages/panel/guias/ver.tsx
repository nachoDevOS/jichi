import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Ban, CalendarX, CheckCheck, Truck, User } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { Input } from '@/components/ui/input';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fechaHora } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { GuiaFicha } from '@/types/guias';

/**
 * ============================================================================
 *  LA FICHA DE UNA GUÍA
 * ============================================================================
 *
 * ----------------------------------------------------------------------------
 *  CERRAR Y ANULAR NO SON LO MISMO, Y LA PANTALLA LO DICE
 * ----------------------------------------------------------------------------
 *
 * CERRAR es registrar que la carga llegó: el traslado ocurrió y esta guía lo
 * amparó. ANULAR es decir que el papel nunca valió.
 *
 * Por eso una guía CERRADA ya no se puede anular —lo dice `puede_anularse`, que
 * llega resuelto del servidor—: anularla declararía que nunca amparó nada y
 * dejaría un viaje real sin ningún respaldo.
 *
 * Las fechas van con `fechaHora()`: los cinco días se cuentan desde el instante
 * de emisión, así que la hora es el dato que decide la vigencia.
 */
export default function VerGuia({ guia }: { guia: GuiaFicha }) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [cerrando, setCerrando] = useState(false);
    const [anulando, setAnulando] = useState(false);

    const cierre = useForm({ peso_total_kg: String(guia.peso_total_kg) });
    const baja = useForm({ motivo: '' });

    return (
        <LayoutPanel
            titulo={`Guía ${guia.codigo_guia}`}
            descripcion={`${guia.comercializador ?? '—'} · ${guia.ruta}`}
            acciones={
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        onClick={() => router.visit(route('beneficiarios.show', guia.beneficiario_id))}
                    >
                        <User className="size-4" />
                        Ver al comercializador
                    </Button>

                    {puede('guias.cerrar') && guia.puede_cerrarse && (
                        <Button onClick={() => setCerrando((v) => !v)}>
                            <CheckCheck className="size-4" />
                            Registrar llegada
                        </Button>
                    )}

                    {puede('guias.anular') && guia.puede_anularse && (
                        <Button variant="destructive" onClick={() => setAnulando(true)}>
                            <Ban className="size-4" />
                            Anular
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={`Guía ${guia.codigo_guia}`} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {cerrando && guia.puede_cerrarse && (
                        <Card className="border-emerald-300 bg-emerald-50/50 dark:border-emerald-500/40 dark:bg-emerald-500/5">
                            <CardHeader>
                                <CardTitle>Registrar llegada</CardTitle>
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
                                        ayuda="Viene con lo declarado al salir. Corríjalo si la balanza del destino dijo otra cosa: acá no hay cupo que exceder."
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
                            <CardTitle>El traslado</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            <Situacion guia={guia} />

                            <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                                <Dato etiqueta="Origen" valor={guia.origen} />
                                <Dato etiqueta="Destino" valor={guia.destino} />
                                <Dato etiqueta="Carga" valor={`${guia.peso_total_kg} kg`} />
                                <Dato
                                    etiqueta="Producto"
                                    valor={guia.es_piscicultura ? 'Piscicultura' : 'De río'}
                                />
                                <Dato etiqueta="Emitida" valor={fechaHora(guia.fecha_emision)} />
                                <Dato etiqueta="Vence" valor={fechaHora(guia.fecha_vencimiento)} />
                                <Dato etiqueta="Asociación" valor={guia.asociacion_nombre ?? '—'} />
                                <Dato etiqueta="Documento" valor={guia.documento ?? '—'} />
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Arancel</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-3 text-sm">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Monto</span>
                            <span className="font-medium tabular-nums">
                                {bs(guia.monto, institucion.moneda)}
                            </span>
                        </div>

                        {/*
                            Se dice explícitamente que se cobró la mitad, y por
                            qué. El monto solo no lo explica: quien mire la ficha
                            dentro de seis meses no va a saber si es un error o
                            una tarifa distinta.
                        */}
                        {guia.es_piscicultura && (
                            <p className="rounded-md bg-sky-50 p-3 text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">
                                Se cobró al {Math.round(guia.factor_arancel * 100)}% por ser producto de{' '}
                                <strong>piscicultura</strong>: el criadero no saca del río, así que no
                                consume el recurso que la tasa protege.
                            </p>
                        )}

                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Cobro</span>
                            {guia.pagado ? (
                                <span className="font-medium text-emerald-700 dark:text-emerald-400">
                                    Pagado
                                </span>
                            ) : (
                                <span className="font-medium text-amber-700 dark:text-amber-400">
                                    debe {bs(guia.saldo_pendiente, institucion.moneda)}
                                </span>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <ConfirmarConMotivo
                abierto={anulando}
                titulo="¿Anular la guía?"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El papel deja de amparar el traslado al instante. El código{' '}
                            <strong>{guia.codigo_guia}</strong> queda ocupado para siempre: la hoja del
                            talonario se gastó.
                        </p>
                        <p>No se desanula. Si hace falta, se emite otra con otro código.</p>
                    </div>
                }
                etiquetaMotivo="Motivo de la anulación"
                ayuda="Queda en la auditoría con su nombre. Deja un hueco en la serie que alguien va a tener que explicar."
                placeholder="Se emitió con el destino equivocado; se reemplaza por la guía GUI-2026-0042."
                textoConfirmar="Anular guía"
                confirmacion="Entiendo que el código queda quemado y que esto no se puede revertir."
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
 *
 * El caso que importa es el del medio: una guía que se pasó de hora y sigue
 * activa es un camión en la ruta con un papel que ya no vale. No es una
 * previsión — es algo que hay que resolver ahora.
 */
function Situacion({ guia }: { guia: GuiaFicha }) {
    if (guia.estado === 'anulada') {
        return (
            <Marco
                clase="bg-rose-50 text-rose-900 dark:bg-rose-500/10 dark:text-rose-200"
                icono={<Ban className="mt-0.5 size-5 shrink-0" />}
                titulo="Anulada"
                texto="Este papel no ampara ningún traslado. El código queda ocupado: la hoja del talonario se gastó."
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
            <dd className="font-medium">{valor}</dd>
        </div>
    );
}

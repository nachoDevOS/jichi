import { Head, Link, router, useForm } from '@inertiajs/react';
import { Ban, IdCard, ShieldOff, ShieldCheck, User } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { CodigoQr } from '@/components/comunes/codigo-qr';
import { DialogoImprimirCarnet } from '@/components/panel/carnets/dialogo-imprimir-carnet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { CarnetFicha, Habilitacion, TitularCarnet, TramiteDelCarnet } from '@/types/carnets';

/**
 * La ficha del carnet: lo que se imprime y lo que se sanciona.
 *
 * LA FIRMA ES EL IDENTIFICADOR DEL CARNET: no hay columna `codigo`. NO va
 * impresa en el plástico —viaja solo dentro del QR— y se muestra en ESTA
 * pantalla y en ninguna otra, por un motivo concreto: si el QR de alguien queda
 * ilegible, esta es la única forma de recuperarla y dictársela para que pueda
 * verificar. Lo que sí va impreso es el número de REGISTRO, que es público y no
 * abre nada. Ver Carnet::registro().
 */
export default function VerCarnet({
    carnet,
    beneficiario,
    habilitaciones,
    tramites,
}: {
    carnet: CarnetFicha;
    beneficiario: TitularCarnet;
    habilitaciones: Habilitacion[];
    tramites: TramiteDelCarnet[];
}) {
    const { puede } = usePermisos();
    const [anulando, setAnulando] = useState(false);
    const [imprimiendo, setImprimiendo] = useState(false);

    return (
        <LayoutPanel
            titulo={`Carnet ${carnet.gestion}`}
            descripcion={`Gestión ${carnet.gestion} · ${beneficiario.nombreCompleto ?? '—'}`}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {/*
                        IMPRIMIR EL PLÁSTICO.

                        Abre la VISTA PREVIA, no el PDF directo: lo que se
                        imprime es un plástico que se troquela y se lamina, y no
                        se corrige. Ver DialogoImprimirCarnet.

                        Se puede volver a sacar todas las veces que haga falta:
                        el carnet se pierde, se moja y se rompe, y la reimpresión
                        sale idéntica —mismo registro, misma firma dentro del
                        QR—. Esconder el botón después de la primera vez sería
                        justamente quitarlo en el único caso en que se necesita.

                        Un carnet VENCIDO también se imprime: es el documento que
                        existió, y la verificación pública ya avisa que caducó.
                        El que no se imprime es el ANULADO, y eso lo decide
                        `puede_imprimirse` en el servidor.
                    */}
                    {carnet.puede_imprimirse && puede('carnets.generar') && (
                        <Button variant="dorado" onClick={() => setImprimiendo(true)}>
                            <IdCard className="size-4" />
                            Imprimir carnet
                        </Button>
                    )}

                    {puede('carnets.anular') && carnet.estado !== 'anulado' && (
                        <Button variant="destructive" onClick={() => setAnulando((v) => !v)}>
                            <Ban className="size-4" />
                            Anular carnet
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={`Carnet ${carnet.gestion}`} />

            <DialogoImprimirCarnet
                abierto={imprimiendo}
                carnetId={carnet.id}
                registro={carnet.registro}
                onCerrar={() => setImprimiendo(false)}
            />

            {anulando && <FormularioAnulacion carnetId={carnet.id} onCancelar={() => setAnulando(false)} />}

            <div className="grid gap-6 lg:grid-cols-3">
                {/* ---------------------------------------------------- El carnet */}
                <Card className="h-fit">
                    <CardContent className="flex flex-col items-center gap-4 pt-5 text-center">
                        <div className="flex size-28 items-center justify-center overflow-hidden rounded-full bg-muted">
                            {beneficiario.foto_url ? (
                                <img
                                    src={beneficiario.foto_url}
                                    alt={beneficiario.nombreCompleto ?? ''}
                                    className="size-full object-cover"
                                />
                            ) : (
                                <User className="size-10 text-muted-foreground" />
                            )}
                        </div>

                        <div>
                            <p className="font-semibold">{beneficiario.nombreCompleto}</p>
                            <p className="text-sm text-muted-foreground">
                                {beneficiario.documento_identidad}
                            </p>
                        </div>

                        <Badge color={carnet.vigente ? 'emerald' : carnet.estado_color}>
                            {carnet.vigente ? 'Vigente' : carnet.estado_etiqueta}
                        </Badge>

                        {/*
                            El QR codifica la URL de verificación, que lleva solo
                            la firma. Se recibe hecha del servidor y no se compone
                            acá: el dominio público no es el de la red
                            departamental.
                        */}
                        <CodigoQr url={carnet.url_verificacion} codigo={carnet.firma} tamano={148} />

                        {/*
                            EL REGISTRO es el número impreso en el plástico, y es
                            por el que pregunta la gente en ventanilla. Va primero
                            y grande porque es el que se busca al cruzar esta
                            pantalla con la credencial que el titular trae.
                        */}
                        <div className="w-full space-y-1 text-sm">
                            <p className="text-xs text-muted-foreground">Registro</p>
                            <p className="font-mono text-lg font-semibold tabular-nums">
                                {carnet.registro}
                            </p>
                        </div>

                        {/*
                            LA FIRMA NO VA IMPRESA EN EL CARNET: viaja solo dentro
                            del QR. Se muestra acá, y en ninguna otra pantalla del
                            panel, porque es la única salida cuando el QR quedó
                            rayado o mojado: el funcionario la lee y se la dicta al
                            titular para que pueda verificar desde su teléfono.

                            En grupos de cuatro y monoespaciada, que es como se
                            dicta sin confundir el 0 con la O ni el 1 con la l.
                        */}
                        <div className="w-full space-y-1 text-sm">
                            <p className="text-xs text-muted-foreground">
                                Firma de validación · solo si el QR no se puede leer
                            </p>
                            <p className="font-mono text-sm font-semibold tracking-wider">
                                {carnet.firma}
                            </p>
                        </div>

                        <div className="w-full space-y-1 border-t border-border pt-3 text-left text-sm">
                            <Dato etiqueta="Emitido" valor={fecha(carnet.fecha_emision)} />
                            <Dato etiqueta="Vence" valor={fecha(carnet.fecha_vencimiento)} />
                        </div>
                    </CardContent>
                </Card>

                {/* ----------------------------------------------- Rubros e historial */}
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Rubros habilitados</CardTitle>
                        </CardHeader>

                        <CardContent>
                            {habilitaciones.length === 0 ? (
                                <p className="py-4 text-sm text-muted-foreground">
                                    El carnet no tiene ningún rubro habilitado. Se habilitan al aprobar
                                    cada trámite.
                                </p>
                            ) : (
                                <ul className="divide-y divide-border">
                                    {habilitaciones.map((h) => (
                                        <li key={h.id} className="flex flex-wrap items-center gap-3 py-3">
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium">{h.rubro}</p>
                                                <p className="text-sm text-muted-foreground">
                                                    Habilitado el {fecha(h.fecha_habilitacion)}
                                                    {/*
                                                        El cupo va acá y no en el
                                                        carnet impreso: el plástico
                                                        no lo lleva. Esta ficha es
                                                        donde la unidad lo consulta.

                                                        Se compara contra null y no
                                                        con un truthy suelto: un
                                                        cupo de 0 es falsy y
                                                        desaparecería del renglón
                                                        sin que nadie lo note.
                                                    */}
                                                    {h.capacidad_kg !== null && (
                                                        <> · Cupo {h.capacidad_kg} Kg</>
                                                    )}
                                                </p>
                                            </div>

                                            <Badge color={h.estado_color}>{h.estado_etiqueta}</Badge>

                                            {puede('habilitaciones.suspender') && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(route('habilitaciones.alternar', h.id))
                                                    }
                                                >
                                                    {h.estado === 'habilitado' ? (
                                                        <>
                                                            <ShieldOff className="size-4" />
                                                            Suspender
                                                        </>
                                                    ) : (
                                                        <>
                                                            <ShieldCheck className="size-4" />
                                                            Rehabilitar
                                                        </>
                                                    )}
                                                </Button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Trámites del carnet</CardTitle>
                        </CardHeader>

                        <CardContent>
                            {tramites.length === 0 ? (
                                <p className="py-4 text-sm text-muted-foreground">Sin trámites.</p>
                            ) : (
                                <ul className="divide-y divide-border text-sm">
                                    {tramites.map((t) => (
                                        <li key={t.id} className="flex flex-wrap items-center gap-3 py-2">
                                            <Link
                                                href={route('tramites.show', t.id)}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {t.rubro ?? `Trámite #${t.id}`}
                                            </Link>

                                            <Badge color={t.estado_color}>{t.estado_etiqueta}</Badge>

                                            <span className="text-muted-foreground">{t.tipo_etiqueta}</span>

                                            <span className="ml-auto tabular-nums text-muted-foreground">
                                                {bs(t.monto_requerido)} · {fecha(t.fecha_solicitud)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </LayoutPanel>
    );
}

/**
 * Anular es definitivo, y además BLOQUEA LA GESTIÓN: el carnet anulado sigue
 * ocupando su lugar en el año, así que la persona no puede sacar otro hasta la
 * gestión siguiente. El aviso lo dice para que nadie lo use como «reposición».
 */
function FormularioAnulacion({ carnetId, onCancelar }: { carnetId: number; onCancelar: () => void }) {
    const form = useForm({ motivo: '' });

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.post(route('carnets.anular', carnetId));
    }

    return (
        <Card className="mb-6 border-rose-300 dark:border-rose-500/40">
            <CardContent className="pt-5">
                <form onSubmit={enviar} className="space-y-3">
                    <p className="text-sm text-muted-foreground">
                        El carnet anulado <strong>sigue ocupando la gestión</strong>: el beneficiario no
                        podrá sacar otro este año. Anular es una sanción, no un trámite de reposición.
                    </p>

                    <Campo
                        etiqueta="Motivo de la anulación"
                        htmlFor="motivo"
                        obligatorio
                        error={form.errors.motivo}
                    >
                        <Textarea
                            id="motivo"
                            rows={3}
                            value={form.data.motivo}
                            onChange={(e) => form.setData('motivo', e.target.value)}
                        />
                    </Campo>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="ghost" onClick={onCancelar}>
                            Cancelar
                        </Button>
                        <Button type="submit" variant="destructive" disabled={form.processing}>
                            Confirmar anulación
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string | null | undefined }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted-foreground">{etiqueta}</span>
            <span className="font-medium">{valor || '—'}</span>
        </div>
    );
}

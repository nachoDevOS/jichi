import { Head, Link, router, useForm } from '@inertiajs/react';
import { Ban, IdCard, Plus, ShieldOff, ShieldCheck, Ship, Truck, User } from 'lucide-react';
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
import type {
    CarnetFicha,
    FaenaDelCarnet,
    GuiaDelCarnet,
    TitularCarnet,
    TramiteDelCarnet,
} from '@/types/carnets';

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
    tramites,
    faenas,
    guias,
}: {
    carnet: CarnetFicha;
    beneficiario: TitularCarnet;
    tramites: TramiteDelCarnet[];
    /** Las últimas 10. El total está en `carnet.total_faenas`. */
    faenas: FaenaDelCarnet[];
    guias: GuiaDelCarnet[];
}) {
    const { puede } = usePermisos();
    const [anulando, setAnulando] = useState(false);
    const [imprimiendo, setImprimiendo] = useState(false);

    return (
        <LayoutPanel
            // El rubro va en el TÍTULO y no en la descripción: una persona
            // puede tener tres carnets del mismo año y sin la actividad las tres
            // pestañas del navegador se llaman igual.
            titulo={`Carnet de ${carnet.rubro ?? '—'} · ${carnet.gestion}`}
            descripcion={beneficiario.nombreCompleto ?? '—'}
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
            {/* El rubro también en la pestaña: una persona puede tener tres carnets
                del mismo año abiertos a la vez. */}
            <Head title={`Carnet de ${carnet.rubro ?? "—"} ${carnet.gestion}`} />

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

                {/* ------------------------------------ Autorización e historial */}
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Actividad autorizada</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            {/*
                                ACÁ HABÍA UNA LISTA, Y AHORA HAY UN DATO.
                                El carnet habilita UNA actividad —es parte de la
                                llave que lo identifica— así que no hay nada que
                                recorrer: quien tiene dos rubros tiene dos carnets,
                                cada uno con su ficha como esta.
                            */}
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-lg font-semibold">{carnet.rubro ?? '—'}</p>

                                    {carnet.rubro_descripcion && (
                                        <p className="text-sm text-muted-foreground">
                                            {carnet.rubro_descripcion}
                                        </p>
                                    )}
                                </div>

                                <Badge color={carnet.estado_color}>{carnet.estado_etiqueta}</Badge>
                            </div>

                            {/*
                                EL CUPO AUTORIZADO, SOLO SI LA ACTIVIDAD LO LLEVA.

                                No todas se autorizan por volumen: la pesca sí, la
                                comercialización no. Mostrar «Cupo autorizado: sin
                                definir» en un carnet de Comercializador no informa
                                nada y sugiere que falta cargar un dato que no
                                existe — y alguien va a ir a buscar cómo cargarlo.

                                Cuando sí lleva, se compara contra null y no con un
                                truthy suelto: un cupo de 0 es falsy y
                                desaparecería del renglón sin que nadie lo note.
                            */}
                            {carnet.requiere_capacidad && (
                                <div className="rounded-md border border-border p-3">
                                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Cupo autorizado
                                    </p>
                                    <p className="text-lg font-semibold tabular-nums">
                                        {carnet.capacidad_kg !== null
                                            ? carnet.capacidad
                                            : 'Sin definir'}
                                    </p>
                                </div>
                            )}

                            {/*
                                SUSPENDER O LEVANTAR.

                                Reemplaza al botón que estaba en cada fila de
                                rubros. La medida sigue siendo por actividad: este
                                carnet es una, y los otros carnets de la persona no
                                se enteran.

                                Los dos `puede_*` los decide el servidor
                                (EstadoCarnet), no esta pantalla: un carnet vencido
                                o anulado no se suspende ni se levanta, y dejar el
                                botón a la vista sería ofrecer algo que va a
                                rebotar.
                            */}
                            {puede('carnets.suspender') &&
                                (carnet.puede_suspenderse || carnet.puede_rehabilitarse) && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => router.post(route('carnets.suspender', carnet.id))}
                                    >
                                        {carnet.puede_suspenderse ? (
                                            <>
                                                <ShieldOff className="size-4" />
                                                Suspender el carnet
                                            </>
                                        ) : (
                                            <>
                                                <ShieldCheck className="size-4" />
                                                Levantar la suspensión
                                            </>
                                        )}
                                    </Button>
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

                    {/*
                        LOS PERMISOS OPERATIVOS QUE CUELGAN DEL CARNET.

                        La sección se muestra según lo que el RUBRO emita
                        —`emite_faenas`— y no según si hoy se puede emitir: un
                        carnet vencido ya no emite, pero sigue teniendo que
                        mostrar lo que emitió en su momento.

                        El BOTÓN, en cambio, mira `puede_emitir_*`, que incluye
                        la vigencia.
                    */}
                    {carnet.emite_faenas && (
                        <Card>
                            <CardHeader className="flex-row items-center justify-between gap-3">
                                <CardTitle>
                                    Faenas
                                    {carnet.total_faenas > 0 && (
                                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                                            {carnet.total_faenas}
                                        </span>
                                    )}
                                </CardTitle>

                                {carnet.puede_emitir_faenas && puede('faenas.crear') && (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.visit(route('faenas.create', { carnet: carnet.id }))
                                        }
                                    >
                                        <Plus className="size-4" />
                                        Nueva faena
                                    </Button>
                                )}
                            </CardHeader>

                            <CardContent>
                                {faenas.length === 0 ? (
                                    <p className="py-4 text-sm text-muted-foreground">
                                        Sin faenas emitidas.
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-border text-sm">
                                        {faenas.map((f) => (
                                            <li
                                                key={f.id}
                                                className="flex flex-wrap items-center gap-3 py-2"
                                            >
                                                <Link
                                                    href={route('faenas.show', f.id)}
                                                    className="font-mono text-xs text-primary hover:underline"
                                                >
                                                    {f.nro_permiso}
                                                </Link>

                                                <Badge color={f.estado_color}>{f.estado_etiqueta}</Badge>

                                                <span className="text-muted-foreground">
                                                    {f.embarcacion ?? '—'}
                                                </span>

                                                <span className="ml-auto tabular-nums text-muted-foreground">
                                                    {f.cantidad ?? '—'} · {fecha(f.fecha_salida)}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                {/* El listado completo, cuando hay más de las
                                    diez que trae la ficha. */}
                                {carnet.total_faenas > faenas.length && (
                                    <Link
                                        href={route('faenas.index', { buscar: carnet.registro })}
                                        className="mt-3 inline-flex items-center gap-2 text-sm text-primary hover:underline"
                                    >
                                        <Ship className="size-4" />
                                        Ver las {carnet.total_faenas}
                                    </Link>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {carnet.emite_guias && (
                        <Card>
                            <CardHeader className="flex-row items-center justify-between gap-3">
                                <CardTitle>
                                    Guías de transporte
                                    {carnet.total_guias > 0 && (
                                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                                            {carnet.total_guias}
                                        </span>
                                    )}
                                </CardTitle>

                                {carnet.puede_emitir_guias && puede('guias.crear') && (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.visit(route('guias.create', { carnet: carnet.id }))
                                        }
                                    >
                                        <Plus className="size-4" />
                                        Nueva guía
                                    </Button>
                                )}
                            </CardHeader>

                            <CardContent>
                                {guias.length === 0 ? (
                                    <p className="py-4 text-sm text-muted-foreground">
                                        Sin guías emitidas.
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-border text-sm">
                                        {guias.map((g) => (
                                            <li
                                                key={g.id}
                                                className="flex flex-wrap items-center gap-3 py-2"
                                            >
                                                <Link
                                                    href={route('guias.show', g.id)}
                                                    className="font-mono text-xs text-primary hover:underline"
                                                >
                                                    {g.nro_guia}
                                                </Link>

                                                <Badge color={g.estado_color}>{g.estado_etiqueta}</Badge>

                                                <span className="text-muted-foreground">
                                                    {g.transporte_etiqueta} · {g.destino_lugar ?? '—'}
                                                </span>

                                                <span className="ml-auto tabular-nums text-muted-foreground">
                                                    {g.total_kg.toFixed(2)} kg · {fecha(g.fecha)}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                {carnet.total_guias > guias.length && (
                                    <Link
                                        href={route('guias.index', { buscar: carnet.registro })}
                                        className="mt-3 inline-flex items-center gap-2 text-sm text-primary hover:underline"
                                    >
                                        <Truck className="size-4" />
                                        Ver las {carnet.total_guias}
                                    </Link>
                                )}
                            </CardContent>
                        </Card>
                    )}
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

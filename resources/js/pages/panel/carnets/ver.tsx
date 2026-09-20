import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Ban, Printer, Ship, Truck, User, Waves } from 'lucide-react';
import { useState } from 'react';
import { BarraSaldo } from '@/components/panel/aprovechamientos/barra-saldo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { CarnetFicha } from '@/types/carnets';

/**
 *  LA FICHA DE UN CARNET
 */
export default function VerCarnet({ carnet }: { carnet: CarnetFicha }) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [revocando, setRevocando] = useState(false);

    const form = useForm({ motivo: '' });

    return (
        <LayoutPanel
            titulo={carnet.beneficiario ?? 'Carnet'}
            descripcion={`${carnet.tipo_actor_etiqueta} · ${carnet.codigo}`}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {/*
                        IMPRIMIR abre en una pestaña aparte y no en un iframe: con
                        un PDF, `iframe.onLoad` no dispara nunca —medido— así que
                        un «cargando…» que dependa de él se queda colgado, y
                        `contentWindow.print()` sobre un PDF lo ignora o lo bloquea
                        según el navegador. La barra del visor propio funciona.
                    */}
                    {puede('carnets.imprimir') && (
                        <a href={route('carnets.imprimir', carnet.id)} target="_blank" rel="noopener">
                            <Button variant="dorado">
                                <Printer className="size-4" />
                                Imprimir carnet
                            </Button>
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

                    <Button
                        variant="ver"
                        onClick={() => router.visit(route('beneficiarios.show', carnet.beneficiario_id))}
                    >
                        <User className="size-4" />
                        Ver al titular
                    </Button>

                    {/* No se revoca dos veces, y la revocación no se revierte. */}
                    {puede('carnets.revocar') && carnet.estado !== 'revocado' && (
                        <Button variant="eliminar" onClick={() => setRevocando(true)}>
                            <Ban className="size-4" />
                            Revocar
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={`Carnet ${carnet.codigo}`} />

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
                            razon={razonDeBloqueo(carnet)}
                        />

                        {/*
                            El cupo solo aparece si el carnet lo lleva. En un
                            comercializador no es que «falte»: la comercialización
                            no se autoriza por volumen.
                        */}
                        {carnet.cupo && (
                            <div className="space-y-2 rounded-md border border-border p-4">
                                <div className="flex items-center gap-2">
                                    <Waves className="size-4 text-muted-foreground" />
                                    <span className="text-sm font-medium">Cupo de pesca</span>

                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="ml-auto"
                                        onClick={() =>
                                            router.visit(route('aprovechamientos.show', carnet.cupo!.id))
                                        }
                                    >
                                        Ver cupo
                                    </Button>
                                </div>

                                <BarraSaldo cupo={carnet.cupo} />
                            </div>
                        )}
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

                        <Dato etiqueta="Código" valor={carnet.codigo} mono />
                        <Dato etiqueta="Documento" valor={carnet.documento_identidad ?? '—'} mono />
                        <Dato etiqueta="Tipo" valor={carnet.tipo ?? '—'} />
                        <Dato etiqueta="Asociación" valor={carnet.asociacion_nombre ?? '—'} />
                        <Dato etiqueta="Emitido el" valor={fecha(carnet.fecha_emision)} />
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

            {/*
                REVOCAR PIDE MOTIVO. Es una sanción, no se revierte, y la
                verificación pública empieza a informarla al instante: sin el
                motivo, dentro de seis meses nadie puede explicar por qué esa
                persona perdió su credencial.
            */}
            <ConfirmarConMotivo
                abierto={revocando}
                titulo="¿Revocar el carnet?"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El plástico deja de valer al instante y la verificación pública va a
                            informarlo como <strong>REVOCADO</strong> a quien lo consulte.
                        </p>
                        <p>
                            No se revierte. Si la persona vuelve a estar en regla, hay que emitirle un
                            carnet nuevo con otro código —el viejo pudo quedar en manos de cualquiera—.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la revocación"
                ayuda="Queda en la auditoría con su nombre."
                placeholder="Infracción constatada en acta 18/2026: pesca fuera de temporada."
                textoConfirmar="Revocar carnet"
                confirmacion="Entiendo que el carnet deja de valer y que esto no se puede revertir."
                valor={form.data.motivo}
                onCambiar={(v) => form.setData('motivo', v)}
                error={form.errors.motivo}
                procesando={form.processing}
                onCancelar={() => setRevocando(false)}
                onConfirmar={() =>
                    form.patch(route('carnets.revocar', carnet.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setRevocando(false);
                            form.reset();
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
function razonDeBloqueo(carnet: CarnetFicha): string | null {
    if (carnet.estado === 'revocado') {
        return 'El carnet está revocado.';
    }

    if (!carnet.vigente) {
        return 'El carnet no está vigente: pasó su fecha de vencimiento.';
    }

    if (carnet.tipo_actor === 'pescador') {
        if (carnet.cupo === null) {
            return 'No tiene una bolsa madre asociada.';
        }

        if (!carnet.cupo.vigente) {
            return 'El cupo de pesca no está vigente.';
        }

        if (carnet.cupo.saldo_kg <= 0) {
            return 'El cupo de pesca no tiene kilos disponibles. Hay que tramitar otro.';
        }
    }

    return null;
}

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

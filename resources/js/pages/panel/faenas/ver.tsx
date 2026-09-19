import { Head, router, useForm } from '@inertiajs/react';
import { BadgeCheck, CalendarX, CheckCheck, Waves } from 'lucide-react';
import { useState } from 'react';
import { BarraSaldo } from '@/components/panel/aprovechamientos/barra-saldo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { fecha } from '@/lib/utils';
import type { FaenaFicha } from '@/types/faenas';

/**
 * ============================================================================
 *  LA FICHA DE UNA FAENA
 * ============================================================================
 *
 * ----------------------------------------------------------------------------
 *  CERRAR LA FAENA ES LA ACCIÓN, Y ESTÁ ARRIBA DE TODO
 * ----------------------------------------------------------------------------
 *
 * Quien abre esta pantalla casi siempre viene porque el pescador volvió. Por
 * eso el formulario de cierre es lo primero, con los kilos declarados ya
 * puestos: en la mayoría de los casos coinciden con la balanza y alcanza con
 * confirmar.
 *
 * ----------------------------------------------------------------------------
 *  COMPLETAR NO CAMBIA EL SALDO, Y ESO SORPRENDE
 * ----------------------------------------------------------------------------
 *
 * Los kilos ya estaban descontados desde que la faena se emitió: una faena
 * ACTIVA consume cupo aunque no se haya descargado nada. Si solo contaran las
 * completadas, un pescador podría tener diez faenas abiertas por el volumen
 * entero cada una.
 *
 * Lo que sí mueve el saldo es CORREGIR los kilos al cerrar.
 */
export default function VerFaena({ faena }: { faena: FaenaFicha }) {
    const { puede } = usePermisos();
    const [cerrando, setCerrando] = useState(false);

    const form = useForm({ kilos_extraidos: String(faena.kilos_extraidos) });

    return (
        <LayoutPanel
            titulo={faena.etiqueta}
            descripcion={`${faena.beneficiario ?? '—'} · ${faena.carnet_codigo ?? ''}`}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {faena.beneficiario_id !== null && (
                        <Button
                            variant="outline"
                            onClick={() => router.visit(route('beneficiarios.show', faena.beneficiario_id!))}
                        >
                            Ver al pescador
                        </Button>
                    )}

                    {faena.cupo && (
                        <Button
                            variant="outline"
                            onClick={() => router.visit(route('aprovechamientos.show', faena.cupo!.id))}
                        >
                            <Waves className="size-4" />
                            Ver cupo
                        </Button>
                    )}

                    {/*
                        `puede_completarse` llega resuelto: exige que la faena
                        esté EN CURSO. Sobre una vencida no se puede — al vencer
                        ya devolvió los kilos, y completarla los volvería a
                        descontar de un cupo que se repuso.
                    */}
                    {puede('faenas.completar') && faena.puede_completarse && (
                        <Button onClick={() => setCerrando((v) => !v)}>
                            <CheckCheck className="size-4" />
                            Registrar la vuelta
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={faena.etiqueta} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {cerrando && faena.puede_completarse && (
                        <Card className="border-emerald-300 bg-emerald-50/50 dark:border-emerald-500/40 dark:bg-emerald-500/5">
                            <CardHeader>
                                <CardTitle>Registrar la vuelta</CardTitle>
                            </CardHeader>

                            <CardContent>
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        form.patch(route('faenas.completar', faena.id), {
                                            preserveScroll: true,
                                            onSuccess: () => setCerrando(false),
                                        });
                                    }}
                                    className="space-y-4"
                                >
                                    <Campo
                                        etiqueta="Kilos descargados"
                                        htmlFor="kilos_extraidos"
                                        error={form.errors.kilos_extraidos}
                                        ayuda="Viene con lo declarado al salir. Corríjalo solo si la balanza dijo otra cosa; hacia arriba se vuelve a comprobar el cupo."
                                        className="max-w-xs"
                                    >
                                        <Input
                                            id="kilos_extraidos"
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            value={form.data.kilos_extraidos}
                                            onChange={(e) => form.setData('kilos_extraidos', e.target.value)}
                                            aria-invalid={Boolean(form.errors.kilos_extraidos)}
                                        />
                                    </Campo>

                                    <div className="flex gap-2">
                                        <Button type="submit" disabled={form.processing}>
                                            Completar faena
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
                            <CardTitle>La salida</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            <Situacion faena={faena} />

                            <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                                <Dato etiqueta="Kilos" valor={`${faena.kilos_extraidos} kg`} />
                                <Dato etiqueta="Salida" valor={fecha(faena.fecha_salida)} />
                                <Dato etiqueta="Límite" valor={fecha(faena.fecha_limite)} />
                                <Dato etiqueta="Asociación" valor={faena.asociacion ?? '—'} />
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Cupo del que salió</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-3 text-sm">
                        {faena.cupo === null ? (
                            <p className="text-muted-foreground">Sin cupo asociado.</p>
                        ) : (
                            <>
                                <Dato etiqueta="Escala" valor={String(faena.cupo.escala ?? '—')} />
                                <BarraSaldo cupo={faena.cupo} />

                                {/*
                                    Se dice explícitamente si ESTA faena está
                                    pesando sobre ese saldo: una vencida no, y sin
                                    la aclaración el número de arriba parece no
                                    cuadrar con la lista de faenas.
                                */}
                                <p className="text-xs text-muted-foreground">
                                    {faena.consume_cupo
                                        ? 'Esta faena está descontando sus kilos del saldo.'
                                        : 'Esta faena venció: sus kilos volvieron al cupo.'}
                                </p>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </LayoutPanel>
    );
}

/**
 * En qué situación está la salida, en una frase.
 *
 * Los tres casos se resuelven con banderas que ya llegaron del servidor. El que
 * importa es el del medio: una faena que se pasó de fecha y sigue activa es un
 * papel que alguien se llevó y del que nadie registró la vuelta — no es una
 * previsión, es algo que hay que ir a buscar.
 */
function Situacion({ faena }: { faena: FaenaFicha }) {
    if (faena.caducada) {
        return (
            <Marco
                clase="bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200"
                icono={<CalendarX className="mt-0.5 size-5 shrink-0" />}
                titulo="Venció sin cerrarse"
                texto="El pescador se llevó el papel y nadie registró la vuelta. Sus kilos ya volvieron al cupo; el número del talonario queda ocupado igual."
            />
        );
    }

    if (faena.vigente) {
        return (
            <Marco
                clase="bg-sky-50 text-sky-900 dark:bg-sky-500/10 dark:text-sky-200"
                icono={<BadgeCheck className="mt-0.5 size-5 shrink-0" />}
                titulo="En curso"
                texto={`Autoriza a pescar hasta el ${fecha(faena.fecha_limite)}. Sus kilos ya están descontados del cupo.`}
            />
        );
    }

    return (
        <Marco
            clase="bg-emerald-50 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200"
            icono={<CheckCheck className="mt-0.5 size-5 shrink-0" />}
            titulo={faena.estado_etiqueta}
            texto={
                faena.estado === 'completado'
                    ? 'El pescador volvió y descargó. El volumen quedó firme contra el cupo.'
                    : 'La salida ya no autoriza nada.'
            }
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
    icono: React.ReactNode;
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
            <dd className="font-medium tabular-nums">{valor}</dd>
        </div>
    );
}

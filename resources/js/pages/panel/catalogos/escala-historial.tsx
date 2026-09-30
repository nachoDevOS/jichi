import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fechaHora } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { TarifaAnterior, TarifaHistorial } from '@/types/catalogos';

/**
 *  Qué servicio y tarifa de SIREB tuvo un tramo de la escala, la actual arriba
 */
export default function EscalaHistorial({
    tramo,
    actual,
    anteriores,
    sirebDisponible,
}: {
    tramo: { descripcion_kg: string; kilos_min: number; kilos_max: number };
    actual: TarifaHistorial;
    anteriores: TarifaAnterior[];
    /** false = SIREB no respondió: no se sabe qué es cada tarifa hoy. */
    sirebDisponible: boolean;
}) {
    return (
        <LayoutPanel
            titulo="Historial de SIREB"
            descripcion={`${tramo.descripcion_kg} · ${tramo.kilos_min} – ${tramo.kilos_max} kg`}
            acciones={
                <Link href={route('categorias-aprovechamiento.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                    <ArrowLeft className="size-4" />
                    Volver a la escala
                </Link>
            }
        >
            <Head title={`Historial de SIREB — ${tramo.descripcion_kg}`} />

            <div className="space-y-6">
                {!sirebDisponible && (
                    <Card className="border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
                        <CardContent className="flex items-start gap-3 pt-5 text-sm">
                            <TriangleAlert className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
                            <p className="text-amber-900 dark:text-amber-200">
                                Recaudaciones (SIREB) no responde: se ven los ids y las fechas, pero no qué es cada
                                tarifa hoy.
                            </p>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Tarifa actual</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Entrada entrada={actual} sirebDisponible={sirebDisponible}>
                            Desde {fechaHora(actual.desde)}
                        </Entrada>
                    </CardContent>
                </Card>

                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle>Tarifas anteriores</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {anteriores.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Este tramo nunca cambió de tarifa: siempre tuvo la actual.
                            </p>
                        ) : (
                            <ol className="space-y-3">
                                {anteriores.map((h, i) => (
                                    <li key={i}>
                                        <Entrada entrada={h} sirebDisponible={sirebDisponible}>
                                            {fechaHora(h.desde)} → {fechaHora(h.hasta)}
                                            {h.cambiado_por && ` · la cambió ${h.cambiado_por}`}
                                        </Entrada>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </CardContent>
                </Card>
            </div>
        </LayoutPanel>
    );
}

/** Una tarifa: qué es hoy en SIREB, sus dos ids y el renglón de fechas. */
function Entrada({
    entrada,
    sirebDisponible,
    children,
}: {
    entrada: TarifaHistorial;
    sirebDisponible: boolean;
    children: ReactNode;
}) {
    const { institucion } = usePage<PageProps>().props;

    return (
        <div className="min-w-0 rounded-md border border-border p-3 text-sm">
            {entrada.tarifa_etiqueta !== null && entrada.tarifa_monto !== null ? (
                <p className="font-medium">
                    {entrada.tarifa_etiqueta || 'Sin etiqueta'} — {bs(entrada.tarifa_monto, institucion.moneda)}
                </p>
            ) : (
                sirebDisponible && <Badge color="rose">Ya no está en SIREB</Badge>
            )}

            <p className="mt-1 break-all font-mono text-xs text-muted-foreground">Tarifa {entrada.tarifa_sireb}</p>
            <p className="break-all font-mono text-xs text-muted-foreground">Servicio {entrada.servicio_sireb}</p>
            <p className="mt-1 text-xs text-muted-foreground">{children}</p>
        </div>
    );
}

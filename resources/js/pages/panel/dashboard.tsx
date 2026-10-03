import { Head, Link, usePage } from '@inertiajs/react';
import { BadgeCheck, CheckCircle2, ClipboardList, Fish, Scale, Wallet } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { lazy, Suspense } from 'react';
import type { ReactNode } from 'react';
import { PanelAvisos } from '@/components/panel/dashboard/panel-avisos';
import { TablaUltimosCarnets } from '@/components/panel/dashboard/tabla-ultimos-carnets';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { Avisos, Pendiente, RecaudacionMes, Resumen, UltimoCarnet } from '@/types/dashboard';

// El gráfico trae recharts (≈100 KB): se baja aparte para que el resto del tablero aparezca enseguida.
const GraficoRecaudacionMensual = lazy(() =>
    import('@/components/panel/dashboard/grafico-recaudacion-mensual').then((m) => ({
        default: m.GraficoRecaudacionMensual,
    })),
);

/**
 * El tablero: qué espera trabajo, cuánto hay vigente, cuánto se cobró y qué está por vencer.
 */
export default function Dashboard({
    gestion,
    pendientes,
    resumen,
    porMes,
    ultimosCarnets,
    avisos,
}: {
    gestion: number;
    pendientes: Pendiente[];
    resumen: Resumen;
    porMes: RecaudacionMes[];
    ultimosCarnets: UltimoCarnet[];
    avisos: Avisos;
}) {
    const { institucion } = usePage<PageProps>().props;

    return (
        <LayoutPanel titulo="Panel" descripcion={`Gestión ${gestion}`}>
            <Head title="Panel" />

            <div className="space-y-4">
                <TrabajoPendiente pendientes={pendientes} />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Numero
                        icono={BadgeCheck}
                        titulo="Carnets vigentes"
                        valor={resumen.pescadores + resumen.comercializadores}
                        detalle={`${resumen.pescadores} de pescador · ${resumen.comercializadores} de comercializador`}
                    />
                    <Numero
                        icono={Scale}
                        titulo="Autorizaciones de pesca vigentes"
                        valor={resumen.autorizaciones_vigentes}
                        detalle="Con kilos disponibles y en fecha"
                    />
                    <Numero
                        icono={Fish}
                        titulo="Permisos en curso"
                        valor={resumen.faenas_vigentes + resumen.guias_vigentes}
                        detalle={`${resumen.faenas_vigentes} faena(s) · ${resumen.guias_vigentes} guía(s)`}
                    />
                    <Numero
                        icono={Wallet}
                        titulo="Cobrado este mes"
                        valor={bs(resumen.cobrado_mes, institucion.moneda)}
                        detalle={`Hoy: ${bs(resumen.cobrado_hoy, institucion.moneda)}`}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-4">
                    <Suspense fallback={<GraficoCargando />}>
                        <GraficoRecaudacionMensual datos={porMes} moneda={institucion.moneda} />
                    </Suspense>

                    <PanelAvisos avisos={avisos} />
                </div>

                <TablaUltimosCarnets carnets={ultimosCarnets} moneda={institucion.moneda} />
            </div>
        </LayoutPanel>
    );
}

/**
 * Lo primero que se mira al llegar: qué espera el pago en SIREB. Cada número lleva al
 * listado ya filtrado; el pago se confirma solo, o con «Verificar pago» en la ficha.
 */
function TrabajoPendiente({ pendientes }: { pendientes: Pendiente[] }) {
    const total = pendientes.reduce((s, p) => s + p.por_pagar, 0);

    return (
        <Card>
            <CardHeader className="flex flex-row items-start gap-3 space-y-0">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <ClipboardList className="size-5" />
                </span>
                <div>
                    <CardTitle>Trabajo pendiente</CardTitle>
                    <CardDescription>
                        {total === 0
                            ? 'No hay trámites esperando. Todo está al día.'
                            : 'Toque un número para ver la lista.'}
                    </CardDescription>
                </div>
            </CardHeader>

            {total === 0 ? (
                <CardContent>
                    <p className="flex items-center gap-2 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
                        <CheckCircle2 className="size-4.5 shrink-0" />
                        Ningún trámite esperando el pago.
                    </p>
                </CardContent>
            ) : (
                <CardContent className="p-0">
                    {/* min-w-0 + overflow: la tabla se desplaza sola en el celular sin mover la página. */}
                    <div className="min-w-0 overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-y border-border text-left text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-2.5 font-medium">Documento</th>
                                    <th className="px-5 py-2.5 text-center font-medium">
                                        Esperando el pago
                                        <span className="block font-normal">en Recaudaciones</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {pendientes.map((p) => (
                                    <tr key={p.documento}>
                                        <td className="px-5 py-3 font-medium">{p.documento}</td>
                                        <td className="px-5 py-3 text-center">
                                            <Cantidad valor={p.por_pagar} href={p.url_por_pagar} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            )}
        </Card>
    );
}

/** Un cero se ve apagado y no es enlace: solo llama la atención lo que tiene trabajo. */
function Cantidad({ valor, href }: { valor: number; href: string }) {
    if (valor === 0) {
        return <span className="text-muted-foreground/60 tabular-nums">0</span>;
    }

    return (
        <Link
            href={href}
            className="inline-flex min-w-10 items-center justify-center rounded-full bg-amber-100 px-3 py-1 font-semibold text-amber-800 tabular-nums transition-colors hover:bg-amber-200 dark:bg-amber-500/15 dark:text-amber-300"
        >
            {valor}
        </Link>
    );
}

function Numero({
    icono: Icono,
    titulo,
    valor,
    detalle,
}: {
    icono: LucideIcon;
    titulo: string;
    valor: ReactNode;
    detalle: string;
}) {
    return (
        <Card>
            <CardContent className="flex items-start gap-4 p-5">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
                    <Icono className="size-5" />
                </span>
                <div className="min-w-0">
                    <p className="text-sm text-muted-foreground">{titulo}</p>
                    <p className="mt-0.5 truncate text-2xl font-bold tabular-nums">{valor}</p>
                    <p className="mt-1 text-xs text-muted-foreground">{detalle}</p>
                </div>
            </CardContent>
        </Card>
    );
}

/** El hueco del gráfico mientras se está bajando, con su misma altura. */
function GraficoCargando() {
    return (
        <Card className="lg:col-span-3">
            <CardHeader>
                <CardTitle>Recaudación del año</CardTitle>
            </CardHeader>
            <CardContent className="h-[26rem]">
                <div className="h-full w-full animate-pulse rounded-md bg-secondary" />
            </CardContent>
        </Card>
    );
}

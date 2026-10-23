import { Head, usePage } from '@inertiajs/react';
import { BadgeCheck, Scale, Ship, Truck } from 'lucide-react';
import { lazy, Suspense } from 'react';
import { PortadaTablero } from '@/components/panel/dashboard/portada-tablero';
import {
    ActividadReciente,
    Alertas,
    BarraPartes,
    Destinos,
    MedidorPesca,
    Numero,
    TopPescadores,
    TrabajoPendiente,
    UltimosCarnets,
} from '@/components/panel/dashboard/tarjetas-tablero';
import { Card } from '@/components/ui/card';
import LayoutPanel from '@/layouts/layout-panel';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import type {
    Actividad,
    Avisos,
    CasiAgotada,
    CobroPorConcepto,
    Destino,
    Pendiente,
    Pesca,
    RecaudacionMes,
    Resumen,
    TopPescador,
    UltimoCarnet,
} from '@/types/dashboard';

// Los gráficos traen recharts (≈100 KB): se bajan aparte para que el resto del tablero aparezca enseguida.
const GraficoRecaudacionMensual = lazy(() =>
    import('@/components/panel/dashboard/grafico-recaudacion-mensual').then((m) => ({
        default: m.GraficoRecaudacionMensual,
    })),
);
const GraficoKilosMes = lazy(() =>
    import('@/components/panel/dashboard/graficos-tablero').then((m) => ({ default: m.GraficoKilosMes })),
);
const DonaConceptos = lazy(() =>
    import('@/components/panel/dashboard/graficos-tablero').then((m) => ({ default: m.DonaConceptos })),
);

/**
 * El tablero: cuánto se cobró, cuánto pescado se movió, qué espera trabajo y qué está por vencer.
 */
export default function Dashboard({
    gestion,
    pendientes,
    resumen,
    porMes,
    ultimosCarnets,
    avisos,
    pesca,
    kilosPorMes,
    porConcepto,
    casiAgotadas,
    topPescadores,
    destinos,
    actividad,
}: {
    gestion: number;
    pendientes: Pendiente[];
    resumen: Resumen;
    porMes: RecaudacionMes[];
    ultimosCarnets: UltimoCarnet[];
    avisos: Avisos;
    pesca: Pesca;
    kilosPorMes: RecaudacionMes[];
    porConcepto: CobroPorConcepto[];
    casiAgotadas: CasiAgotada[];
    topPescadores: TopPescador[];
    destinos: Destino[];
    actividad: Actividad[];
}) {
    const { institucion, auth } = usePage<PageProps>().props;
    const moneda = institucion.moneda;
    const esperando = pendientes.reduce((t, p) => t + p.por_pagar, 0);

    return (
        <LayoutPanel titulo="Panel" descripcion={`Gestión ${gestion}`}>
            <Head title="Panel" />

            <div className="space-y-5">
                <PortadaTablero
                    nombre={auth.user?.name ?? ''}
                    gestion={gestion}
                    cobradoHoy={resumen.cobrado_hoy}
                    cobradoMes={resumen.cobrado_mes}
                    esperando={esperando}
                    moneda={moneda}
                />

                <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                    <Numero
                        icono={BadgeCheck}
                        tono="bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"
                        titulo="Carnets vigentes"
                        valor={resumen.pescadores + resumen.comercializadores}
                        detalle={`${resumen.pescadores} de pescador · ${resumen.comercializadores} de comercializador`}
                    >
                        <BarraPartes
                            a={resumen.pescadores}
                            b={resumen.comercializadores}
                            colorA="bg-sky-500"
                            colorB="bg-violet-500"
                        />
                    </Numero>
                    <Numero
                        icono={Scale}
                        tono="bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                        titulo="Autorizaciones vigentes"
                        valor={resumen.autorizaciones_vigentes}
                        detalle="Aprobadas y en fecha"
                    />
                    <Numero
                        icono={Ship}
                        tono="bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"
                        titulo="Faenas en el agua"
                        valor={resumen.faenas_vigentes}
                        detalle="Permisos de faena vigentes hoy"
                    />
                    <Numero
                        icono={Truck}
                        tono="bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                        titulo="Guías en camino"
                        valor={resumen.guias_vigentes}
                        detalle="Traslados vigentes ahora"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Suspense fallback={<Cargando className="lg:col-span-2" />}>
                        <GraficoRecaudacionMensual datos={porMes} moneda={moneda} />
                    </Suspense>
                    <MedidorPesca pesca={pesca} />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Suspense fallback={<Cargando className="lg:col-span-2" />}>
                        <GraficoKilosMes datos={kilosPorMes} />
                    </Suspense>
                    <Suspense fallback={<Cargando />}>
                        <DonaConceptos datos={porConcepto} moneda={moneda} gestion={gestion} />
                    </Suspense>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <TrabajoPendiente pendientes={pendientes} />
                    <Alertas avisos={avisos} casiAgotadas={casiAgotadas} />
                    <ActividadReciente actividad={actividad} moneda={moneda} />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <TopPescadores pescadores={topPescadores} />
                    <Destinos destinos={destinos} gestion={gestion} />
                    <UltimosCarnets carnets={ultimosCarnets} />
                </div>
            </div>
        </LayoutPanel>
    );
}

/** El hueco de un gráfico mientras se baja, con su misma altura. */
function Cargando({ className }: { className?: string }) {
    return (
        <Card className={cn('h-[22rem] p-5', className)}>
            <div className="h-full w-full animate-pulse rounded-md bg-secondary" />
        </Card>
    );
}

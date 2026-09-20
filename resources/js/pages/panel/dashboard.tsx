import { Head, usePage } from '@inertiajs/react';
import { BadgeCheck, FileText, Wallet, Waves } from 'lucide-react';
import { lazy, Suspense } from 'react';
import { MiniBarras, MiniLinea } from '@/components/panel/dashboard/mini-grafico';
import { PanelAvisos } from '@/components/panel/dashboard/panel-avisos';
import { TablaUltimosCarnets } from '@/components/panel/dashboard/tabla-ultimos-carnets';
import { DesglosePie, WidgetEstadistica } from '@/components/panel/dashboard/widget-estadistica';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { PageProps } from '@/types';
import type {
    ActividadDia,
    Avisos,
    CarnetsPorActor,
    CarnetsPorTipo,
    RecaudacionMes,
    ResumenDelDia,
    UltimoCarnet,
} from '@/types/dashboard';

/**
 *  LOS DOS GRÁFICOS GRANDES SE CARGAN APARTE, Y DESPUÉS
 */
const GraficoCarnetsPorTipo = lazy(() =>
    import('@/components/panel/dashboard/grafico-carnets-por-tipo').then((m) => ({
        // lazy() espera un módulo con `default`, y estos componentes se exportan
        // por nombre —como todos los del sistema—. Esto los adapta sin tener que
        // cambiar la forma en que se exportan.
        default: m.GraficoCarnetsPorTipo,
    })),
);

const GraficoRecaudacionMensual = lazy(() =>
    import('@/components/panel/dashboard/grafico-recaudacion-mensual').then((m) => ({
        default: m.GraficoRecaudacionMensual,
    })),
);

/**
 * El tablero de la gestión en curso.
 */
export default function Dashboard({
    gestion,
    resumen,
    porDia,
    porTipoCarnet,
    porMes,
    porActor,
    ultimosCarnets,
    avisos,
}: {
    gestion: number;
    resumen: ResumenDelDia;
    porDia: ActividadDia[];
    porTipoCarnet: CarnetsPorTipo[];
    porMes: RecaudacionMes[];
    porActor: CarnetsPorActor[];
    ultimosCarnets: UltimoCarnet[];
    avisos: Avisos;
}) {
    const { institucion } = usePage<PageProps>().props;

    return (
        <LayoutPanel titulo="Panel" descripcion={`Gestión ${gestion}`}>
            <Head title="Panel" />

            <div className="space-y-4">
                {/* ------------------------------------------------- Indicadores */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <WidgetEstadistica
                        tono={1}
                        icono={BadgeCheck}
                        etiqueta="Carnets vigentes"
                        valor={String(resumen.carnets_vigentes)}
                        pie={`${resumen.carnets_gestion} emitidos en la gestión`}
                    >
                        {/*
                            Un carnet emitido este año puede estar vigente o no
                            —vencido, revocado—, así que los dos pedazos suman
                            exactamente los emitidos de la gestión y la barra no
                            puede mentir.
                        */}
                        <DesglosePie
                            partes={[
                                { etiqueta: 'vigentes', cantidad: resumen.carnets_vigentes },
                                {
                                    etiqueta: 'no vigentes',
                                    cantidad: Math.max(
                                        0,
                                        resumen.carnets_gestion - resumen.carnets_vigentes,
                                    ),
                                },
                            ]}
                        />
                    </WidgetEstadistica>

                    <WidgetEstadistica
                        tono={2}
                        icono={FileText}
                        etiqueta="Documentos de hoy"
                        valor={String(resumen.documentos_hoy)}
                        pie="Carnets, faenas y guías · últimos 14 días"
                    >
                        {/* Barras y no línea: son conteos enteros. Ver el
                            comentario de MiniBarras. */}
                        <MiniBarras
                            valores={porDia.map((d) => d.documentos)}
                            className="h-10 w-full"
                        />
                    </WidgetEstadistica>

                    <WidgetEstadistica
                        tono={3}
                        icono={Waves}
                        etiqueta="Permisos vigentes"
                        valor={String(resumen.faenas_vigentes + resumen.guias_vigentes)}
                        pie={`${resumen.cupos_activos} cupo(s) de pesca activo(s)`}
                    >
                        {/*
                            Faenas y guías son EXCLUYENTES —un permiso es de uno
                            o del otro tipo, nunca de los dos—, que es lo que la
                            barra necesita para que los pedazos sumen el total.
                        */}
                        <DesglosePie
                            partes={[
                                { etiqueta: 'faenas', cantidad: resumen.faenas_vigentes },
                                { etiqueta: 'guías', cantidad: resumen.guias_vigentes },
                            ]}
                        />
                    </WidgetEstadistica>

                    <WidgetEstadistica
                        tono={4}
                        icono={Wallet}
                        etiqueta="Recaudado este mes"
                        valor={bs(resumen.recaudado_mes, institucion.moneda)}
                        // El dato accionable no es cuánto entró, sino cuánto
                        // falta entrar: eso es trabajo de cobranza pendiente.
                        pie={`Hoy: ${bs(resumen.recaudado_hoy, institucion.moneda)} · Por cobrar: ${bs(resumen.por_cobrar, institucion.moneda)}`}
                    >
                        <MiniLinea
                            valores={porDia.map((d) => d.recaudado)}
                            className="h-10 w-full"
                        />
                    </WidgetEstadistica>
                </div>

                {/* ---------------------------------------------- Gráfico ancla */}
                <div className="grid gap-4 lg:grid-cols-4">
                    <Suspense
                        fallback={<GraficoCargando titulo="Recaudación del año" alto="h-[26rem]" />}
                    >
                        <GraficoRecaudacionMensual datos={porMes} moneda={institucion.moneda} />
                    </Suspense>

                    <RepartoPorActividad datos={porActor} gestion={gestion} />
                </div>

                {/* ------------------------------------------ Tipos y avisos */}
                <div className="grid gap-4 lg:grid-cols-4">
                    <Suspense
                        fallback={
                            <GraficoCargando
                                titulo="Carnets por tipo"
                                alto="h-72"
                                className="lg:col-span-2"
                            />
                        }
                    >
                        <GraficoCarnetsPorTipo datos={porTipoCarnet} gestion={gestion} />
                    </Suspense>

                    <PanelAvisos avisos={avisos} />
                </div>

                {/* ----------------------------------------------------- Tabla */}
                <div className="grid gap-4 lg:grid-cols-3">
                    <TablaUltimosCarnets carnets={ultimosCarnets} moneda={institucion.moneda} />
                </div>
            </div>
        </LayoutPanel>
    );
}

/**
 * El hueco del gráfico mientras se está bajando.
 */
function GraficoCargando({
    titulo,
    alto,
    className = 'lg:col-span-3',
}: {
    titulo: string;
    /** La misma clase de altura que usa el gráfico de verdad. */
    alto: string;
    className?: string;
}) {
    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle>{titulo}</CardTitle>
            </CardHeader>

            <CardContent className={alto}>
                <div className="h-full w-full animate-pulse rounded-md bg-secondary" />
            </CardContent>
        </Card>
    );
}

/**
 * Pescadores contra comercializadores, entre los carnets vigentes.
 */
function RepartoPorActividad({ datos, gestion }: { datos: CarnetsPorActor[]; gestion: number }) {
    const total = datos.reduce((suma, d) => suma + d.cantidad, 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Por actividad</CardTitle>
            </CardHeader>

            <CardContent className="space-y-4">
                <p className="text-sm text-muted-foreground">
                    Gestión {gestion} · {total} carnet(s) vigente(s)
                </p>

                {datos.map((d) => (
                    <div key={d.tipo} className="space-y-1">
                        <div className="flex items-baseline justify-between gap-2">
                            <Badge color={d.color}>{d.etiqueta}</Badge>
                            <span className="text-xl font-semibold tabular-nums">{d.cantidad}</span>
                        </div>

                        {/*
                            La barra de proporción se dibuja con un div de ancho
                            porcentual y no con una librería: para dos valores,
                            traer recharts sería cargar 100 KB para dibujar un
                            rectángulo.
                        */}
                        <div className="h-1.5 overflow-hidden rounded-full bg-secondary">
                            <div
                                className="h-full rounded-full bg-primary"
                                style={{ width: total > 0 ? `${(d.cantidad / total) * 100}%` : '0%' }}
                            />
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

import { Head, usePage } from '@inertiajs/react';
import { BadgeCheck, CheckCheck, FileText, Wallet } from 'lucide-react';
import { lazy, Suspense } from 'react';
import { MiniBarras, MiniLinea } from '@/components/panel/dashboard/mini-grafico';
import { PanelCierreGestion } from '@/components/panel/dashboard/panel-cierre-gestion';
import { TablaUltimosTramites } from '@/components/panel/dashboard/tabla-ultimos-tramites';
import { DesglosePie, WidgetEstadistica } from '@/components/panel/dashboard/widget-estadistica';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { PageProps } from '@/types';
import type {
    ActividadDia,
    AvisoCierreGestion,
    CarnetsPorRubro,
    RecaudacionMes,
    ResumenDelDia,
    TramitesPorTipo,
    UltimoTramite,
} from '@/types/dashboard';

/**
 * ============================================================================
 *  LOS DOS GRÁFICOS GRANDES SE CARGAN APARTE, Y DESPUÉS
 * ============================================================================
 *
 * `lazy()` le dice a Vite que ponga cada uno en su propio archivo y que lo baje
 * recién cuando haga falta dibujarlo, en vez de meterlo dentro del archivo del
 * tablero.
 *
 * El motivo es concreto y se medía: los dos gráficos usan `recharts`, y por
 * arrastrarla el tablero pesaba **373 kB** — más que React entero—. Es la
 * pantalla a la que cae TODO el mundo apenas entra, así que ese peso lo pagaba
 * cada persona en cada ingreso, antes de ver un solo número.
 *
 * Y los números son lo accionable: «cuántos trámites hay sin resolver», «cuánto
 * se recaudó hoy». Los gráficos son contexto. Separándolos, lo importante
 * aparece de inmediato y lo demás llega un instante después, solo.
 *
 * LAS LÍNEAS CHICAS DE LOS CUATRO INDICADORES NO PASAN POR ACÁ: están dibujadas
 * a mano en SVG justamente para no volver a meter recharts arriba de todo y
 * desarmar esta decisión. Ver components/panel/dashboard/mini-grafico.tsx.
 *
 * `Suspense` es lo que React necesita para saber qué dibujar mientras tanto:
 * sin él, un componente `lazy()` que todavía no llegó revienta la pantalla.
 * El `fallback` es un recuadro de la MISMA altura —ver GraficoCargando—, así
 * la página no salta cuando el gráfico aparece.
 */
const GraficoCarnetsPorRubro = lazy(() =>
    import('@/components/panel/dashboard/grafico-carnets-por-rubro').then((m) => ({
        // lazy() espera un módulo con `default`, y estos componentes se exportan
        // por nombre —como todos los del sistema—. Esto los adapta sin tener que
        // cambiar la forma en que se exportan.
        default: m.GraficoCarnetsPorRubro,
    })),
);

const GraficoRecaudacionMensual = lazy(() =>
    import('@/components/panel/dashboard/grafico-recaudacion-mensual').then((m) => ({
        default: m.GraficoRecaudacionMensual,
    })),
);

/**
 * El tablero de la gestión en curso.
 *
 * Los siete bloques llegan como props desde DashboardController, cada uno
 * calculado en su propia consulta. Todo lo que se muestra acá es de solo
 * lectura: el tablero informa, no permite hacer nada.
 */
export default function Dashboard({
    gestion,
    resumen,
    porDia,
    porRubro,
    porMes,
    porTipo,
    ultimosTramites,
    porVencer,
}: {
    gestion: number;
    resumen: ResumenDelDia;
    porDia: ActividadDia[];
    porRubro: CarnetsPorRubro[];
    porMes: RecaudacionMes[];
    porTipo: TramitesPorTipo[];
    ultimosTramites: UltimoTramite[];
    porVencer: AvisoCierreGestion;
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
                        icono={FileText}
                        etiqueta="Trámites sin resolver"
                        valor={String(resumen.tramites_abiertos)}
                        // El dato accionable no es cuántos hay, sino cuántos se
                        // pueden resolver ya: los que están cobrados.
                        pie={`${resumen.listos_para_aprobar} listos para aprobar`}
                    >
                        {/*
                            Los dos pedazos son EXCLUYENTES —un expediente está
                            en revisión o está pendiente, nunca en las dos—, que
                            es lo que la barra necesita para no mentir. «Listos
                            para aprobar» no entra acá justamente por eso: los
                            cobrados están repartidos entre los dos estados y
                            sumarlos como un tercer pedazo contaría gente dos
                            veces. Va como texto arriba.
                        */}
                        <DesglosePie
                            partes={[
                                { etiqueta: 'en revisión', cantidad: resumen.tramites_en_revision },
                                {
                                    etiqueta: 'pendientes',
                                    cantidad: resumen.tramites_abiertos - resumen.tramites_en_revision,
                                },
                            ]}
                        />
                    </WidgetEstadistica>

                    <WidgetEstadistica
                        tono={2}
                        icono={CheckCheck}
                        etiqueta="Trámites de hoy"
                        valor={String(resumen.tramites_hoy)}
                        pie="Movimiento de los últimos 14 días"
                    >
                        {/* Barras y no línea: son conteos enteros. Ver el
                            comentario de MiniBarras. */}
                        <MiniBarras
                            valores={porDia.map((d) => d.tramites)}
                            className="h-10 w-full"
                        />
                    </WidgetEstadistica>

                    <WidgetEstadistica
                        tono={3}
                        icono={BadgeCheck}
                        etiqueta={`Carnets vigentes ${gestion}`}
                        valor={String(resumen.carnets_vigentes)}
                        pie={`${resumen.carnets_gestion} emitidos en la gestión`}
                    >
                        {/*
                            Un carnet emitido este año puede estar vigente o no
                            —vencido, anulado—, así que los dos pedazos suman
                            exactamente los emitidos de la gestión.
                        */}
                        <DesglosePie
                            partes={[
                                { etiqueta: 'vigentes', cantidad: resumen.carnets_vigentes },
                                {
                                    etiqueta: 'no vigentes',
                                    cantidad: resumen.carnets_gestion - resumen.carnets_vigentes,
                                },
                            ]}
                        />
                    </WidgetEstadistica>

                    <WidgetEstadistica
                        tono={4}
                        icono={Wallet}
                        etiqueta="Recaudado este mes"
                        valor={bs(resumen.recaudado_mes, institucion.moneda)}
                        pie={`Hoy: ${bs(resumen.recaudado_hoy, institucion.moneda)}`}
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

                    <TiposDeTramite datos={porTipo} gestion={gestion} />
                </div>

                {/* -------------------------------------- Rubros y fin de gestión */}
                <div className="grid gap-4 lg:grid-cols-4">
                    <Suspense
                        fallback={
                            <GraficoCargando
                                titulo="Carnets por rubro"
                                alto="h-72"
                                className="lg:col-span-2"
                            />
                        }
                    >
                        <GraficoCarnetsPorRubro datos={porRubro} gestion={gestion} />
                    </Suspense>

                    <PanelCierreGestion aviso={porVencer} gestion={gestion} />
                </div>

                {/* ----------------------------------------------------- Tabla */}
                <div className="grid gap-4 lg:grid-cols-3">
                    <TablaUltimosTramites tramites={ultimosTramites} moneda={institucion.moneda} />
                </div>
            </div>
        </LayoutPanel>
    );
}

/**
 * El hueco del gráfico mientras se está bajando.
 *
 * Ocupa lo mismo que el gráfico terminado, y por eso el alto es una prop y no
 * un número fijo: los dos gráficos miden distinto. Si el hueco midiera otra
 * cosa, al llegar el gráfico la página daría un salto y lo que el operador
 * estaba por tocar se le correría de lugar.
 *
 * `animate-pulse` es de Tailwind y hace el latido gris de «esto está por
 * llegar».
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
 * Emisiones iniciales contra adiciones de rubro.
 *
 * Es el número que muestra cómo está funcionando la Regla A: cuánta gente saca
 * carnet por primera vez este año y cuánta ya lo tenía y viene a sumar
 * actividades. Va como dos cifras y no como gráfico porque son dos valores: un
 * gráfico de torta con dos porciones no agrega nada que el número no diga.
 */
function TiposDeTramite({ datos, gestion }: { datos: TramitesPorTipo[]; gestion: number }) {
    const total = datos.reduce((suma, d) => suma + d.cantidad, 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Tipos de trámite</CardTitle>
            </CardHeader>

            <CardContent className="space-y-4">
                <p className="text-sm text-muted-foreground">
                    Gestión {gestion} · {total} trámite(s)
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

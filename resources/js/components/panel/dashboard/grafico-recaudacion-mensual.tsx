import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Card } from '@/components/ui/card';
import { ESTILO_TOOLTIP } from '@/lib/graficos';
import { bs } from '@/lib/utils';
import type { RecaudacionMes } from '@/types/dashboard';

/**
 * ============================================================================
 *  LA RECAUDACIÓN DE LOS ÚLTIMOS DOCE MESES
 * ============================================================================
 *
 * Los gráficos los dibuja `recharts`, una librería de React. Se arma como si
 * fuera HTML: cada pieza del gráfico es una etiqueta.
 *
 *   <ResponsiveContainer>  se estira al tamaño del contenedor padre
 *     <AreaChart>          el gráfico y sus datos
 *       <CartesianGrid>    la cuadrícula de fondo
 *       <XAxis> <YAxis>    los ejes
 *       <Tooltip>          el cuadrito al pasar el mouse
 *       <Area>             la curva con el relleno debajo
 *
 * OJO: ResponsiveContainer mide a su padre, así que el padre necesita una
 * altura concreta. Por eso el div que lo envuelve lleva `h-64`. Sin esa altura
 * el gráfico se calcula con altura cero y no se ve nada.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ÁREA Y NO LÍNEA
 * ----------------------------------------------------------------------------
 *
 * Lo que se pregunta de la recaudación no es «cuánto entró en abril» —para eso
 * está el globito— sino «cómo viene el año». Eso es el BULTO bajo la curva, y
 * una línea sola no lo dibuja: hay que reconstruirlo con la vista. El relleno
 * lo muestra directamente.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ EL PANEL VA PINTADO Y NO BLANCO
 * ----------------------------------------------------------------------------
 *
 * Es la pieza más grande del tablero y la que resume el año. Sobre blanco, con
 * el resto de las tarjetas también blancas, queda al mismo nivel que el listado
 * de los últimos trámites. Pintado, hace de ancla: es lo que el ojo agarra
 * después de los cuatro números de arriba.
 *
 * Reusa el tono 1 de los indicadores —no un azul escrito acá— porque ese par de
 * tokens ya tiene medido el contraste del texto encima, en modo claro y en
 * oscuro. Los colores de recharts NO son clases de Tailwind sino atributos SVG,
 * así que van como `var(--widget-1-fg)` y no como `text-widget-1-fg`.
 */

/** Color del texto sobre el panel. Se repite en todos los trazos del gráfico. */
const TINTA = 'var(--widget-1-fg)';

export function GraficoRecaudacionMensual({
    datos,
    moneda,
}: {
    datos: RecaudacionMes[];
    moneda: string;
}) {
    const total = datos.reduce((suma, d) => suma + d.total, 0);

    // El mejor mes se busca con reduce y no ordenando una copia: ordenar para
    // quedarse con un solo elemento recorre la lista varias veces y, además,
    // `sort()` muta el arreglo —acá sería la prop, que no es nuestra—.
    const mejor = datos.reduce<RecaudacionMes | null>(
        (max, d) => (max === null || d.total > max.total ? d : max),
        null,
    );

    const ultimo = datos[datos.length - 1];

    return (
        <Card className="overflow-hidden lg:col-span-3">
            <div className="bg-widget-1 px-5 pt-5 pb-3 text-widget-1-fg">
                <h3 className="text-base font-semibold tracking-tight">Recaudación del año</h3>

                <p className="text-sm opacity-75">
                    Últimos 12 meses en {moneda}, sumando todos los depósitos registrados.
                </p>

                <div className="mt-4 h-64">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={datos} margin={{ top: 4, right: 4, bottom: 0, left: -16 }}>
                            <defs>
                                {/*
                                    El degradado que se desvanece hacia abajo. Va
                                    en <defs> porque un degradado de SVG no es un
                                    color: es un objeto que se declara una vez y
                                    se referencia por id desde el relleno.
                                */}
                                <linearGradient id="degradadoRecaudacion" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor={TINTA} stopOpacity={0.45} />
                                    <stop offset="100%" stopColor={TINTA} stopOpacity={0.02} />
                                </linearGradient>
                            </defs>

                            <CartesianGrid
                                strokeDasharray="3 3"
                                stroke={TINTA}
                                strokeOpacity={0.18}
                                vertical={false}
                            />

                            {/* dataKey dice qué campo de cada fila usar. 'etiqueta'
                                es el 'Sep 26' que armó el controlador en PHP. */}
                            <XAxis
                                dataKey="etiqueta"
                                tick={{ fontSize: 11, fill: TINTA, opacity: 0.75 }}
                                tickLine={false}
                                axisLine={false}
                            />

                            <YAxis
                                tick={{ fontSize: 11, fill: TINTA, opacity: 0.75 }}
                                tickLine={false}
                                axisLine={false}
                                width={64}
                            />

                            <Tooltip
                                contentStyle={ESTILO_TOOLTIP}
                                // El globito sale del panel de color y cae sobre
                                // el fondo de la página, así que usa el estilo
                                // común del sistema y no la tinta de acá.
                                cursor={{ stroke: TINTA, strokeOpacity: 0.4 }}
                                // formatter decide qué texto mostrar al pasar el mouse.
                                // Devuelve [valor, nombre]: "1.250,50 Bs" / "Recaudado".
                                formatter={(v) => [bs(Number(v), moneda), 'Recaudado'] as [string, string]}
                            />

                            <Area
                                type="monotone"
                                dataKey="total"
                                stroke={TINTA}
                                strokeWidth={2.5}
                                fill="url(#degradadoRecaudacion)"
                                dot={false}
                                // El punto solo aparece donde está el mouse: con
                                // doce puntos siempre dibujados, la curva se
                                // convierte en un collar de cuentas.
                                activeDot={{ r: 4, fill: TINTA, stroke: 'none' }}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            </div>

            {/*
                LA FRANJA DE CIFRAS NO PIDE NADA AL SERVIDOR: los cuatro números
                salen de la misma serie que ya dibujó la curva. Calcularlos en el
                controlador serían cuatro consultas más para responder algo que
                el navegador ya tiene en memoria.
            */}
            <div className="grid grid-cols-2 divide-border sm:grid-cols-4 sm:divide-x">
                <CifraPie titulo="Total del período" valor={bs(total, moneda)} />
                <CifraPie titulo="Promedio mensual" valor={bs(total / (datos.length || 1), moneda)} />
                <CifraPie
                    titulo="Mejor mes"
                    valor={mejor && mejor.total > 0 ? bs(mejor.total, moneda) : '—'}
                    pie={mejor && mejor.total > 0 ? mejor.etiqueta : 'sin cobros en el período'}
                />
                <CifraPie
                    titulo="Mes en curso"
                    valor={ultimo ? bs(ultimo.total, moneda) : '—'}
                    pie={ultimo?.etiqueta}
                />
            </div>
        </Card>
    );
}

/** Una de las cuatro cifras del pie del gráfico. */
function CifraPie({ titulo, valor, pie }: { titulo: string; valor: string; pie?: string }) {
    return (
        <div className="px-5 py-3.5">
            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                {titulo}
            </p>

            <p className="mt-0.5 truncate text-base font-semibold tabular-nums">{valor}</p>

            {pie && <p className="truncate text-xs text-muted-foreground">{pie}</p>}
        </div>
    );
}

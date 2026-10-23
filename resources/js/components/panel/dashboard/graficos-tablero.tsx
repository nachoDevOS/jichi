import { Truck } from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useReducirMovimiento } from '@/hooks/use-movimiento';
import { ESTILO_TOOLTIP } from '@/lib/graficos';
import { bs } from '@/lib/utils';
import type { CobroPorConcepto, RecaudacionMes } from '@/types/dashboard';
import { COLOR_CONCEPTO } from './colores';

const kg = (n: number) => `${n.toLocaleString('es-BO', { maximumFractionDigits: 0 })} kg`;

/** Los kilos que salieron en guías, mes por mes: es el pescado que se movió. */
export function GraficoKilosMes({ datos }: { datos: RecaudacionMes[] }) {
    const reducir = useReducirMovimiento();
    const total = datos.reduce((t, d) => t + d.total, 0);
    const ultimo = datos.length - 1;

    return (
        <Card className="min-w-0 lg:col-span-2">
            <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        <Truck className="size-4.5 text-primary" />
                        Pescado trasladado
                    </CardTitle>
                    <CardDescription>Kilos en guías de transporte, los últimos 12 meses.</CardDescription>
                </div>
                <div className="text-right">
                    <p className="text-xl font-semibold tabular-nums">{kg(total)}</p>
                    <p className="text-xs text-muted-foreground">en el período</p>
                </div>
            </CardHeader>

            {/* El contenedor necesita altura propia: sin ella recharts dibuja en cero. */}
            <CardContent className="h-60">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={datos} margin={{ top: 4, right: 4, bottom: 0, left: -12 }}>
                        <defs>
                            <linearGradient id="degradadoKilos" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor="var(--rio-claro)" />
                                <stop offset="100%" stopColor="var(--primary)" />
                            </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                        <XAxis
                            dataKey="etiqueta"
                            tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                            tickLine={false}
                            axisLine={false}
                        />
                        <YAxis
                            tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                            tickFormatter={(v: number) => v.toLocaleString('es-BO')}
                            tickLine={false}
                            axisLine={false}
                            width={56}
                        />
                        <Tooltip
                            contentStyle={ESTILO_TOOLTIP}
                            labelStyle={{ color: 'var(--popover-foreground)', fontWeight: 600 }}
                            itemStyle={{ color: 'var(--popover-foreground)' }}
                            cursor={{ fill: 'var(--secondary)' }}
                            formatter={(v) => [kg(Number(v)), 'Trasladado'] as [string, string]}
                        />
                        <Bar
                            dataKey="total"
                            radius={[4, 4, 0, 0]}
                            maxBarSize={36}
                            isAnimationActive={!reducir}
                            animationDuration={1100}
                        >
                            {/* El mes en curso, más fuerte: es el que se está llenando. */}
                            {datos.map((d, i) => (
                                <Cell
                                    key={d.periodo}
                                    fill="url(#degradadoKilos)"
                                    fillOpacity={i === ultimo ? 1 : 0.7}
                                />
                            ))}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}

/** De dónde sale lo cobrado en la gestión: un trozo por trámite, con el monto escrito al lado. */
export function DonaConceptos({
    datos,
    moneda,
    gestion,
}: {
    datos: CobroPorConcepto[];
    moneda: string;
    gestion: number;
}) {
    const reducir = useReducirMovimiento();
    const total = datos.reduce((t, d) => t + d.total, 0);

    return (
        <Card className="min-w-0">
            <CardHeader>
                <CardTitle>De dónde sale lo cobrado</CardTitle>
                <CardDescription>Recibos de la gestión {gestion}, por trámite.</CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                <div className="relative mx-auto h-44 w-44">
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie
                                data={total > 0 ? datos : [{ etiqueta: 'Sin cobros', total: 1, clave: 'vacio' }]}
                                dataKey="total"
                                nameKey="etiqueta"
                                innerRadius="68%"
                                outerRadius="100%"
                                paddingAngle={total > 0 ? 2 : 0}
                                stroke="var(--card)"
                                strokeWidth={2}
                                isAnimationActive={!reducir}
                                animationDuration={1100}
                            >
                                {(total > 0 ? datos : [{ clave: 'vacio' }]).map((d) => (
                                    <Cell
                                        key={d.clave}
                                        fill={
                                            d.clave === 'vacio'
                                                ? 'var(--secondary)'
                                                : COLOR_CONCEPTO[d.clave as CobroPorConcepto['clave']]
                                        }
                                    />
                                ))}
                            </Pie>
                            {total > 0 && (
                                <Tooltip
                                    contentStyle={ESTILO_TOOLTIP}
                                    itemStyle={{ color: 'var(--popover-foreground)' }}
                                    formatter={(v, n) => [bs(Number(v), moneda), String(n)] as [string, string]}
                                />
                            )}
                        </PieChart>
                    </ResponsiveContainer>
                    {/* El total en el hueco de la dona. */}
                    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span className="text-[11px] tracking-wide text-muted-foreground uppercase">Total</span>
                        <span className="text-lg font-semibold tabular-nums">{bs(total, moneda)}</span>
                    </div>
                </div>

                {/* La leyenda con montos: el color nunca va solo. */}
                <ul className="space-y-2 text-sm">
                    {datos.map((d) => (
                        <li key={d.clave} className="flex items-center gap-2">
                            <i
                                className="size-2.5 shrink-0 rounded-full"
                                style={{ backgroundColor: COLOR_CONCEPTO[d.clave] }}
                                aria-hidden
                            />
                            <span className="min-w-0 flex-1 truncate">{d.etiqueta}</span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {total > 0 ? `${Math.round((d.total / total) * 100)} %` : '—'}
                            </span>
                            <span className="w-24 text-right font-medium tabular-nums">{bs(d.total, moneda)}</span>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

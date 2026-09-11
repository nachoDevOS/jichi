import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { colorSerie, ESTILO_EJE, ESTILO_TOOLTIP } from '@/lib/graficos';
import { bs } from '@/lib/utils';
import type { RecaudacionArea } from '@/types/dashboard';

/**
 * Barras horizontales con lo recaudado por cada área departamental en el mes.
 *
 * `layout="vertical"` en recharts significa que las BARRAS son horizontales
 * (el nombre confunde: describe la orientación de los ejes, no de las barras).
 * Se eligió así porque los nombres de las áreas son largos —"Pesca Artesanal",
 * "Actividades Económicas"— y en vertical se pisarían unos con otros.
 */
export function GraficoRecaudacionPorArea({
    datos,
    moneda,
}: {
    datos: RecaudacionArea[];
    moneda: string;
}) {
    return (
        <Card className="lg:col-span-2">
            <CardHeader>
                <CardTitle>Recaudación por área</CardTitle>
                <CardDescription>Mes en curso.</CardDescription>
            </CardHeader>

            <CardContent className="h-72">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={datos} layout="vertical" margin={{ top: 4, right: 12, bottom: 0, left: 8 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" horizontal={false} />

                        <XAxis type="number" tick={ESTILO_EJE} tickLine={false} axisLine={false} />

                        <YAxis
                            type="category"
                            dataKey="area"
                            width={110}
                            tick={ESTILO_EJE}
                            tickLine={false}
                            axisLine={false}
                        />

                        <Tooltip
                            contentStyle={ESTILO_TOOLTIP}
                            formatter={(v) => [bs(Number(v), moneda), 'Recaudado'] as [string, string]}
                        />

                        <Bar dataKey="total" radius={[0, 4, 4, 0]}>
                            {/*
                                Por defecto todas las barras salen del mismo color.
                                Un <Cell> por fila permite darle a cada área el suyo.

                                `key` es obligatoria siempre que se dibuja una lista:
                                es cómo React identifica cada elemento para no
                                repintar todo cuando cambia uno solo.
                            */}
                            {datos.map((fila, i) => (
                                <Cell key={fila.area} fill={colorSerie(i)} />
                            ))}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}

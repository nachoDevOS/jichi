import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { colorSerie, ESTILO_EJE, ESTILO_TOOLTIP } from '@/lib/graficos';
import type { CarnetsPorRubro } from '@/types/dashboard';

/**
 * Cuántos carnets de la gestión habilitan cada rubro.
 *
 * LAS BARRAS VAN HORIZONTALES (`layout="vertical"`) porque los nombres de rubro
 * son largos —«Comercializador»— y en barras verticales quedarían
 * inclinados o cortados. Horizontal, el nombre entra entero sobre el eje.
 *
 * OJO: ResponsiveContainer mide a su padre, así que el padre necesita una altura
 * concreta. Por eso el CardContent lleva `h-72`. Sin esa altura el gráfico se
 * calcula con altura cero y no se ve nada.
 */
export function GraficoCarnetsPorRubro({ datos, gestion }: { datos: CarnetsPorRubro[]; gestion: number }) {
    return (
        <Card className="lg:col-span-2">
            <CardHeader>
                <CardTitle>Carnets por rubro</CardTitle>
                <CardDescription>
                    Gestión {gestion}, contando solo las habilitaciones activas.
                </CardDescription>
            </CardHeader>

            <CardContent className="h-72">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart
                        data={datos}
                        layout="vertical"
                        margin={{ top: 4, right: 16, bottom: 0, left: 8 }}
                    >
                        <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" horizontal={false} />

                        <XAxis type="number" tick={ESTILO_EJE} tickLine={false} axisLine={false} />

                        <YAxis
                            type="category"
                            dataKey="rubro"
                            tick={ESTILO_EJE}
                            tickLine={false}
                            axisLine={false}
                            width={140}
                        />

                        <Tooltip
                            contentStyle={ESTILO_TOOLTIP}
                            cursor={{ fill: 'var(--secondary)' }}
                            formatter={(v) => [`${v} carnets`, 'Habilitados'] as [string, string]}
                        />

                        <Bar dataKey="cantidad" radius={[0, 4, 4, 0]}>
                            {/*
                                Un color por barra. Recharts pinta todas iguales si
                                no se le dan Cell: se usan los de la paleta
                                institucional, que son variables CSS y por eso
                                cambian solas entre modo claro y oscuro.
                            */}
                            {datos.map((fila, i) => (
                                <Cell key={fila.rubro} fill={colorSerie(i)} />
                            ))}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}

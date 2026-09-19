import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { colorSerie, ESTILO_EJE, ESTILO_TOOLTIP } from '@/lib/graficos';
import type { CarnetsPorTipo } from '@/types/dashboard';

/**
 * Cuántos carnets vigentes hay de cada tipo del catálogo.
 *
 * Se cuentan solo los VIGENTES y no todos los emitidos, porque la pregunta del
 * tablero es «cuánta gente está habilitada hoy», y un carnet revocado o vencido
 * no habilita a nadie.
 *
 * LAS BARRAS VAN HORIZONTALES (`layout="vertical"`) porque los nombres del
 * catálogo son largos —«Carnet Comercializador»— y en barras verticales
 * quedarían inclinados o cortados. Horizontal, el nombre entra entero sobre el
 * eje.
 *
 * OJO: ResponsiveContainer mide a su padre, así que el padre necesita una
 * altura concreta. Por eso el CardContent lleva `h-72`. Sin esa altura el
 * gráfico se calcula con altura cero y no se ve nada.
 */
export function GraficoCarnetsPorTipo({ datos, gestion }: { datos: CarnetsPorTipo[]; gestion: number }) {
    return (
        <Card className="lg:col-span-2">
            <CardHeader>
                <CardTitle>Carnets por tipo</CardTitle>
                <CardDescription>
                    Gestión {gestion}, contando solo los carnets vigentes hoy.
                </CardDescription>
            </CardHeader>

            <CardContent className="h-72">
                {datos.length === 0 ? (
                    /*
                     * Un gráfico sin filas se dibuja como un recuadro vacío que
                     * parece un error de la pantalla. El texto dice qué falta
                     * hacer, que es lo único accionable cuando el catálogo está
                     * sin cargar.
                     */
                    <p className="flex h-full items-center justify-center px-6 text-center text-sm text-muted-foreground">
                        Todavía no hay tipos de carnet cargados en el catálogo.
                    </p>
                ) : (
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
                                dataKey="tipo"
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
                                    <Cell key={fila.tipo} fill={colorSerie(i)} />
                                ))}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}

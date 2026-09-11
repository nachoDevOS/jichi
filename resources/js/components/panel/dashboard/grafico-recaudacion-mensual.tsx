import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ESTILO_EJE, ESTILO_TOOLTIP } from '@/lib/graficos';
import { bs } from '@/lib/utils';
import type { RecaudacionMes } from '@/types/dashboard';

/**
 * Línea de tiempo con lo recaudado en los últimos 12 meses.
 *
 * Los gráficos los dibuja `recharts`, una librería de React. Se arma como si
 * fuera HTML: cada pieza del gráfico es una etiqueta.
 *
 *   <ResponsiveContainer>  se estira al tamaño del contenedor padre
 *     <LineChart>          el gráfico y sus datos
 *       <CartesianGrid>    la cuadrícula de fondo
 *       <XAxis> <YAxis>    los ejes
 *       <Tooltip>          el cuadrito al pasar el mouse
 *       <Line>             la línea en sí
 *
 * OJO: ResponsiveContainer mide a su padre, así que el padre necesita una
 * altura concreta. Por eso el CardContent lleva `h-72`. Sin esa altura el
 * gráfico se calcula con altura cero y no se ve nada.
 */
export function GraficoRecaudacionMensual({
    datos,
    moneda,
}: {
    datos: RecaudacionMes[];
    moneda: string;
}) {
    return (
        <Card className="lg:col-span-3">
            <CardHeader>
                <CardTitle>Recaudación de los últimos 12 meses</CardTitle>
                <CardDescription>Montos en {moneda}, pagos no anulados.</CardDescription>
            </CardHeader>

            <CardContent className="h-72">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={datos} margin={{ top: 8, right: 8, bottom: 0, left: -12 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />

                        {/* dataKey dice qué campo de cada fila usar. 'etiqueta'
                            es el 'Sep 26' que armó el controlador en PHP. */}
                        <XAxis dataKey="etiqueta" tick={ESTILO_EJE} tickLine={false} axisLine={false} />

                        <YAxis tick={ESTILO_EJE} tickLine={false} axisLine={false} width={64} />

                        <Tooltip
                            contentStyle={ESTILO_TOOLTIP}
                            // formatter decide qué texto mostrar al pasar el mouse.
                            // Devuelve [valor, nombre]: "1.250,50 Bs" / "Recaudado".
                            formatter={(v) => [bs(Number(v), moneda), 'Recaudado'] as [string, string]}
                        />

                        <Line
                            type="monotone"
                            dataKey="total"
                            stroke="var(--grafico-1)"
                            strokeWidth={2.5}
                            dot={{ r: 3, fill: 'var(--grafico-1)' }}
                            activeDot={{ r: 5 }}
                        />
                    </LineChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}

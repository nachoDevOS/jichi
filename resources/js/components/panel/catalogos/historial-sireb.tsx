import { TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { fechaHora } from '@/lib/utils';
import type { ServicioAnterior, ServicioHistorial } from '@/types/catalogos';

/**
 * El servicio de SIREB actual y los anteriores de una fila de Aranceles.
 */
export function HistorialSireb({
    actual,
    anteriores,
    sirebDisponible,
}: {
    actual: ServicioHistorial;
    anteriores: ServicioAnterior[];
    /** false = SIREB no respondió: no se sabe cómo se llama hoy cada servicio. */
    sirebDisponible: boolean;
}) {
    return (
        <div className="space-y-6">
            {!sirebDisponible && (
                <Card className="border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
                    <CardContent className="flex items-start gap-3 pt-5 text-sm">
                        <TriangleAlert className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
                        <p className="text-amber-900 dark:text-amber-200">
                            Recaudaciones (SIREB) no responde: se ven los ids y las fechas, pero no el nombre de cada
                            servicio.
                        </p>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Servicio actual</CardTitle>
                </CardHeader>
                <CardContent>
                    <Entrada entrada={actual} sirebDisponible={sirebDisponible}>
                        Desde {fechaHora(actual.desde)}
                    </Entrada>
                </CardContent>
            </Card>

            <Card className="min-w-0">
                <CardHeader>
                    <CardTitle>Servicios anteriores</CardTitle>
                </CardHeader>
                <CardContent>
                    {anteriores.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Nunca cambió de servicio: siempre tuvo el actual.</p>
                    ) : (
                        <ol className="space-y-3">
                            {anteriores.map((h, i) => (
                                <li key={i}>
                                    <Entrada entrada={h} sirebDisponible={sirebDisponible}>
                                        {fechaHora(h.desde)} → {fechaHora(h.hasta)}
                                        {h.cambiado_por && ` · lo cambió ${h.cambiado_por}`}
                                    </Entrada>
                                </li>
                            ))}
                        </ol>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

/** Un servicio: cómo se llama hoy en SIREB, su id y el renglón de fechas. */
function Entrada({
    entrada,
    sirebDisponible,
    children,
}: {
    entrada: ServicioHistorial;
    sirebDisponible: boolean;
    children: ReactNode;
}) {
    return (
        <div className="min-w-0 rounded-md border border-border p-3 text-sm">
            {/* Un período sin servicio es real: la fila nace así hasta que alguien lo elige. */}
            {entrada.servicio_sireb === null ? (
                <Badge color="slate">Sin servicio asignado</Badge>
            ) : entrada.servicio_nombre !== null ? (
                <p className="font-medium">{entrada.servicio_nombre}</p>
            ) : (
                sirebDisponible && <Badge color="rose">Ya no está en SIREB</Badge>
            )}

            {entrada.servicio_sireb !== null && (
                <p className="mt-1 break-all font-mono text-xs text-muted-foreground">{entrada.servicio_sireb}</p>
            )}
            <p className="mt-1 text-xs text-muted-foreground">{children}</p>
        </div>
    );
}

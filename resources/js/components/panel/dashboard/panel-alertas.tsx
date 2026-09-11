import { AlertTriangle, CalendarClock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { AlertasOperativas } from '@/types/dashboard';

/**
 * Columna derecha del panel: lo que necesita atención hoy.
 *
 * Son dos tarjetas: los contadores generales y el detalle de los documentos
 * que están por vencer. Van juntas en un componente porque comparten el mismo
 * origen de datos (`alertas`) y siempre se muestran una encima de la otra.
 */
export function PanelAlertas({ alertas }: { alertas: AlertasOperativas }) {
    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <AlertTriangle className="size-4 text-warning" />
                        Alertas operativas
                    </CardTitle>
                </CardHeader>

                <CardContent className="space-y-2 text-sm">
                    <FilaAlerta etiqueta="Trámites con pago pendiente" valor={alertas.tramites_sin_pago} />
                    <FilaAlerta etiqueta="Esperando aprobación" valor={alertas.pendientes_aprobacion} />
                    <FilaAlerta etiqueta="Documentos vencidos" valor={alertas.documentos_vencidos} />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <CalendarClock className="size-4 text-accent" />
                        Por vencer ({alertas.dias_alerta} días)
                    </CardTitle>
                </CardHeader>

                <CardContent className="space-y-3">
                    {alertas.documentos_por_vencer.map((d) => (
                        <div key={d.id} className="flex items-start justify-between gap-3 text-sm">
                            <div className="min-w-0">
                                <p className="truncate font-medium">{d.solicitante}</p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {d.tipo} · {d.codigo_verificacion}
                                </p>
                            </div>

                            {/*
                                A una semana o menos el aviso pasa a rojo. El ?? 99
                                cubre el caso de un documento sin fecha de
                                vencimiento, donde dias_para_vencer llega null.
                            */}
                            <Badge color={(d.dias_para_vencer ?? 99) <= 7 ? 'rose' : 'amber'}>
                                {d.dias_para_vencer} d
                            </Badge>
                        </div>
                    ))}

                    {alertas.documentos_por_vencer.length === 0 && (
                        <p className="py-4 text-center text-sm text-muted-foreground">
                            Sin vencimientos próximos.
                        </p>
                    )}
                </CardContent>
            </Card>
        </>
    );
}

/**
 * Un renglón "etiqueta ......... número".
 *
 * Verde cuando el contador está en cero (no hay nada que hacer) y ámbar
 * cuando hay pendientes: el color dice si hay trabajo sin necesidad de leer.
 */
function FilaAlerta({ etiqueta, valor }: { etiqueta: string; valor: number }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-muted-foreground">{etiqueta}</span>
            <Badge color={valor > 0 ? 'amber' : 'emerald'}>{valor}</Badge>
        </div>
    );
}

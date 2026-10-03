import { useForm } from '@inertiajs/react';
import { Printer, RefreshCw } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermisos } from '@/hooks/use-permisos';
import { bs, cn, fecha, fechaHora } from '@/lib/utils';
import type { ReciboDelCupo, VentaSireb } from '@/types/aprovechamientos';

/**
 * El cobro de un documento, que se paga en SIREB. La comparten las cuatro fichas:
 * el estado de la liquidación, el código con el que se paga, el botón que pregunta
 * por el pago (si está pagado, lo aprueba) y el recibo una vez emitido.
 */
export function TarjetaRecaudaciones({
    monto,
    sireb,
    recibo,
    puedeVerificar,
    rutaVerificar,
    permiso,
    className,
}: {
    monto: number;
    sireb: VentaSireb | null;
    recibo: ReciboDelCupo | null;
    puedeVerificar: boolean;
    /** La ruta ya resuelta: `route('carnets.verificar-pago', id)`. */
    rutaVerificar: string;
    /** El permiso del botón: el `crear` del documento. */
    permiso: string;
    className?: string;
}) {
    const { puede } = usePermisos();
    const verificacion = useForm({});

    return (
        <Card className={cn('h-fit min-w-0', className)}>
            <CardHeader>
                <CardTitle>Pago en Recaudaciones</CardTitle>
            </CardHeader>

            <CardContent className="space-y-3 text-sm">
                <div className="flex justify-between gap-3">
                    <span className="text-muted-foreground">Monto</span>
                    <span className="font-semibold tabular-nums">{bs(monto)}</span>
                </div>

                {sireb && (
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground">Recaudaciones</span>
                        <Badge color={sireb.estado_color}>{sireb.estado_etiqueta}</Badge>
                    </div>
                )}

                {/* Con este código el titular paga en SIREB. */}
                {sireb?.codigo_publico && (
                    <div className="flex justify-between gap-3">
                        <span className="text-muted-foreground">Código de pago</span>
                        <span className="font-mono font-medium">{sireb.codigo_publico}</span>
                    </div>
                )}

                {recibo ? (
                    <div className="space-y-2 rounded-md border p-3">
                        <div className="flex justify-between gap-3">
                            <span className="text-muted-foreground">Recibo</span>
                            <span className="font-mono font-medium">{recibo.numero_recibo}</span>
                        </div>
                        {recibo.numero_boleta && (
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">Boleta</span>
                                <span className="text-right">
                                    {recibo.numero_boleta}
                                    {recibo.entidad_bancaria && <> · {recibo.entidad_bancaria}</>}
                                </span>
                            </div>
                        )}
                        <div className="flex justify-between gap-3">
                            <span className="text-muted-foreground">Pagado</span>
                            <span>{recibo.fecha_pago ? fecha(recibo.fecha_pago) : fechaHora(recibo.emitido_en)}</span>
                        </div>
                        {puede('recibos.imprimir') && (
                            <a
                                href={route('recibos.imprimir', recibo.id)}
                                target="_blank"
                                rel="noreferrer"
                                className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'w-full')}
                            >
                                <Printer className="size-4" />
                                Imprimir recibo
                            </a>
                        )}
                    </div>
                ) : (
                    <p className="text-muted-foreground">
                        Se paga en Recaudaciones. Cuando lo validen allá, queda aprobado y se emite el recibo.
                    </p>
                )}

                {puede(permiso) && puedeVerificar && (
                    <Button
                        variant="outline"
                        className="w-full"
                        disabled={verificacion.processing}
                        onClick={() => verificacion.post(rutaVerificar, { preserveScroll: true })}
                    >
                        <RefreshCw className={cn('size-4', verificacion.processing && 'animate-spin')} />
                        Verificar pago
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

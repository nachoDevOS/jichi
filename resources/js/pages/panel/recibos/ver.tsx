import { Head, Link, router, usePage } from '@inertiajs/react';
import { Printer, ReceiptText } from 'lucide-react';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha, fechaHora } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { ReciboFicha } from '@/types/recibos';

/**
 *  La ficha de un recibo: el comprobante de un documento pagado en SIREB
 */
export default function VerRecibo({ recibo }: { recibo: ReciboFicha }) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;

    return (
        <LayoutPanel
            titulo={`Recibo ${recibo.numero_recibo}`}
            descripcion={`${recibo.beneficiario ?? '—'} · ${fechaHora(recibo.emitido_en)}`}
            acciones={
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" onClick={() => router.visit(route('recibos.index'))}>
                        <ReceiptText className="size-4" />
                        Volver a recibos
                    </Button>

                    {/* Abre una pestaña: lo que vuelve es un PDF, y se imprime desde el visor. */}
                    {puede('recibos.imprimir') && (
                        <a
                            href={route('recibos.imprimir', recibo.id)}
                            target="_blank"
                            rel="noreferrer"
                            className={cn(buttonVariants({ variant: 'default' }))}
                        >
                            <Printer className="size-4" />
                            Recibo
                        </a>
                    )}
                </div>
            }
        >
            <Head title={`Recibo ${recibo.numero_recibo}`} />

            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>El pago</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-3 text-sm">
                        <div>
                            <p className="text-muted-foreground">Concepto</p>
                            <p className="font-medium">{recibo.concepto}</p>
                        </div>
                        {recibo.documento_pagado && (
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">Documento</span>
                                <Link href={recibo.documento_pagado.url} className="font-medium text-primary hover:underline">
                                    {recibo.documento_pagado.nombre}
                                </Link>
                            </div>
                        )}
                        <Dato etiqueta="Monto" valor={bs(recibo.monto_total, institucion.moneda)} />
                        <Dato etiqueta="Boleta" valor={recibo.numero_boleta ?? '—'} mono />
                        <Dato etiqueta="Banco" valor={recibo.entidad_bancaria ?? '—'} />
                        <Dato etiqueta="Pagado el" valor={fecha(recibo.fecha_pago)} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>El comprobante</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-3 text-sm">
                        <Dato etiqueta="Número" valor={recibo.numero_recibo} mono />
                        <Dato etiqueta="A nombre de" valor={recibo.beneficiario ?? '—'} />
                        <Dato etiqueta="C.I." valor={recibo.documento ?? '—'} mono />
                        <Dato etiqueta="Emitido" valor={fechaHora(recibo.emitido_en)} />
                    </CardContent>
                </Card>
            </div>
        </LayoutPanel>
    );
}

function Dato({ etiqueta, valor, mono = false }: { etiqueta: string; valor: string; mono?: boolean }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted-foreground">{etiqueta}</span>
            <span className={mono ? 'text-right font-mono font-medium' : 'text-right font-medium'}>{valor}</span>
        </div>
    );
}

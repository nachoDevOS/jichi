import { Head, router, usePage } from '@inertiajs/react';
import { Paperclip, Printer, ReceiptText, TriangleAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import LayoutPanel from '@/layouts/layout-panel';
import { usePermisos } from '@/hooks/use-permisos';
import { bs, cn, fechaHora } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { ReciboFicha } from '@/types/caja';

/**
 *  LA FICHA DE UN RECIBO
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
                    <Button variant="outline" onClick={() => router.visit(route('caja.index'))}>
                        <ReceiptText className="size-4" />
                        Volver a caja
                    </Button>

                    {/*
                        IMPRIMIR ABRE UNA PESTAÑA, no navega con Inertia: lo que
                        vuelve es un PDF, y el visor del navegador es desde donde
                        el operador aprieta imprimir. Con `router.visit` Inertia
                        esperaría una respuesta suya y no sabría qué hacer con el
                        archivo.
                    */}
                    {puede('recibos.imprimir') && (
                        <a
                            href={route('recibos.imprimir', recibo.id)}
                            target="_blank"
                            rel="noreferrer"
                            className={cn(buttonVariants({ variant: 'default' }))}
                        >
                            <Printer className="size-4" />
                            Imprimir recibo
                        </a>
                    )}
                </div>
            }
        >
            <Head title={`Recibo ${recibo.numero_recibo}`} />

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="min-w-0 lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Detalle</CardTitle>
                    </CardHeader>

                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-2.5 font-medium">Concepto</th>
                                        <th className="px-5 py-2.5 font-medium">Boleta</th>
                                        <th className="px-5 py-2.5 text-right font-medium">Monto</th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-border">
                                    {recibo.pagos.map((p) => (
                                        <tr key={p.id}>
                                            <td className="px-5 py-2.5">
                                                <p className="font-medium">{p.concepto}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {p.detalle ?? '—'}
                                                </p>
                                            </td>

                                            <td className="px-5 py-2.5">
                                                {/* La boleta del banco. Siempre hay:
                                                    todo pago es un depósito. */}
                                                {p.comprobante_url && (
                                                    <a
                                                        href={p.comprobante_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="mt-1 flex items-center gap-1 text-xs text-primary hover:underline"
                                                        title="Abrir la boleta del depósito"
                                                    >
                                                        <Paperclip className="size-3" />
                                                        {p.nro_transaccion ?? 'boleta'}
                                                    </a>
                                                )}
                                            </td>

                                            <td className="px-5 py-2.5 text-right font-medium tabular-nums">
                                                {bs(p.monto_parcial, institucion.moneda)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>

                                <tfoot className="border-t border-border">
                                    <tr>
                                        <td className="px-5 py-3 font-medium" colSpan={2}>
                                            Total del comprobante
                                        </td>
                                        <td className="px-5 py-3 text-right text-lg font-semibold tabular-nums">
                                            {bs(recibo.monto_total, institucion.moneda)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>El comprobante</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-3 text-sm">
                            <Dato etiqueta="Número" valor={recibo.numero_recibo} mono />
                            <Dato etiqueta="A nombre de" valor={recibo.beneficiario ?? '—'} />
                            <Dato etiqueta="C.I." valor={recibo.documento ?? '—'} mono />
                            <Dato etiqueta="Emitido" valor={fechaHora(recibo.emitido_en)} />

                            <div>
                                <p className="text-muted-foreground">Concepto</p>
                                <p className="font-medium">{recibo.concepto}</p>
                            </div>
                        </CardContent>
                    </Card>

                    {/*
                        EL AVISO DE DESCUADRE. Lo impreso está congelado; esto
                        compara contra lo que hay hoy. No se tapa recalculando:
                        el papel entregado no puede cambiar porque después se
                        corrija un abono.
                    */}
                    {!recibo.cuadra && (
                        <Card className="border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
                            <CardContent className="flex items-start gap-3 pt-5 text-sm">
                                <TriangleAlert className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
                                <div>
                                    <p className="font-medium text-amber-900 dark:text-amber-200">
                                        Este recibo no cuadra
                                    </p>
                                    <p className="text-amber-800/80 dark:text-amber-200/80">
                                        El papel dice {bs(recibo.monto_total, institucion.moneda)} y sus
                                        abonos suman hoy {bs(recibo.monto_actual, institucion.moneda)}.
                                        Alguien corrigió un cobro después de emitirlo.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </LayoutPanel>
    );
}

function Dato({ etiqueta, valor, mono = false }: { etiqueta: string; valor: string; mono?: boolean }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted-foreground">{etiqueta}</span>
            <span className={mono ? 'text-right font-mono font-medium' : 'text-right font-medium'}>
                {valor}
            </span>
        </div>
    );
}

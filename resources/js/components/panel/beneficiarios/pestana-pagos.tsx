import { Link } from '@inertiajs/react';
import { Printer, Receipt, Wallet } from 'lucide-react';
import { buttonVariants } from '@/components/ui/button';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { usePermisos } from '@/hooks/use-permisos';
import { bs, cn, fechaHora, hace } from '@/lib/utils';
import type { ReciboDelBeneficiario } from '@/types/beneficiarios';
import { Cifra } from './partes-ficha';

/**
 * Sus recibos y lo que todavía falta pagar en Recaudaciones.
 */
export function PestanaPagos({
    recibos,
    deuda,
    moneda,
}: {
    recibos: ReciboDelBeneficiario[];
    deuda: number;
    moneda: string;
}) {
    const { puede } = usePermisos();
    const total = recibos.reduce((suma, r) => suma + r.monto_total, 0);

    return (
        <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-3">
                <Cifra icono={Receipt} etiqueta="Pagado" valor={bs(total, moneda)} detalle="suma de sus recibos" />
                <Cifra icono={Receipt} etiqueta="Recibos" valor={String(recibos.length)} detalle={recibos[0] ? `el último ${hace(recibos[0].emitido_en)}` : 'Ninguno emitido'} />
                <Cifra
                    icono={Wallet}
                    etiqueta="Por pagar"
                    valor={bs(deuda, moneda)}
                    detalle={deuda > 0 ? 'pendiente en Recaudaciones' : 'Está al día'}
                    className={deuda > 0 ? 'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10' : undefined}
                />
            </div>

            {recibos.length === 0 ? (
                <div className="rounded-lg border border-border">
                    <EstadoVacio icono={Receipt} titulo="Sin recibos" descripcion="Todavía no se le emitió ningún comprobante." />
                </div>
            ) : (
                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-secondary/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2.5 font-medium">N°</th>
                                <th className="px-4 py-2.5 font-medium">Emitido</th>
                                <th className="px-4 py-2.5 font-medium">Concepto</th>
                                <th className="px-4 py-2.5 text-right font-medium">Monto</th>
                                <th className="px-4 py-2.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {recibos.map((r) => (
                                <tr key={r.id} className="hover:bg-secondary/50">
                                    <td className="px-4 py-2.5">
                                        {puede('recibos.ver') ? (
                                            <Link
                                                href={route('recibos.show', r.id)}
                                                className="font-mono font-medium tabular-nums text-primary hover:underline"
                                            >
                                                {r.numero}
                                            </Link>
                                        ) : (
                                            <span className="font-mono font-medium tabular-nums">{r.numero}</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2.5 text-xs text-muted-foreground">
                                        {fechaHora(r.emitido_en)}
                                        <span className="block">{hace(r.emitido_en)}</span>
                                    </td>
                                    <td className="px-4 py-2.5">
                                        {r.concepto ?? '—'}
                                    </td>
                                    <td className="px-4 py-2.5 text-right font-medium tabular-nums">{bs(r.monto_total, moneda)}</td>
                                    <td className="px-4 py-2.5">
                                        {puede('recibos.imprimir') && (
                                            <div className="flex justify-end">
                                                <a
                                                    href={route('recibos.imprimir', r.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    title="Recibo"
                                                    aria-label={`Imprimir el recibo ${r.numero}`}
                                                    className={cn(buttonVariants({ variant: 'outline', size: 'icon' }))}
                                                >
                                                    <Printer className="size-4" />
                                                </a>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

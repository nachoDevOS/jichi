import { QrCode, RefreshCw, X } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';
import { TextoCopiable } from '@/components/comunes/texto-copiable';
import { QrSimulado } from '@/components/comunes/qr-simulado';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { bs, cn } from '@/lib/utils';

/** Qué se paga: lo arma cada ficha con sus propios datos. */
export interface DetallePago {
    concepto: string;
    numero?: string | null;
    titular?: string | null;
    documento?: string | null;
    /** Un renglón propio del trámite: kilos, ruta, gestión. */
    detalle?: string | null;
}

/** El pago por QR, en una ventana al centro. El QR es de muestra: el cobro real sigue en SIREB. */
export function ModalPagoQr({
    abierto,
    monto,
    codigoPago,
    pago,
    puedeVerificar,
    verificando,
    onVerificar,
    onCerrar,
}: {
    abierto: boolean;
    monto: number;
    codigoPago: string | null;
    pago?: DetallePago;
    puedeVerificar: boolean;
    verificando: boolean;
    onVerificar: () => void;
    onCerrar: () => void;
}) {
    useEffect(() => {
        if (!abierto) return;
        const alPresionar = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onCerrar();
        };
        document.addEventListener('keydown', alPresionar);
        return () => document.removeEventListener('keydown', alPresionar);
    }, [abierto, onCerrar]);

    if (!abierto) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-pago-qr"
        >
            <div className="w-full max-w-3xl rounded-xl border border-border bg-card shadow-lg">
                <div className="flex items-start gap-4 border-b p-5">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <QrCode className="size-5" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h2 id="titulo-pago-qr" className="font-semibold">
                            Pagar por QR
                        </h2>
                        <p className="text-sm text-muted-foreground">Pago en Recaudaciones del GAD Beni</p>
                    </div>
                    <button type="button" onClick={onCerrar} className="rounded-md p-1 text-muted-foreground hover:bg-muted" aria-label="Cerrar">
                        <X className="size-5" />
                    </button>
                </div>

                <div className="grid gap-6 p-5 text-sm md:grid-cols-[1fr_18rem]">
                    <div className="flex flex-col items-center justify-center gap-3">
                        <div className="rounded-lg border bg-white p-3 shadow-sm">
                            <QrSimulado texto={codigoPago ?? String(monto)} className="size-64 sm:size-80" />
                        </div>
                        <Badge color="amber">QR de muestra: todavía no cobra</Badge>
                    </div>

                    <div className="space-y-4">
                        <div className="rounded-lg bg-muted/40 p-4 text-center">
                            <p className="text-muted-foreground">Monto a pagar</p>
                            <p className="text-3xl font-semibold tabular-nums">{bs(monto)}</p>
                        </div>

                        <dl className="space-y-2">
                            {pago && <Renglon etiqueta="Concepto" valor={pago.concepto} />}
                            {pago?.numero && <Renglon etiqueta="N°" valor={pago.numero} />}
                            {pago?.titular && <Renglon etiqueta="Titular" valor={pago.titular} />}
                            {pago?.documento && <Renglon etiqueta="C.I." valor={pago.documento} />}
                            {pago?.detalle && <Renglon etiqueta="Detalle" valor={pago.detalle} />}
                            <Renglon etiqueta="Código de pago" valor={codigoPago ? <TextoCopiable texto={codigoPago} className="-mr-1.5" /> : '—'} />
                        </dl>
                    </div>
                </div>

                <div className="flex flex-col-reverse gap-2 border-t p-4 sm:flex-row sm:justify-end">
                    <Button key="cerrar-qr" type="button" variant="outline" onClick={onCerrar}>
                        Cerrar
                    </Button>
                    {puedeVerificar && (
                        <Button key="verificar-qr" type="button" disabled={verificando} onClick={onVerificar}>
                            <RefreshCw className={cn('size-4', verificando && 'animate-spin')} />
                            Verificar pago
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}

function Renglon({ etiqueta, valor }: { etiqueta: string; valor: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-3">
            <dt className="text-muted-foreground">{etiqueta}</dt>
            <dd className="text-right font-medium">{valor}</dd>
        </div>
    );
}

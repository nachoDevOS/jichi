import { X } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';
import { TextoCopiable } from '@/components/comunes/texto-copiable';
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

/** El armazón de las ventanas de pago (QR y carga de boleta), para que se vean iguales. */
export function VentanaPago({
    abierto,
    titulo,
    subtitulo,
    icono,
    tono,
    bloqueada = false,
    pie,
    onCerrar,
    children,
}: {
    abierto: boolean;
    titulo: string;
    subtitulo: string;
    icono: ReactNode;
    tono: 'sky' | 'emerald';
    /** Mientras se envía, Escape y la X no cierran: se perdería lo cargado. */
    bloqueada?: boolean;
    pie: ReactNode;
    onCerrar: () => void;
    children: ReactNode;
}) {
    useEffect(() => {
        if (!abierto) return;
        const alPresionar = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && !bloqueada) onCerrar();
        };
        document.addEventListener('keydown', alPresionar);
        // Sin esto la ficha de atrás se desplaza con la rueda por debajo de la ventana.
        const desbordeAnterior = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', alPresionar);
            document.body.style.overflow = desbordeAnterior;
        };
    }, [abierto, bloqueada, onCerrar]);

    if (!abierto) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-ventana-pago"
            onMouseDown={(e) => e.target === e.currentTarget && !bloqueada && onCerrar()}
        >
            <div className="w-full max-w-3xl overflow-hidden rounded-xl border border-border bg-card shadow-lg">
                <div className="flex items-start gap-4 border-b p-5">
                    <div
                        className={cn(
                            'flex size-10 shrink-0 items-center justify-center rounded-full',
                            tono === 'sky'
                                ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300'
                                : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
                        )}
                    >
                        {icono}
                    </div>
                    <div className="min-w-0 flex-1">
                        <h2 id="titulo-ventana-pago" className="font-semibold">
                            {titulo}
                        </h2>
                        <p className="text-sm text-muted-foreground">{subtitulo}</p>
                    </div>
                    <button
                        type="button"
                        onClick={onCerrar}
                        disabled={bloqueada}
                        className="rounded-md p-1 text-muted-foreground hover:bg-muted disabled:opacity-50"
                        aria-label="Cerrar"
                    >
                        <X className="size-5" />
                    </button>
                </div>

                {children}

                <div className="flex flex-col-reverse gap-2 border-t bg-muted/30 p-4 sm:flex-row sm:justify-end">
                    {pie}
                </div>
            </div>
        </div>
    );
}

/** El monto, qué se paga y el código: lo mismo a la vista en las dos formas de pago. */
export function ResumenCobro({
    monto,
    codigoPago,
    pago,
}: {
    monto: number;
    codigoPago: string | null;
    pago?: DetallePago;
}) {
    return (
        <div className="space-y-4">
            <div className="rounded-lg bg-secondary/60 p-4">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Monto a pagar</p>
                <p className="text-3xl font-semibold tabular-nums">{bs(monto)}</p>
                {codigoPago && (
                    <div className="mt-3 border-t border-foreground/10 pt-3">
                        <p className="text-xs text-muted-foreground">Código de pago</p>
                        <TextoCopiable texto={codigoPago} className="-ml-1.5 text-base tracking-wider" />
                    </div>
                )}
            </div>

            {pago && (
                <dl className="space-y-2 text-sm">
                    <Renglon etiqueta="Concepto" valor={pago.concepto} />
                    {pago.numero && <Renglon etiqueta="N°" valor={pago.numero} />}
                    {pago.titular && <Renglon etiqueta="Titular" valor={pago.titular} />}
                    {pago.documento && <Renglon etiqueta="C.I." valor={pago.documento} />}
                    {pago.detalle && <Renglon etiqueta="Detalle" valor={pago.detalle} />}
                </dl>
            )}
        </div>
    );
}

function Renglon({ etiqueta, valor }: { etiqueta: string; valor: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-3">
            <dt className="shrink-0 text-muted-foreground">{etiqueta}</dt>
            <dd className="text-right font-medium">{valor}</dd>
        </div>
    );
}

/** Los pasos que sigue el titular, numerados: se leen de arriba abajo sin pensar. */
export function PasosNumerados({ pasos }: { pasos: ReactNode[] }) {
    return (
        <ol className="space-y-2 text-sm">
            {pasos.map((paso, i) => (
                <li key={i} className="flex items-start gap-2.5">
                    <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-primary-foreground">
                        {i + 1}
                    </span>
                    <span className="leading-snug">{paso}</span>
                </li>
            ))}
        </ol>
    );
}

import { AlertTriangle, ArrowRight, FilePlus, LoaderCircle, X } from 'lucide-react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { bs } from '@/lib/utils';
import type { CotizacionRenovacion } from '@/types/aprovechamientos';

/**
 * La pregunta antes de pedir otra liquidación a SIREB, con el monto que va a
 * cobrar según la tarifa vigente del catálogo. Sin precio no se puede confirmar.
 */
export function ConfirmarNuevaLiquidacion({
    abierto,
    cotizando,
    cotizacion,
    procesando,
    onConfirmar,
    onCerrar,
}: {
    abierto: boolean;
    cotizando: boolean;
    cotizacion: CotizacionRenovacion | null;
    procesando: boolean;
    onConfirmar: () => void;
    onCerrar: () => void;
}) {
    useEffect(() => {
        if (!abierto) return;
        const alPresionar = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && !procesando) onCerrar();
        };
        document.addEventListener('keydown', alPresionar);
        return () => document.removeEventListener('keydown', alPresionar);
    }, [abierto, procesando, onCerrar]);

    if (!abierto) return null;

    const listo = !cotizando && cotizacion !== null;
    const cambio = listo && cotizacion.monto !== null && Math.abs(cotizacion.monto - cotizacion.anterior) >= 0.005;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm animate-in fade-in"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-nueva-liquidacion"
            onClick={() => !procesando && onCerrar()}
        >
            <div
                className="relative w-full max-w-sm rounded-2xl border border-border bg-card p-6 text-center shadow-2xl animate-in zoom-in-95 fade-in"
                onClick={(e) => e.stopPropagation()}
            >
                <button
                    type="button"
                    onClick={onCerrar}
                    disabled={procesando}
                    className="absolute top-3 right-3 rounded-md p-1 text-muted-foreground hover:bg-muted disabled:opacity-50"
                    aria-label="Cerrar"
                >
                    <X className="size-5" />
                </button>

                <div className="mx-auto flex size-16 items-center justify-center rounded-full bg-primary/10 text-primary ring-8 ring-primary/5">
                    <FilePlus className="size-8" />
                </div>

                <h2 id="titulo-nueva-liquidacion" className="mt-5 text-lg font-semibold">
                    ¿Generar una nueva liquidación?
                </h2>
                <p className="mt-2 text-sm text-muted-foreground">Se emitirá un nuevo código de pago para cobrar este trámite.</p>

                {/* El monto que va a cobrar, con la tarifa de hoy. */}
                <div className="mt-5 rounded-xl border bg-muted/40 p-4" aria-live="polite">
                    {!listo ? (
                        <div className="flex items-center justify-center gap-2 py-3 text-sm text-muted-foreground">
                            <LoaderCircle className="size-4 animate-spin" />
                            Consultando la tarifa vigente…
                        </div>
                    ) : cotizacion.monto === null ? (
                        <div className="flex gap-2 text-left text-sm text-rose-700 dark:text-rose-300">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            <p>{cotizacion.motivo}</p>
                        </div>
                    ) : (
                        <>
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Monto a cobrar</p>
                            <p className="mt-1 text-3xl font-bold tabular-nums">{bs(cotizacion.monto)}</p>
                            {cambio ? (
                                <p className="mt-2 flex items-center justify-center gap-1.5 text-sm text-amber-700 dark:text-amber-300">
                                    La tarifa cambió:
                                    <span className="tabular-nums line-through opacity-70">{bs(cotizacion.anterior)}</span>
                                    <ArrowRight className="size-3.5" />
                                    <span className="font-semibold tabular-nums">{bs(cotizacion.monto)}</span>
                                </p>
                            ) : (
                                <p className="mt-2 text-sm text-muted-foreground">Mismo monto que la liquidación vencida.</p>
                            )}
                        </>
                    )}
                </div>

                <div className="mt-6 grid grid-cols-2 gap-3">
                    <Button key="no-renovar" type="button" variant="outline" size="lg" onClick={onCerrar} disabled={procesando}>
                        No
                    </Button>
                    <Button
                        key="si-renovar"
                        type="button"
                        size="lg"
                        onClick={onConfirmar}
                        disabled={procesando || !listo || cotizacion.monto === null}
                    >
                        {procesando && <LoaderCircle className="size-4 animate-spin" />}
                        {procesando ? 'Generando…' : 'Sí, generar'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

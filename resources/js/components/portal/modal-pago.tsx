import { Info, X } from 'lucide-react';
import { createContext, useContext, useEffect, useRef, useState, type PropsWithChildren } from 'react';
import { bs } from '@/lib/utils';

/** Lo que el modal muestra: todo sale de la tarjeta, menos la imagen del QR. */
export interface Pago {
    concepto: string;
    codigo: string | null;
    monto: number;
    moneda: string;
    /** El PNG del QR, servido por Portal\PagoSimuladoController. */
    qr: string;
}

const ContextoPago = createContext<((pago: Pago) => void) | null>(null);

/** Abre el modal de pago desde cualquier pantalla del portal. */
export function useModalPago() {
    const abrir = useContext(ContextoPago);

    if (!abrir) throw new Error('useModalPago va dentro de <ProveedorPago>, que monta LayoutPortal.');

    return abrir;
}

/**
 *  El modal GLOBAL de «Pagar con QR»
 *
 *  Uno solo para todo el portal: lo monta el layout y lo abre cualquier tarjeta.
 *  HOY es una demostración sin banco: el botón solo aparece con
 *  `jichi.portal.pago_qr` encendido, que por defecto es solo en local.
 */
export function ProveedorPago({ children }: PropsWithChildren) {
    const [pago, setPago] = useState<Pago | null>(null);
    const cerrarRef = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        if (!pago) return;

        const alTeclear = (e: KeyboardEvent) => e.key === 'Escape' && setPago(null);
        document.addEventListener('keydown', alTeclear);
        // La página de atrás no se desplaza mientras el modal está abierto.
        const desborde = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        cerrarRef.current?.focus();

        return () => {
            document.removeEventListener('keydown', alTeclear);
            document.body.style.overflow = desborde;
        };
    }, [pago]);

    return (
        <ContextoPago.Provider value={setPago}>
            {children}

            {pago && (
                <div
                    className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/60 p-0 backdrop-blur-sm sm:items-center sm:p-4"
                    onClick={() => setPago(null)}
                >
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="titulo-pago"
                        onClick={(e) => e.stopPropagation()}
                        className="max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:max-w-md sm:rounded-3xl sm:p-6"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                    Pagar con QR
                                </p>
                                <h2 id="titulo-pago" className="font-bold text-rio-profundo">
                                    {pago.concepto}
                                </h2>
                            </div>
                            <button
                                ref={cerrarRef}
                                type="button"
                                onClick={() => setPago(null)}
                                aria-label="Cerrar"
                                className="rounded-full p-2 text-slate-500 hover:bg-slate-100"
                            >
                                <X className="size-5" />
                            </button>
                        </div>

                        <div className="mt-4 text-center">
                            <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                Monto a pagar
                            </p>
                            <p className="text-4xl font-extrabold text-rio-profundo">{bs(pago.monto, pago.moneda)}</p>
                            {pago.codigo && (
                                <p className="mt-1 font-mono text-xs tracking-wider text-slate-500">{pago.codigo}</p>
                            )}
                        </div>
                        <div className="mx-auto mt-4 w-fit overflow-hidden rounded-xl border-2 border-rio-claro">
                            <img src={pago.qr} alt="Código QR para pagar" className="size-56 bg-slate-100 sm:size-60" />
                        </div>

                        <p className="mt-4 flex gap-2 rounded-2xl bg-sky-50 p-3 text-xs text-sky-900">
                            <Info className="size-4 shrink-0" />
                            Escanee el código con la app de su banco. El pago con QR se registra solo: no necesita
                            llevar ningún comprobante a ventanilla del SEDAG.
                        </p>

                        <button
                            type="button"
                            onClick={() => setPago(null)}
                            className="mt-4 w-full rounded-xl bg-rio px-4 py-3 font-semibold text-white hover:bg-rio-profundo"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            )}
        </ContextoPago.Provider>
    );
}

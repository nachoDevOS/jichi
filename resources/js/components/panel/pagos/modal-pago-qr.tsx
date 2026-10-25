import { router } from '@inertiajs/react';
import { LoaderCircle, QrCode, RefreshCw, TriangleAlert } from 'lucide-react';
import { useEffect, useState } from 'react';
import { QrSimulado } from '@/components/comunes/qr-simulado';
import { PasosNumerados, ResumenCobro, VentanaPago, type DetallePago } from '@/components/panel/pagos/ventana-pago';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { bs, cn } from '@/lib/utils';

/** El pago por QR, en una ventana al centro. El QR es de muestra: el cobro real sigue en SIREB. */
export function ModalPagoQr({
    abierto,
    monto,
    codigoPago,
    rutaConsultar,
    pago,
    puedeVerificar,
    verificando,
    onVerificar,
    onCerrar,
}: {
    abierto: boolean;
    monto: number;
    codigoPago: string | null;
    /** Al abrirse pregunta a SIREB: el QR sale solo con el cobro pendiente y sin pago. */
    rutaConsultar?: string;
    pago?: DetallePago;
    puedeVerificar: boolean;
    verificando: boolean;
    onVerificar: () => void;
    onCerrar: () => void;
}) {
    const [consulta, setConsulta] = useState<{
        puede: boolean;
        mensaje: string | null;
    } | null>(null);

    useEffect(() => {
        if (!abierto || !rutaConsultar) return;
        setConsulta(null);
        const peticion = new AbortController();
        fetch(rutaConsultar, {
            headers: { Accept: 'application/json' },
            signal: peticion.signal,
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((r: { puede: boolean; mensaje: string | null }) => {
                setConsulta(r);
                // Pudo traer un pago o el vencimiento: la ficha de atrás se pone al día.
                if (!r.puede) router.reload();
            })
            .catch(() => {
                if (!peticion.signal.aborted)
                    setConsulta({
                        puede: false,
                        mensaje: 'No se pudo consultar el cobro en Recaudaciones. Intente de nuevo.',
                    });
            });

        return () => peticion.abort();
    }, [abierto, rutaConsultar]);

    const muestraQr = !rutaConsultar || Boolean(consulta?.puede);

    return (
        <VentanaPago
            abierto={abierto}
            titulo="Pagar por QR"
            subtitulo="El titular paga desde la app de su banco"
            icono={<QrCode className="size-5" />}
            tono="sky"
            onCerrar={onCerrar}
            pie={
                <>
                    <Button key="cerrar-qr" type="button" variant="outline" onClick={onCerrar}>
                        Cerrar
                    </Button>
                    {puedeVerificar && muestraQr && (
                        <Button key="verificar-qr" type="button" disabled={verificando} onClick={onVerificar}>
                            <RefreshCw className={cn('size-4', verificando && 'animate-spin')} />
                            {verificando ? 'Revisando…' : 'Ya pagó: verificar'}
                        </Button>
                    )}
                </>
            }
        >
            {rutaConsultar && !consulta && (
                <p className="flex items-center justify-center gap-2 py-16 text-sm text-muted-foreground">
                    <LoaderCircle className="size-5 animate-spin" />
                    Consultando el cobro en Recaudaciones…
                </p>
            )}

            {consulta && !consulta.puede && (
                <div className="p-5">
                    <p className="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-3 text-sm text-amber-900 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-100 dark:ring-amber-500/30">
                        <TriangleAlert className="mt-0.5 size-5 shrink-0" />
                        {consulta.mensaje}
                    </p>
                </div>
            )}

            {muestraQr && (
                <div className="grid gap-6 p-5 text-sm md:grid-cols-[1fr_18rem]">
                    <div className="flex flex-col items-center gap-4">
                        <div className="rounded-xl border bg-white p-3 shadow-sm">
                            <QrSimulado texto={codigoPago ?? String(monto)} className="size-56 sm:size-72" />
                        </div>
                        <Badge color="amber">QR de muestra: todavía no cobra</Badge>
                        <div className="w-full max-w-sm rounded-lg border p-4">
                            <p className="mb-3 font-medium">Cómo paga el titular</p>
                            <PasosNumerados
                                pasos={[
                                    'Abre la app de su banco y elige «Pagar con QR».',
                                    'Escanea este código con la cámara del teléfono.',
                                    <>
                                        Confirma el pago de <strong className="tabular-nums">{bs(monto)}</strong>.
                                    </>,
                                    'Cuando le salga el comprobante, apriete «Ya pagó: verificar».',
                                ]}
                            />
                        </div>
                    </div>

                    <ResumenCobro monto={monto} codigoPago={codigoPago} pago={pago} />
                </div>
            )}
        </VentanaPago>
    );
}

import { router } from '@inertiajs/react';
import { CircleCheck, FlaskConical, LoaderCircle, QrCode, RefreshCw, TriangleAlert } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import {
    formatoRestante,
    ImagenQrSireb,
    useCuentaRegresiva,
    useEsperaDePago,
    type RespuestaQr,
} from '@/components/comunes/qr-sireb';
import { PasosNumerados, ResumenCobro, VentanaPago, type DetallePago } from '@/components/panel/pagos/ventana-pago';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { bs, cn } from '@/lib/utils';

/**
 * El pago por QR, en una ventana al centro. El QR lo genera SIREB —real o de prueba— y se
 * muestra tal cual; con la ventana abierta se pregunta solo si ya se pagó. Ver docs/modulos/PAGO-QR.md.
 */
export function ModalPagoQr({
    abierto,
    monto,
    codigoPago,
    rutaConsultar,
    rutaEstado,
    rutaSimular,
    pago,
    puedeVerificar,
    verificando,
    onVerificar,
    onCerrar,
}: {
    abierto: boolean;
    monto: number;
    codigoPago: string | null;
    /** Al abrirse pide el QR: SIREB lo da solo con el cobro pendiente y sin pago. */
    rutaConsultar?: string;
    /** `route('carnets.estado-qr', id)`: ¿ya se pagó? */
    rutaEstado?: string;
    /** `route('carnets.simular-pago-qr', id)`: solo se ofrece con un QR de prueba. */
    rutaSimular?: string;
    pago?: DetallePago;
    puedeVerificar: boolean;
    verificando: boolean;
    onVerificar: () => void;
    onCerrar: () => void;
}) {
    const [consulta, setConsulta] = useState<RespuestaQr | null>(null);
    const [intento, setIntento] = useState(0);
    const [pagado, setPagado] = useState<string | null>(null);
    const [simulando, setSimulando] = useState(false);

    useEffect(() => {
        if (!abierto || !rutaConsultar) return;
        setConsulta(null);
        setPagado(null);
        const peticion = new AbortController();
        fetch(rutaConsultar, { headers: { Accept: 'application/json' }, signal: peticion.signal })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((r: RespuestaQr) => {
                setConsulta(r);
                // Pudo traer un pago o el vencimiento: la ficha de atrás se pone al día.
                if (!r.puede && r.disponible) router.reload();
            })
            .catch(() => {
                if (!peticion.signal.aborted)
                    setConsulta({
                        puede: false,
                        disponible: true,
                        mensaje: 'No se pudo consultar el cobro en Recaudaciones. Intente de nuevo.',
                    });
            });

        return () => peticion.abort();
    }, [abierto, rutaConsultar, intento]);

    const qr = consulta?.puede ? consulta.qr : undefined;
    const restantes = useCuentaRegresiva(qr);
    const vencido = restantes === 0;

    const alPagar = useCallback((mensaje: string | null) => {
        setPagado(mensaje ?? 'Pago confirmado en Recaudaciones: el trámite quedó aprobado.');
        router.reload();
    }, []);
    useEsperaDePago(rutaEstado, abierto && Boolean(qr) && !vencido && pagado === null, alPagar);

    function simular() {
        if (!rutaSimular) return;
        router.post(rutaSimular, {}, {
            preserveScroll: true,
            onStart: () => setSimulando(true),
            onFinish: () => setSimulando(false),
            onSuccess: onCerrar,
        });
    }

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
                    {qr?.simulado && rutaSimular && !vencido && pagado === null && (
                        <Button key="simular-qr" type="button" variant="outline" disabled={simulando} onClick={simular}>
                            <FlaskConical className="size-4" />
                            {simulando ? 'Simulando…' : 'Simular pago (prueba)'}
                        </Button>
                    )}
                    {puedeVerificar && qr && pagado === null && (
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
                    Pidiendo el QR a Recaudaciones…
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

            {pagado !== null && (
                <div className="p-5">
                    <p className="flex items-start gap-2 rounded-lg bg-emerald-50 px-3 py-3 text-sm text-emerald-900 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-100 dark:ring-emerald-500/30">
                        <CircleCheck className="mt-0.5 size-5 shrink-0" />
                        {pagado}
                    </p>
                </div>
            )}

            {qr && pagado === null && (
                <div className="grid gap-6 p-5 text-sm md:grid-cols-[1fr_18rem]">
                    <div className="flex flex-col items-center gap-4">
                        <div className="relative rounded-xl border bg-white p-3 shadow-sm">
                            <ImagenQrSireb qr={qr} vencido={vencido} className="size-56 sm:size-72" />
                            {vencido && (
                                <div className="absolute inset-0 flex flex-col items-center justify-center gap-3">
                                    <p className="font-medium text-slate-900">El QR venció</p>
                                    <Button key="otro-qr" type="button" size="sm" onClick={() => setIntento((n) => n + 1)}>
                                        <RefreshCw className="size-4" />
                                        Generar otro
                                    </Button>
                                </div>
                            )}
                        </div>
                        {qr.simulado ? (
                            <Badge color="amber">QR de prueba: no se paga con el banco</Badge>
                        ) : (
                            restantes !== null &&
                            !vencido && <Badge color="sky">Vence en {formatoRestante(restantes)}</Badge>
                        )}
                        {qr.simulado && restantes !== null && !vencido && (
                            <span className="text-xs text-muted-foreground">Vence en {formatoRestante(restantes)}</span>
                        )}
                        <div className="w-full max-w-sm rounded-lg border p-4">
                            <p className="mb-3 font-medium">Cómo paga el titular</p>
                            <PasosNumerados
                                pasos={[
                                    'Abre la app de su banco y elige «Pagar con QR».',
                                    'Escanea este código con la cámara del teléfono.',
                                    <>
                                        Confirma el pago de <strong className="tabular-nums">{bs(qr.monto)}</strong>.
                                    </>,
                                    'Esta ventana se entera sola del pago y el trámite queda aprobado.',
                                ]}
                            />
                        </div>
                    </div>

                    <ResumenCobro monto={qr.monto || monto} codigoPago={codigoPago} pago={pago} />
                </div>
            )}
        </VentanaPago>
    );
}

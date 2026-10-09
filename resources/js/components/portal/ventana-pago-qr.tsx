import { Download, LoaderCircle, QrCode, TriangleAlert, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { QrSimulado } from '@/components/comunes/qr-simulado';
import { Dato } from '@/components/portal/piezas';
import { bs } from '@/lib/utils';

interface Respuesta {
    puede: boolean;
    mensaje: string | null;
    monto: number | null;
    codigo_pago: string | null;
    titular?: string;
    documento?: string;
}

/**
 * Pagar por QR desde el portal. Al abrirse pregunta al servidor, que consulta SIREB:
 * el QR sale solo con el cobro pendiente y sin pago cargado. Es de MUESTRA: todavía no cobra.
 */
export function VentanaPagoQr({
    codigo,
    titulo,
    detalle,
    onCerrar,
}: {
    codigo: string;
    titulo: string;
    /** El renglón del trámite (kilos, ruta, gestión), el mismo de la fila. */
    detalle: string;
    onCerrar: () => void;
}) {
    const [respuesta, setRespuesta] = useState<Respuesta | null>(null);
    const [fallo, setFallo] = useState(false);
    const lienzoQr = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const peticion = new AbortController();
        fetch(route('portal.pagar-qr', { codigo }), { headers: { Accept: 'application/json' }, signal: peticion.signal })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then(setRespuesta)
            .catch(() => !peticion.signal.aborted && setFallo(true));

        return () => peticion.abort();
    }, [codigo]);

    useEffect(() => {
        const alTeclear = (e: KeyboardEvent) => e.key === 'Escape' && onCerrar();
        document.addEventListener('keydown', alTeclear);
        const desborde = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', alTeclear);
            document.body.style.overflow = desborde;
        };
    }, [onCerrar]);

    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/70 backdrop-blur-sm sm:items-center sm:p-4"
            onClick={onCerrar}
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-label={`Pagar por QR: ${titulo}`}
                onClick={(e) => e.stopPropagation()}
                className="flex max-h-[94vh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:max-w-md sm:rounded-3xl"
            >
                <div className="flex items-center gap-3 border-b border-slate-200 px-4 py-3">
                    <QrCode className="size-5 shrink-0 text-rio" />
                    <div className="min-w-0 flex-1 leading-tight">
                        <p className="text-sm font-bold text-rio-profundo">Pagar por QR</p>
                        <p className="truncate text-xs text-slate-500">{titulo}</p>
                    </div>
                    <button type="button" onClick={onCerrar} aria-label="Cerrar" className="rounded-full p-2 text-slate-500 hover:bg-slate-100">
                        <X className="size-5" />
                    </button>
                </div>

                <div className="overflow-y-auto p-5">
                    {!respuesta && !fallo && (
                        <p className="flex items-center justify-center gap-2 py-16 text-sm text-slate-500">
                            <LoaderCircle className="size-5 animate-spin" />
                            Consultando su cobro en Recaudaciones…
                        </p>
                    )}

                    {(fallo || (respuesta && !respuesta.puede)) && (
                        <p className="flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
                            <TriangleAlert className="mt-0.5 size-5 shrink-0" />
                            {respuesta?.mensaje ?? 'No se pudo consultar su cobro. Intente de nuevo.'}
                        </p>
                    )}

                    {respuesta?.puede && (
                        <div className="flex flex-col items-center gap-4 text-center">
                            <div ref={lienzoQr} className="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                                <QrSimulado texto={respuesta.codigo_pago ?? codigo} className="size-60" />
                            </div>
                            <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900">
                                QR de muestra: todavía no cobra
                            </span>
                            <div>
                                <p className="text-sm text-slate-500">Monto a pagar</p>
                                <p className="text-3xl font-bold text-rio-profundo tabular-nums">{bs(respuesta.monto)}</p>
                            </div>
                            {/* Qué se está pagando: lo mismo que va impreso debajo del QR descargado. */}
                            <dl className="grid w-full grid-cols-2 gap-x-4 gap-y-2.5 rounded-2xl bg-slate-50 p-4 text-left text-sm ring-1 ring-slate-200">
                                <Dato rotulo="Concepto" ancho>
                                    {titulo}
                                </Dato>
                                {detalle && (
                                    <Dato rotulo="Detalle" ancho>
                                        {detalle}
                                    </Dato>
                                )}
                                <Dato rotulo="Titular" ancho>
                                    {respuesta.titular}
                                </Dato>
                                <Dato rotulo="C.I.">{respuesta.documento}</Dato>
                                <Dato rotulo="Código de pago">
                                    <span className="font-mono tracking-wider">{respuesta.codigo_pago}</span>
                                </Dato>
                            </dl>
                            <button
                                type="button"
                                onClick={() => {
                                    const svg = lienzoQr.current?.querySelector('svg');
                                    if (svg)
                                        void descargarQr(svg, respuesta.monto, respuesta.codigo_pago ?? codigo, [
                                            titulo,
                                            `${respuesta.titular ?? ''} · C.I. ${respuesta.documento ?? ''}`,
                                        ]);
                                }}
                                className="flex items-center gap-1.5 rounded-full border border-rio/30 px-4 py-2 text-sm font-bold text-rio transition-colors hover:bg-rio-espuma"
                            >
                                <Download className="size-4" />
                                Descargar QR
                            </button>
                            <p className="text-xs leading-relaxed text-slate-500">
                                Escanee el código con la aplicación de su banco. Cuando Recaudaciones valide el pago, el trámite queda aprobado solo.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

/** El QR en PNG, con el monto y el código debajo: para pagarlo después desde la aplicación del banco. */
async function descargarQr(svg: SVGSVGElement, monto: number | null, codigoPago: string, renglones: string[]) {
    const imagen = new Image();
    const url = URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(svg)], { type: 'image/svg+xml' }));
    try {
        imagen.src = url;
        await imagen.decode();

        const lado = 600;
        const lienzo = document.createElement('canvas');
        lienzo.width = lado;
        lienzo.height = lado + 200;
        const ctx = lienzo.getContext('2d');
        if (!ctx) return;

        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, lienzo.width, lienzo.height);
        // Sin suavizado: los módulos del QR tienen que salir con bordes netos.
        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(imagen, 0, 0, lado, lado);

        ctx.textAlign = 'center';
        ctx.fillStyle = '#0f172a';
        ctx.font = 'bold 40px sans-serif';
        ctx.fillText(bs(monto), lado / 2, lado + 50);
        ctx.font = '26px monospace';
        ctx.fillText(`Código de pago ${codigoPago}`, lado / 2, lado + 90);
        ctx.fillStyle = '#475569';
        ctx.font = '20px sans-serif';
        // Qué se paga y de quién; un nombre largo se angosta en vez de cortarse.
        renglones.forEach((r, i) => ctx.fillText(r, lado / 2, lado + 125 + i * 28, lado - 40));
        ctx.fillStyle = '#b45309';
        ctx.font = '18px sans-serif';
        ctx.fillText('QR de muestra: todavía no cobra', lado / 2, lado + 185);

        // Blob y no data URL: en el celular una data URL larga a veces abre en vez de bajar.
        const png = await new Promise<Blob | null>((listo) => lienzo.toBlob(listo, 'image/png'));
        if (!png) return;
        const destino = URL.createObjectURL(png);
        const enlace = document.createElement('a');
        enlace.href = destino;
        enlace.download = `qr-pago-${codigoPago}.png`;
        // Firefox solo descarga con el enlace dentro del documento.
        document.body.appendChild(enlace);
        enlace.click();
        enlace.remove();
        setTimeout(() => URL.revokeObjectURL(destino), 1000);
    } finally {
        URL.revokeObjectURL(url);
    }
}

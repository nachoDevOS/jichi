import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

/** El QR tal como lo manda SIREB: el mismo contrato si es real o de prueba. Ver docs/modulos/PAGO-QR.md. */
export interface QrSireb {
    /** PNG en base64, sin el prefijo `data:`. Se muestra tal cual: Jichi no lo dibuja. */
    imagen: string;
    transaccion_id: string | null;
    monto: number;
    /** Instante ISO con zona. Después hay que pedir otro. */
    vence_en: string | null;
    /** Lo que le queda, contado por el SERVIDOR: la hora del teléfono puede estar mal. */
    segundos_restantes: number | null;
    /** QR de prueba de test: no se paga con una app bancaria. */
    simulado: boolean;
}

/** Lo que contesta el servidor al abrir la ventana (`CobroQrService::qr()`). */
export interface RespuestaQr {
    puede: boolean;
    /** false: ese Recaudaciones todavía no cobra por QR; se paga por depósito. */
    disponible: boolean;
    mensaje: string | null;
    qr?: QrSireb;
}

export function imagenQr(qr: QrSireb): string {
    return `data:image/png;base64,${qr.imagen}`;
}

export function ImagenQrSireb({ qr, vencido, className }: { qr: QrSireb; vencido: boolean; className?: string }) {
    return (
        // Sin `pixelated`: achicar un PNG grande con vecino más cercano puede comerse módulos del QR.
        <img
            src={imagenQr(qr)}
            alt={qr.simulado ? 'Código QR de prueba' : 'Código QR de pago'}
            className={cn('bg-white object-contain', vencido && 'opacity-20 grayscale', className)}
        />
    );
}

/**
 * Segundos que le quedan al QR: los que dijo el servidor menos lo que pasó desde que llegaron.
 * No usa la hora del teléfono, que puede estar corrida. null si SIREB no mandó vencimiento.
 */
export function useCuentaRegresiva(qr: QrSireb | undefined): number | null {
    const [restantes, setRestantes] = useState<number | null>(null);
    const segundos = qr?.segundos_restantes ?? null;
    const transaccion = qr?.transaccion_id;

    useEffect(() => {
        if (segundos === null) {
            setRestantes(null);
            return;
        }
        const inicio = Date.now();
        const calcular = () => setRestantes(Math.max(0, segundos - Math.floor((Date.now() - inicio) / 1000)));
        calcular();
        const reloj = setInterval(calcular, 1000);

        return () => clearInterval(reloj);
    }, [segundos, transaccion]);

    return restantes;
}

export function formatoRestante(segundos: number): string {
    const m = Math.floor(segundos / 60);
    const s = segundos % 60;

    return `${m}:${String(s).padStart(2, '0')}`;
}

/**
 * Con el QR a la vista, pregunta cada 10 s si ya se pagó. El servidor consulta a SIREB como
 * mucho cada 15 s por trámite y, si está pagado, lo aprueba. Con la pestaña oculta no pregunta.
 */
export function useEsperaDePago(ruta: string | undefined, activo: boolean, alPagar: (mensaje: string | null) => void) {
    const avisar = useRef(alPagar);
    avisar.current = alPagar;

    useEffect(() => {
        if (!ruta || !activo) return;
        let terminado = false;

        const preguntar = () => {
            if (document.hidden || terminado) return;
            fetch(ruta, { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : null))
                .then((r: { pagado: boolean; mensaje: string | null } | null) => {
                    if (r?.pagado && !terminado) {
                        terminado = true;
                        avisar.current(r.mensaje);
                    }
                })
                .catch(() => undefined);
        };

        const reloj = setInterval(preguntar, 10_000);

        return () => {
            terminado = true;
            clearInterval(reloj);
        };
    }, [ruta, activo]);
}

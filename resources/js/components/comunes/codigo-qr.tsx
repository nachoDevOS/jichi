import QRCode from 'qrcode';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 *  EL CÓDIGO QR QUE SE IMPRIME EN CADA DOCUMENTO
 */
export function CodigoQr({
    url,
    codigo,
    tamano = 128,
    className,
}: {
    /** La dirección completa de verificación, ya armada por el servidor. */
    url: string;
    /** El código del carnet. Solo se usa para el texto alternativo. */
    codigo: string;
    /** Lado del QR en píxeles. */
    tamano?: number;
    className?: string;
}) {
    const [imagen, setImagen] = useState<string | null>(null);

    useEffect(() => {
        let vigente = true;

        QRCode.toDataURL(url, {
            errorCorrectionLevel: 'H',
            margin: 1,
            width: tamano * 2, // el doble, para que no se vea borroso en pantallas retina
            color: { dark: '#14300f', light: '#ffffff' },
        })
            .then((datos) => {
                // Si el componente se desmontó mientras se generaba, no se
                // toca el estado: React avisaría de una fuga de memoria.
                if (vigente) setImagen(datos);
            })
            .catch(() => {
                if (vigente) setImagen(null);
            });

        return () => {
            vigente = false;
        };
    }, [url, tamano]);

    if (!imagen) {
        // Hueco del mismo tamaño mientras se genera, para que el documento no
        // pegue un salto cuando el QR aparece.
        return (
            <span
                className={cn('inline-block shrink-0 rounded bg-black/5', className)}
                style={{ width: tamano, height: tamano }}
                aria-hidden
            />
        );
    }

    return (
        <img
            src={imagen}
            alt={`Código QR de verificación del carnet ${codigo}`}
            width={tamano}
            height={tamano}
            className={cn('shrink-0 rounded bg-white', className)}
        />
    );
}

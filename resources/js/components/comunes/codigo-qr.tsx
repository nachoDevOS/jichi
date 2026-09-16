import QRCode from 'qrcode';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * ============================================================================
 *  EL CÓDIGO QR QUE SE IMPRIME EN CADA DOCUMENTO
 * ============================================================================
 *
 * Codifica la URL pública de verificación, que lleva la firma de validación:
 * /verificar/{firma}. Es lo ÚNICO del carnet que la contiene —en el plástico se
 * imprime el número de registro, que no abre nada—. El inspector lo escanea con
 * cualquier lector del teléfono y cae directo en la pantalla que le dice si el
 * carnet es auténtico y si está vigente.
 *
 * ¿POR QUÉ EL QR LLEVA UNA URL Y NO SOLO EL CÓDIGO?
 *
 * Porque un QR con el texto «4K7R-J2MX-P9TQ» adentro no hace nada: el lector
 * muestra esa cadena y el inspector tendría que abrir el navegador, recordar la
 * dirección del sistema y tipearla. Con la URL completa, escanear y verificar es
 * un solo gesto.
 *
 * ¿Y POR QUÉ LA URL LA ARMA EL SERVIDOR Y SE RECIBE HECHA?
 *
 * Porque lleva la firma de validación, y porque el dominio público no es el de
 * la red departamental: el ciudadano escanea desde su teléfono, fuera de la
 * institución. Armarla acá con route() daría algo como http://jichi.test/... que
 * no abre nada. Sale de config('jichi.url_verificacion'); ver
 * CarnetController::urlVerificacion().
 *
 * ¿POR QUÉ SE GENERA EN EL NAVEGADOR Y NO EN PHP?
 *
 * Este componente es para las pantallas del panel, donde el QR se dibuja
 * mientras el operador carga los datos. Generarlo en el servidor obligaría a
 * una petición por cada tecla.
 *
 * El PDF que se imprime es otra historia: ahí el QR lo va a generar PHP con
 * **simple-qrcode**, que ya está instalado, porque DomPDF no ejecuta
 * JavaScript. Los dos codifican exactamente la misma URL.
 *
 * CORRECCIÓN DE ERRORES EN NIVEL ALTO ('H'). Permite reconstruir el código con
 * hasta un 30% de la superficie dañada. No es un lujo: estos documentos viven
 * doblados en el bolsillo de un pescador, se mojan y se despintan al sol.
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

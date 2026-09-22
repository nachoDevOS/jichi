import type { PropsWithChildren } from 'react';
import { Cabecera } from '@/components/publico/institucional/cabecera';
import { Pie } from '@/components/publico/institucional/pie';
import type { InstitucionPortada } from '@/types/publico';

/**
 *  EL MARCO DEL SITIO INSTITUCIONAL
 *
 *  No reemplaza a layout-publico: ese es el acta angosta e imprimible de la
 *  verificación por QR, y este es el sitio ancho. Mezclarlos rompería el papel.
 *
 *  Pinta sus propias superficies en claro a propósito. El sitio público no
 *  sigue el modo oscuro del panel: es la imagen institucional y tiene que
 *  verse igual en cualquier teléfono.
 */
export default function LayoutInstitucional({
    portada,
    children,
}: PropsWithChildren<{ portada: InstitucionPortada }>) {
    return (
        <div className="flex min-h-screen flex-col overflow-x-hidden bg-white text-slate-700">
            <Cabecera portada={portada} />

            <main className="flex-1">{children}</main>

            <Pie portada={portada} />
        </div>
    );
}

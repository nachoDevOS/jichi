import { useState, type PropsWithChildren, type ReactNode } from 'react';
import { Toaster } from 'sonner';
import { BarraLateral } from '@/components/panel/layout/barra-lateral';
import { BarraSuperior } from '@/components/panel/layout/barra-superior';
import { useFlash } from '@/hooks/use-flash';

/**
 * ============================================================================
 *  EL MARCO DE TODAS LAS PANTALLAS DEL PANEL
 * ============================================================================
 *
 * Un "layout" es el envoltorio que se repite en cada página: la barra lateral,
 * el encabezado y el aviso de mensajes. Cada pantalla se escribe pensando solo
 * en su contenido y se mete acá adentro:
 *
 *   export default function Solicitantes() {
 *       return (
 *           <LayoutPanel titulo="Solicitantes">
 *               ...aquí va SOLO el contenido de la pantalla...
 *           </LayoutPanel>
 *       );
 *   }
 *
 * `children` es justamente ese contenido. React lo pasa automáticamente:
 * es todo lo que está escrito entre <LayoutPanel> y </LayoutPanel>.
 *
 * Este archivo quedó a propósito muy corto. Las tres partes que lo componen
 * viven en components/panel/layout/ y se pueden leer una por una:
 *
 *   barra-lateral.tsx   el menú azul de la izquierda
 *   barra-superior.tsx  el encabezado con el título
 *   navegacion.ts       la lista de módulos del menú
 */
export default function LayoutPanel({
    children,
    titulo,
    descripcion,
    acciones,
}: PropsWithChildren<{
    /** Título grande del encabezado. También conviene repetirlo en <Head>. */
    titulo: string;
    /** Línea de apoyo debajo del título. */
    descripcion?: string;
    /** Botones de la esquina superior derecha: "Nuevo", "Exportar"... */
    acciones?: ReactNode;
}>) {
    /*
     * Convierte los mensajes flash de Laravel (->with('exito', '...')) en
     * avisos flotantes. Se llama una sola vez acá y sirve para todo el panel.
     */
    useFlash();

    /*
     * useState guarda un dato que, al cambiar, hace que React vuelva a pintar.
     * Acá guarda si el menú lateral está abierto, que solo importa en celular.
     *
     *   abierto      -> el valor actual (empieza en false)
     *   setAbierto   -> la función para cambiarlo
     *
     * Vive en el layout, y no dentro de la barra lateral, porque DOS
     * componentes distintos lo necesitan: la barra (para deslizarse) y el
     * botón del encabezado (para abrirla). Cuando dos componentes comparten
     * un dato, este sube al padre común. En React eso se llama "levantar el
     * estado".
     */
    const [menuAbierto, setMenuAbierto] = useState(false);

    return (
        <div className="min-h-screen bg-background">
            {/* Contenedor de los avisos flotantes (toasts) que dispara useFlash. */}
            <Toaster position="top-right" richColors closeButton />

            <BarraLateral abierto={menuAbierto} onCerrar={() => setMenuAbierto(false)} />

            {/*
                Fondo oscuro detrás del menú abierto en celular. Tocarlo lo
                cierra. `lg:hidden` lo hace desaparecer en pantallas grandes,
                donde el menú está fijo y no estorba.
            */}
            {menuAbierto && (
                <div
                    className="fixed inset-0 z-30 bg-black/40 lg:hidden"
                    onClick={() => setMenuAbierto(false)}
                    aria-hidden
                />
            )}

            {/* lg:pl-64 deja libre el ancho exacto de la barra lateral. */}
            <div className="lg:pl-64">
                <BarraSuperior
                    titulo={titulo}
                    descripcion={descripcion}
                    acciones={acciones}
                    onAbrirMenu={() => setMenuAbierto(true)}
                />

                <main className="p-4 sm:p-6">{children}</main>
            </div>
        </div>
    );
}

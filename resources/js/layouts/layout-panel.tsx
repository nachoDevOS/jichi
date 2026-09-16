import { useState, type PropsWithChildren, type ReactNode } from 'react';
import { Toaster } from 'sonner';
import { BarraLateral } from '@/components/panel/layout/barra-lateral';
import { BarraSuperior } from '@/components/panel/layout/barra-superior';
import { useFlash } from '@/hooks/use-flash';
import { cn } from '@/lib/utils';

/** Dónde se recuerda si el menú quedó angosto. */
const CLAVE_ANGOSTO = 'panel-menu-angosto';

/**
 * Lee la preferencia guardada del menú.
 *
 * Va envuelto en try/catch porque `localStorage` LANZA —no devuelve null— en
 * una ventana de incógnito o con las cookies bloqueadas por política del
 * equipo, que es un escenario real en una oficina pública. Sin el catch, el
 * panel entero queda en blanco por recordar el ancho de una barra.
 */
function leerAngosto(): boolean {
    try {
        return localStorage.getItem(CLAVE_ANGOSTO) === '1';
    } catch {
        return false;
    }
}

/**
 * ============================================================================
 *  EL MARCO DE TODAS LAS PANTALLAS DEL PANEL
 * ============================================================================
 *
 * Un "layout" es el envoltorio que se repite en cada página: la barra lateral,
 * el encabezado y el aviso de mensajes. Cada pantalla se escribe pensando solo
 * en su contenido y se mete acá adentro:
 *
 *   export default function Beneficiarios() {
 *       return (
 *           <LayoutPanel titulo="Beneficiarios">
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
 *   barra-superior.tsx  el encabezado: sesión arriba, título y migas abajo
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
     *
     *   menuAbierto  -> en CELULAR: si la barra está desplegada encima
     *   menuAngosto  -> en ESCRITORIO: si la barra quedó reducida a iconos
     *
     * Son dos y no uno porque responden a cosas distintas: el primero se apaga
     * solo al navegar, el segundo es una preferencia que tiene que sobrevivir a
     * la recarga.
     *
     * Viven en el layout, y no dentro de la barra lateral, porque DOS
     * componentes distintos los necesitan: la barra (para dibujarse) y el
     * encabezado (para los botones que los cambian). Cuando dos componentes
     * comparten un dato, este sube al padre común. En React eso se llama
     * "levantar el estado".
     */
    const [menuAbierto, setMenuAbierto] = useState(false);

    // La función va como argumento —y no `useState(leerAngosto())`— para que
    // localStorage se lea UNA vez, al montar, y no en cada repintado.
    const [menuAngosto, setMenuAngosto] = useState(leerAngosto);

    const alternarAngosto = () => {
        const valor = !menuAngosto;

        setMenuAngosto(valor);

        // El guardado nunca debe voltear la pantalla: si el navegador no deja
        // escribir, la barra igual se angosta, solo que no se acuerda.
        try {
            localStorage.setItem(CLAVE_ANGOSTO, valor ? '1' : '0');
        } catch {
            /* preferencia no persistida: no es un error para el usuario */
        }
    };

    return (
        <div className="min-h-screen bg-panel-fondo">
            {/* Contenedor de los avisos flotantes (toasts) que dispara useFlash. */}
            <Toaster position="top-right" richColors closeButton />

            <BarraLateral
                abierto={menuAbierto}
                angosto={menuAngosto}
                onCerrar={() => setMenuAbierto(false)}
                onAlternarAngosto={alternarAngosto}
            />

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

            {/*
                El relleno de la izquierda deja libre el ancho EXACTO de la barra
                lateral, y por eso los dos números tienen que moverse juntos: si
                acá dice 64 y la barra mide 16, el contenido le queda por debajo.
                La transición dura lo mismo que la de la barra para que se
                muevan como una sola pieza.
            */}
            <div
                className={cn(
                    'transition-[padding] duration-200',
                    menuAngosto ? 'lg:pl-16' : 'lg:pl-64',
                )}
            >
                <BarraSuperior
                    titulo={titulo}
                    descripcion={descripcion}
                    acciones={acciones}
                    onAbrirMenu={() => setMenuAbierto(true)}
                    onAlternarAngosto={alternarAngosto}
                />

                <main className="p-4 sm:p-6">{children}</main>
            </div>
        </div>
    );
}

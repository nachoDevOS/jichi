import { BookOpen } from 'lucide-react';
import { useCallback, useState } from 'react';
import { createPortal } from 'react-dom';
import { VentanaManual } from '@/components/panel/manual/ventana-manual';

/**
 * El botón «Manual» de la barra superior: abre los videos tutoriales sin salir
 * de la pantalla en la que se está trabajando.
 */
export function BotonManual() {
    const [abierto, setAbierto] = useState(false);
    // Estable: la ventana lo usa en la dependencia de su efecto.
    const cerrar = useCallback(() => setAbierto(false), []);

    return (
        <>
            <button
                type="button"
                onClick={() => setAbierto(true)}
                title="Manual de usuario"
                className="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-sm font-medium text-muted-foreground hover:bg-secondary hover:text-foreground"
            >
                <BookOpen className="size-5" />
                <span className="hidden md:inline">Manual</span>
            </button>

            {/* Al body: dentro del encabezado (sticky, z-20) quedaría debajo de la barra lateral. */}
            {abierto && createPortal(<VentanaManual onCerrar={cerrar} />, document.body)}
        </>
    );
}

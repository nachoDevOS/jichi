import { Menu } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Encabezado de la página: botón de menú (solo en celular), título, descripción
 * y los botones de acción de la pantalla ("Nuevo solicitante", "Exportar"...).
 *
 * `sticky top-0` lo deja pegado arriba al desplazar la página, para que el
 * título y los botones sigan a mano en listados largos.
 */
export function BarraSuperior({
    titulo,
    descripcion,
    acciones,
    onAbrirMenu,
}: {
    titulo: string;
    descripcion?: string;
    /** Botones del lado derecho. Los define cada página. */
    acciones?: ReactNode;
    onAbrirMenu: () => void;
}) {
    return (
        <header className="sticky top-0 z-20 border-b border-border bg-background/85 backdrop-blur">
            <div className="flex items-center gap-4 px-4 py-4 sm:px-6">
                <button
                    type="button"
                    className="lg:hidden"
                    onClick={onAbrirMenu}
                    aria-label="Abrir menú"
                >
                    <Menu className="size-5" />
                </button>

                <div className="min-w-0 flex-1">
                    {/* truncate corta con "..." los títulos largos en vez de
                        empujar los botones fuera de la pantalla. */}
                    <h1 className="truncate text-xl font-semibold tracking-tight">{titulo}</h1>

                    {descripcion && (
                        <p className="truncate text-sm text-muted-foreground">{descripcion}</p>
                    )}
                </div>

                {acciones && <div className="flex items-center gap-2">{acciones}</div>}
            </div>
        </header>
    );
}

import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Lo que se muestra cuando una lista no tiene nada que mostrar.
 */
export function EstadoVacio({
    icono: Icono,
    titulo,
    descripcion,
    accion,
}: {
    icono: LucideIcon;
    titulo: string;
    descripcion?: string;
    /** Botón o enlace opcional: "Registrar el primero", "Limpiar filtros"... */
    accion?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Icono className="size-6" />
            </div>

            <div className="space-y-1">
                <p className="font-medium">{titulo}</p>
                {descripcion && (
                    <p className="max-w-sm text-sm text-muted-foreground">{descripcion}</p>
                )}
            </div>

            {accion}
        </div>
    );
}

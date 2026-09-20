import { User } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * La foto de una persona en una fila de tabla, o la silueta cuando no tiene.
 */
export function Retrato({
    url,
    nombre,
    className,
}: {
    url: string | null;
    /** Va en el `alt`: es lo que lee un lector de pantalla y lo que se muestra
     *  si la imagen no carga. */
    nombre: string;
    /** Para cambiar el tamaño donde haga falta. Por defecto, `size-10`. */
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted',
                className,
            )}
        >
            {url ? (
                <img src={url} alt={nombre} className="size-full object-cover" />
            ) : (
                <User className="size-5 text-muted-foreground" aria-hidden />
            )}
        </div>
    );
}

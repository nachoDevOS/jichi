import { User } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * La foto de una persona en una fila de tabla, o la silueta cuando no tiene.
 *
 * ----------------------------------------------------------------------------
 *  EL HUECO SE DIBUJA IGUAL, Y DEL MISMO TAMAÑO
 * ----------------------------------------------------------------------------
 *
 * Sin él, las filas con y sin fotografía tendrían alturas distintas y la tabla
 * quedaría dentada; peor todavía, la columna del nombre se correría de lugar
 * entre una fila y otra, que es lo que más molesta al recorrer el padrón con la
 * vista.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ESTÁ EN `comunes/` Y NO DENTRO DE UNA PANTALLA
 * ----------------------------------------------------------------------------
 *
 * Porque lo usan dos listados que no se conocen entre sí —el padrón de
 * beneficiarios y el de trámites— y los dos tienen que verse igual. Copiado en
 * cada uno, alcanza con ajustar el tamaño en uno para que las dos tablas dejen
 * de coincidir.
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

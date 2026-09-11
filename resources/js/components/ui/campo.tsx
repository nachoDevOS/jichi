import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * Envoltorio de un campo de formulario: etiqueta + control + error + ayuda.
 *
 * ¿PARA QUÉ SIRVE?
 *
 * Sin esto, cada campo de cada formulario del sistema hay que escribirlo así:
 *
 *   <div className="space-y-2">
 *     <Label htmlFor="telefono">Teléfono</Label>
 *     <Input id="telefono" value={...} onChange={...} aria-invalid={...} />
 *     {errors.telefono && <p className="text-sm text-destructive">{errors.telefono}</p>}
 *   </div>
 *
 * Son cinco líneas repetidas veinte veces por formulario, y basta olvidarse
 * una para que un error de validación no se muestre y el usuario no entienda
 * por qué no puede guardar. Con <Campo> queda:
 *
 *   <Campo etiqueta="Teléfono" htmlFor="telefono" error={errors.telefono}>
 *     <Input id="telefono" ... />
 *   </Campo>
 *
 * `children` es el hueco donde va el control (Input, Select, Textarea...).
 * Es una idea central de React: un componente puede recibir otros componentes
 * como contenido, igual que una etiqueta HTML envuelve a otras.
 */
export function Campo({
    etiqueta,
    htmlFor,
    error,
    ayuda,
    obligatorio = false,
    className,
    children,
}: {
    etiqueta: string;
    htmlFor?: string;
    /** Mensaje de validación que devolvió Laravel para este campo. */
    error?: string;
    /** Texto chico de apoyo, debajo del control. */
    ayuda?: string;
    obligatorio?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={htmlFor}>
                {etiqueta}
                {obligatorio && (
                    <span className="ml-0.5 text-destructive" aria-hidden>
                        *
                    </span>
                )}
            </Label>

            {children}

            {/* role="alert" hace que los lectores de pantalla anuncien el error
                apenas aparece, sin que el usuario tenga que ir a buscarlo. */}
            {error ? (
                <p className="text-sm text-destructive" role="alert">
                    {error}
                </p>
            ) : (
                ayuda && <p className="text-xs text-muted-foreground">{ayuda}</p>
            )}
        </div>
    );
}

import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * Envoltorio de un campo de formulario: etiqueta + control + error + ayuda.
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

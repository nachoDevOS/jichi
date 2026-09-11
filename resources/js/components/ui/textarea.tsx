import * as React from 'react';
import { cn } from '@/lib/utils';

/**
 * Caja de texto de varias líneas: direcciones, observaciones, motivos de
 * rechazo. Mismos estilos que <Input> para que los formularios se vean parejos.
 */
export const Textarea = React.forwardRef<
    HTMLTextAreaElement,
    React.TextareaHTMLAttributes<HTMLTextAreaElement>
>(({ className, rows = 3, ...props }, ref) => (
    <textarea
        ref={ref}
        rows={rows}
        className={cn(
            'flex w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm transition-colors',
            'placeholder:text-muted-foreground',
            'focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring',
            'disabled:cursor-not-allowed disabled:opacity-50',
            'aria-invalid:border-destructive aria-invalid:outline-destructive',
            className,
        )}
        {...props}
    />
));

Textarea.displayName = 'Textarea';

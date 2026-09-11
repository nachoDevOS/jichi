import * as React from 'react';
import { cn } from '@/lib/utils';

/**
 * Lista desplegable. Es el <select> de HTML de toda la vida, con los estilos
 * del sistema aplicados.
 *
 * No usa librerías externas a propósito: para elegir entre pocas opciones el
 * <select> nativo funciona mejor en celular (abre el selector del sistema
 * operativo) y ya viene accesible con teclado sin escribir nada.
 */
export const Select = React.forwardRef<
    HTMLSelectElement,
    React.SelectHTMLAttributes<HTMLSelectElement>
>(({ className, children, ...props }, ref) => (
    <select
        ref={ref}
        className={cn(
            'flex h-10 w-full appearance-none rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground shadow-sm transition-colors',
            // La flechita se dibuja como imagen de fondo porque appearance-none
            // borra la que pone el navegador.
            "bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke-width=%222%22 stroke=%22%2364748b%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22m19.5 8.25-7.5 7.5-7.5-7.5%22/%3E%3C/svg%3E')] bg-[length:1rem] bg-[right_0.6rem_center] bg-no-repeat pr-9",
            'focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring',
            'disabled:cursor-not-allowed disabled:opacity-50',
            'aria-invalid:border-destructive aria-invalid:outline-destructive',
            className,
        )}
        {...props}
    >
        {children}
    </select>
));

Select.displayName = 'Select';

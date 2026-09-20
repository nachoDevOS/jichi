import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

/**
 *  LA ESCALA DE LOS BOTONES DE TODO EL SISTEMA
 */
const buttonVariants = cva(
    "inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md text-[13px] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50 [&_svg]:size-3.5 [&_svg]:shrink-0",
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm',
                dorado: 'bg-accent text-accent-foreground hover:bg-accent/90 shadow-sm',
                destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
                outline: 'border border-border bg-card hover:bg-secondary hover:text-secondary-foreground',
                secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
                ghost: 'hover:bg-secondary hover:text-secondary-foreground',
                link: 'text-primary underline-offset-4 hover:underline',

                // LAS TRES ACCIONES, iguales en toda pantalla —ficha y tabla—: el
                // color dice qué hace el botón antes de leerlo —celeste mira,
                // ámbar cambia, rojo saca— y así no se inventa uno por pantalla.
                ver: 'border border-sky-300 bg-card text-sky-700 hover:bg-sky-50 hover:text-sky-800 dark:border-sky-500/40 dark:text-sky-300 dark:hover:bg-sky-500/10',
                editar: 'border border-amber-300 bg-card text-amber-700 hover:bg-amber-50 hover:text-amber-800 dark:border-amber-500/40 dark:text-amber-300 dark:hover:bg-amber-500/10',
                // `eliminar` es toda acción que SACA algo de circulación:
                // eliminar, dar de baja, anular, revocar, rechazar.
                eliminar: 'border border-rose-300 bg-card text-rose-700 hover:bg-rose-50 hover:text-rose-800 dark:border-rose-500/40 dark:text-rose-300 dark:hover:bg-rose-500/10',
            },
            size: {
                default: 'h-8 px-3',
                sm: 'h-7 rounded-md px-2.5 text-xs',
                // `lg` no es «grande»: es el tamaño de un botón que va solo y
                // manda, como el de iniciar sesión. Sigue siendo compacto.
                lg: 'h-9 rounded-md px-5',
                icon: 'size-8',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export interface ButtonProps
    extends React.ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant, size, ...props }, ref) => (
        <button ref={ref} className={cn(buttonVariants({ variant, size }), className)} {...props} />
    ),
);

Button.displayName = 'Button';

export { buttonVariants };

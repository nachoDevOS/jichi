import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

/**
 * ============================================================================
 *  LA ESCALA DE LOS BOTONES DE TODO EL SISTEMA
 * ============================================================================
 *
 * Cambiar estas clases cambia TODOS los botones, porque no hay ningún botón con
 * tamaño propio: las pantallas usan `<Button>` o, cuando el botón es un enlace,
 * `buttonVariants()` sobre un `<a>`.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ SON CHICOS
 * ----------------------------------------------------------------------------
 *
 * Esto es un panel de trabajo, no una página de inicio: la ficha de un trámite
 * llega a mostrar seis acciones en la misma barra —corregir, aprobar, rechazar,
 * recibo, imprimir, entregar—. Con botones altos esa fila se parte en dos
 * renglones y empuja el contenido hacia abajo.
 *
 * 32 px de alto (`h-8`) es la medida cómoda para un sistema que se usa con mouse
 * todo el día. NO bajar de ahí: más chico empieza a costar acertarle, y el
 * operador de ventanilla hace esto cientos de veces por jornada.
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

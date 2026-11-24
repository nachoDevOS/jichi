import * as React from 'react';
import { cn } from '@/lib/utils';

export const Card = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
    ({ className, ...props }, ref) => (
        <div
            ref={ref}
            className={cn('rounded-lg border border-border bg-card text-card-foreground shadow-xs', className)}
            {...props}
        />
    ),
);
Card.displayName = 'Card';

export const CardHeader = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
    ({ className, ...props }, ref) => (
        <div ref={ref} className={cn('flex flex-col gap-0.5 p-4 pb-2.5', className)} {...props} />
    ),
);
CardHeader.displayName = 'CardHeader';

export const CardTitle = React.forwardRef<HTMLHeadingElement, React.HTMLAttributes<HTMLHeadingElement>>(
    ({ className, ...props }, ref) => (
        <h3 ref={ref} className={cn('text-sm font-semibold tracking-tight', className)} {...props} />
    ),
);
CardTitle.displayName = 'CardTitle';

export const CardDescription = React.forwardRef<HTMLParagraphElement, React.HTMLAttributes<HTMLParagraphElement>>(
    ({ className, ...props }, ref) => (
        <p ref={ref} className={cn('text-xs text-muted-foreground', className)} {...props} />
    ),
);
CardDescription.displayName = 'CardDescription';

export const CardContent = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
    ({ className, ...props }, ref) => <div ref={ref} className={cn('p-4 pt-0', className)} {...props} />,
);
CardContent.displayName = 'CardContent';

/*
 * NO HAY `CardFooter`. Lo traía el juego original de shadcn y ninguna pantalla
 * del sistema lo usó nunca: las tarjetas de acá terminan en su contenido, y los
 * botones viven en la barra de acciones del layout, no al pie de cada tarjeta.
 * Se quitó al limpiar código sin uso.
 */

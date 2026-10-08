import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { usePermisos } from '@/hooks/use-permisos';
import { cn } from '@/lib/utils';

/**
 *  Enlace a otro módulo que solo es enlace si el rol puede verlo; si no, queda
 *  el texto. Sin esto, la ficha ofrece un clic que termina en 403.
 */
export function EnlacePermitido({
    permiso,
    href,
    className,
    nuevaPestana = false,
    title,
    children,
}: {
    permiso: string;
    href: string;
    className?: string;
    /** `<a target=_blank>` en vez de `Link`: Inertia navega en la misma pestaña. */
    nuevaPestana?: boolean;
    title?: string;
    children: ReactNode;
}) {
    const { puede } = usePermisos();

    if (!puede(permiso)) {
        // Sin el color ni los efectos de enlace: que no parezca clicable.
        const sinEnlace = (className ?? '')
            .split(' ')
            .filter((c) => c !== 'text-primary' && !c.startsWith('hover:') && c !== 'transition-transform' && c !== 'transition-colors')
            .join(' ');

        return <span className={sinEnlace}>{children}</span>;
    }

    if (nuevaPestana) {
        return (
            <a href={href} target="_blank" rel="noreferrer" className={className} title={title}>
                {children}
            </a>
        );
    }

    return (
        <Link href={href} className={cn(className)} title={title}>
            {children}
        </Link>
    );
}

import { cn } from '@/lib/utils';

/**
 * Los colores llegan desde los enums de PHP (EstadoTramite::color(),
 * TipoTramite::color(), EstadoCarnet::color()...), por eso el mapa está escrito
 * con clases COMPLETAS.
 */
const COLORES: Record<string, string> = {
    slate: 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-500/15 dark:text-slate-300 dark:ring-slate-400/30',
    amber: 'bg-amber-100 text-amber-800 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-400/30',
    sky: 'bg-sky-100 text-sky-800 ring-sky-600/20 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-400/30',
    indigo: 'bg-indigo-100 text-indigo-800 ring-indigo-600/20 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-400/30',
    violet: 'bg-violet-100 text-violet-800 ring-violet-600/20 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-400/30',
    emerald: 'bg-emerald-100 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-400/30',
    rose: 'bg-rose-100 text-rose-800 ring-rose-600/20 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-400/30',
};

interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
    color?: keyof typeof COLORES | string;
}

export function Badge({ color = 'slate', className, ...props }: BadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
                COLORES[color] ?? COLORES.slate,
                className,
            )}
            {...props}
        />
    );
}

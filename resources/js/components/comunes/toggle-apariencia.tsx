import { Monitor, Moon, Sun } from 'lucide-react';
import { useApariencia, type Apariencia } from '@/hooks/use-apariencia';
import { cn } from '@/lib/utils';

const OPCIONES: { valor: Apariencia; icono: typeof Sun; titulo: string }[] = [
    { valor: 'light', icono: Sun, titulo: 'Modo claro' },
    { valor: 'dark', icono: Moon, titulo: 'Modo oscuro' },
    { valor: 'system', icono: Monitor, titulo: 'Seguir al sistema' },
];

export function ToggleApariencia({ className }: { className?: string }) {
    const { apariencia, cambiar } = useApariencia();

    return (
        <div
            role="radiogroup"
            aria-label="Apariencia"
            className={cn('inline-flex items-center gap-0.5 rounded-lg bg-black/10 p-0.5 dark:bg-white/10', className)}
        >
            {OPCIONES.map(({ valor, icono: Icono, titulo }) => (
                <button
                    key={valor}
                    type="button"
                    role="radio"
                    aria-checked={apariencia === valor}
                    title={titulo}
                    onClick={() => cambiar(valor)}
                    className={cn(
                        'rounded-md p-1.5 transition-colors',
                        apariencia === valor
                            ? 'bg-white text-primary shadow-sm dark:bg-white/20 dark:text-white'
                            : 'opacity-70 hover:opacity-100',
                    )}
                >
                    <Icono className="size-4" />
                    <span className="sr-only">{titulo}</span>
                </button>
            ))}
        </div>
    );
}

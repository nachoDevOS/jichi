import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';

/** Un texto que se copia con un clic, con el ícono que confirma. */
export function TextoCopiable({ texto, className }: { texto: string; className?: string }) {
    const [copiado, setCopiado] = useState(false);

    async function copiar() {
        try {
            await navigator.clipboard.writeText(texto);
        } catch {
            // navigator.clipboard solo existe en HTTPS o localhost: entrando por la IP de la red no está.
            const area = document.createElement('textarea');
            area.value = texto;
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }

        setCopiado(true);
        toast.success('Copiado');
        setTimeout(() => setCopiado(false), 2000);
    }

    const Icono = copiado ? Check : Copy;

    return (
        <button
            type="button"
            onClick={copiar}
            title="Copiar"
            className={cn(
                'inline-flex items-center gap-1.5 rounded px-1.5 py-0.5 font-mono font-medium transition-colors hover:bg-muted',
                className,
            )}
        >
            <span className="break-all text-left">{texto}</span>
            <Icono className={cn('size-3.5 shrink-0', copiado ? 'text-emerald-600' : 'text-muted-foreground')} />
        </button>
    );
}

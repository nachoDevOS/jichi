import { Check, FileText, ImageIcon, Paperclip, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useArchivos } from '@/hooks/use-archivos';
import { cn } from '@/lib/utils';

/**
 *  EL CAMPO PARA ADJUNTAR UN ARCHIVO
 */
export function SelectorArchivo({
    id,
    archivo,
    onElegir,
    soloImagen = false,
    error,
}: {
    id: string;
    /** El archivo elegido, o null. Lo maneja el formulario, no este componente. */
    archivo: File | null;
    onElegir: (archivo: File | null) => void;
    /** Para la fotografía: solo acepta imagen, no PDF. */
    soloImagen?: boolean;
    /** Error que devolvió el servidor para este campo. */
    error?: string;
}) {
    const { acepta, aceptaImagen, validar } = useArchivos();
    const entrada = useRef<HTMLInputElement>(null);

    // El error propio del componente —tipo o peso— es distinto del que manda el
    // servidor: este aparece al instante y sin haber subido nada.
    const [rechazo, setRechazo] = useState<string | null>(null);
    const [miniatura, setMiniatura] = useState<string | null>(null);

    /*
     * LA MINIATURA HAY QUE LIBERARLA A MANO.
     */
    useEffect(() => {
        if (!archivo || !archivo.type.startsWith('image/')) {
            setMiniatura(null);

            return;
        }

        const url = URL.createObjectURL(archivo);
        setMiniatura(url);

        return () => URL.revokeObjectURL(url);
    }, [archivo]);

    function elegir(elegido: File | null) {
        setRechazo(null);

        if (!elegido) {
            onElegir(null);

            return;
        }

        const motivo = validar(elegido, { soloImagen });

        if (motivo) {
            setRechazo(motivo);
            onElegir(null);
            // Se limpia el input del navegador: si no, vuelve a mostrar el
            // nombre del archivo rechazado y parece que quedó cargado.
            if (entrada.current) entrada.current.value = '';

            return;
        }

        onElegir(elegido);
    }

    function quitar() {
        if (entrada.current) entrada.current.value = '';
        setRechazo(null);
        onElegir(null);
    }

    const Icono = soloImagen ? ImageIcon : FileText;
    const problema = rechazo ?? error;

    return (
        <div className="space-y-1.5">
            {/* El input real queda oculto: se dispara desde el recuadro de
                abajo, que es lo que se puede dar estilo. `sr-only` y no
                `hidden` para que el teclado y los lectores de pantalla lo
                sigan alcanzando. */}
            <input
                ref={entrada}
                id={id}
                type="file"
                accept={soloImagen ? aceptaImagen : acepta}
                className="sr-only"
                onChange={(e) => elegir(e.target.files?.[0] ?? null)}
            />

            {archivo ? (
                <div className="flex items-center gap-3 rounded-md border border-emerald-500/50 bg-emerald-50/60 p-2.5 dark:bg-emerald-950/20">
                    {miniatura ? (
                        <img
                            src={miniatura}
                            alt=""
                            className="size-10 shrink-0 rounded object-cover"
                        />
                    ) : (
                        <span className="flex size-10 shrink-0 items-center justify-center rounded bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                            <Icono className="size-5" />
                        </span>
                    )}

                    <div className="min-w-0 flex-1">
                        <p className="flex items-center gap-1.5 text-[13px] font-medium">
                            <Check className="size-3.5 shrink-0 text-emerald-600" />
                            {/* `truncate` y `min-w-0` en el padre: sin los dos,
                                un nombre largo empuja el botón de quitar fuera
                                del recuadro. */}
                            <span className="truncate">{archivo.name}</span>
                        </p>
                        <p className="text-xs text-muted-foreground">{pesoLegible(archivo.size)}</p>
                    </div>

                    <button
                        type="button"
                        onClick={quitar}
                        className="shrink-0 rounded p-1 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                        title="Quitar el archivo"
                        aria-label={`Quitar ${archivo.name}`}
                    >
                        <X className="size-4" />
                    </button>
                </div>
            ) : (
                <label
                    htmlFor={id}
                    className={cn(
                        'flex cursor-pointer items-center gap-2 rounded-md border border-dashed border-input bg-card p-2.5 text-[13px] text-muted-foreground transition-colors',
                        'hover:border-primary/60 hover:bg-secondary hover:text-foreground',
                        problema && 'border-destructive/60',
                    )}
                >
                    <Paperclip className="size-4 shrink-0" />
                    {soloImagen ? 'Elegir una imagen' : 'Elegir un archivo'}
                </label>
            )}

            {problema && (
                <p className="text-xs text-destructive" role="alert">
                    {problema}
                </p>
            )}
        </div>
    );
}

/**
 * El peso del archivo como lo entiende una persona: «1,2 MB», «340 KB».
 *
 * En KB cuando es chico, porque «0,3 MB» no le dice nada a nadie. Coma decimal,
 * que es como se escribe acá.
 */
function pesoLegible(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
}

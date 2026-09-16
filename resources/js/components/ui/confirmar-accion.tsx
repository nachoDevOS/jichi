import { AlertTriangle } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';

/**
 * Ventana de confirmación para acciones que no se pueden deshacer:
 * dar de baja un beneficiario, anular un carnet, suspender un rubro.
 *
 * POR QUÉ NO SE USA window.confirm()
 *
 * El confirm() del navegador congela toda la pestaña, no se puede estilar, y
 * en varios navegadores móviles ni siquiera aparece. Para una acción que borra
 * datos de un sistema departamental conviene algo que se vea claramente y que diga
 * exactamente qué se va a hacer.
 *
 * CÓMO SE USA
 *
 *   const [confirmar, setConfirmar] = useState(false);
 *
 *   <Button onClick={() => setConfirmar(true)}>Dar de baja</Button>
 *
 *   <ConfirmarAccion
 *       abierto={confirmar}
 *       titulo="¿Dar de baja al beneficiario?"
 *       descripcion="Dejará de aparecer en los listados."
 *       onCancelar={() => setConfirmar(false)}
 *       onConfirmar={() => router.delete(route('beneficiarios.destroy', id))}
 *   />
 */
export function ConfirmarAccion({
    abierto,
    titulo,
    descripcion,
    textoConfirmar = 'Confirmar',
    procesando = false,
    onConfirmar,
    onCancelar,
}: {
    abierto: boolean;
    titulo: string;
    descripcion?: ReactNode;
    textoConfirmar?: string;
    /** Deshabilita los botones mientras la petición está en curso. */
    procesando?: boolean;
    onConfirmar: () => void;
    onCancelar: () => void;
}) {
    /*
     * useEffect ejecuta código "por fuera" del pintado: acá, escuchar la tecla
     * Escape mientras la ventana está abierta.
     *
     * El `return` de adentro es la LIMPIEZA: React lo llama al cerrarse la
     * ventana. Sin eso, cada apertura dejaría un listener más pegado al
     * documento y se irían acumulando.
     */
    useEffect(() => {
        if (!abierto) return;

        const alPresionar = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onCancelar();
        };

        document.addEventListener('keydown', alPresionar);

        return () => document.removeEventListener('keydown', alPresionar);
    }, [abierto, onCancelar]);

    if (!abierto) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-confirmacion"
        >
            <div className="w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-lg">
                <div className="flex items-start gap-4">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                        <AlertTriangle className="size-5" />
                    </div>

                    <div className="min-w-0 space-y-1">
                        <h2 id="titulo-confirmacion" className="font-semibold">
                            {titulo}
                        </h2>
                        {descripcion && (
                            <div className="text-sm text-muted-foreground">{descripcion}</div>
                        )}
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-2">
                    <Button variant="outline" onClick={onCancelar} disabled={procesando}>
                        Cancelar
                    </Button>
                    <Button variant="destructive" onClick={onConfirmar} disabled={procesando}>
                        {textoConfirmar}
                    </Button>
                </div>
            </div>
        </div>
    );
}

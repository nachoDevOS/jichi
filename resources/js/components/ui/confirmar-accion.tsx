import { AlertTriangle } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
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
    confirmacion,
    procesando = false,
    onConfirmar,
    onCancelar,
}: {
    abierto: boolean;
    titulo: string;
    descripcion?: ReactNode;
    textoConfirmar?: string;
    /**
     * Frase que hay que MARCAR antes de poder confirmar. Ver la nota de abajo.
     * Sin esta prop no aparece ninguna casilla y la ventana se comporta como
     * siempre.
     */
    confirmacion?: string;
    /** Deshabilita los botones mientras la petición está en curso. */
    procesando?: boolean;
    onConfirmar: () => void;
    onCancelar: () => void;
}) {
    const [aceptado, setAceptado] = useState(false);

    // Se limpia al cerrar: la segunda vez aparecería ya marcada y no serviría
    // para nada.
    useEffect(() => {
        if (!abierto) setAceptado(false);
    }, [abierto]);
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

                {confirmacion && (
                    <label className="mt-5 flex cursor-pointer items-start gap-2.5 rounded-md border border-border bg-secondary/40 p-3 text-sm">
                        <input
                            type="checkbox"
                            checked={aceptado}
                            onChange={(e) => setAceptado(e.target.checked)}
                            className="mt-0.5 size-4 shrink-0 rounded border-input accent-primary"
                        />
                        <span>{confirmacion}</span>
                    </label>
                )}

                <div className="mt-6 flex justify-end gap-2">
                    <Button variant="outline" onClick={onCancelar} disabled={procesando}>
                        Cancelar
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={onConfirmar}
                        // Con casilla, el botón espera a que esté marcada.
                        disabled={procesando || (Boolean(confirmacion) && !aceptado)}
                    >
                        {textoConfirmar}
                    </Button>
                </div>
            </div>
        </div>
    );
}

/**
 * ============================================================================
 *  LA CASILLA DE CONSENTIMIENTO
 * ============================================================================
 *
 * Una frase que hay que MARCAR antes de poder confirmar. El botón queda apagado
 * hasta entonces.
 *
 * ----------------------------------------------------------------------------
 *  PARA QUÉ SIRVE SI YA HAY QUE APRETAR «CONFIRMAR»
 * ----------------------------------------------------------------------------
 *
 * Porque un botón se aprieta de memoria. Después de la décima vez, «¿está
 * seguro?» ya no se lee: la mano va sola al mismo lugar de la pantalla. La
 * casilla rompe eso porque está en OTRO lado y exige un acto distinto.
 *
 * Y sobre todo, DICE QUÉ SE ESTÁ AFIRMANDO. En validar un depósito no es una
 * traba: es la declaración misma —«comparé la boleta con el extracto»— y esa
 * frase es lo que después respalda la firma de quien revisó.
 *
 * Se usa solo donde hace falta: lo irreversible y lo que es una declaración. En
 * todo lo demás estorba, y una casilla que se marca sin leer no protege nada.
 *
 * Se limpia al cerrar la ventana, o la segunda vez aparecería ya marcada y no
 * serviría para nada.
 */

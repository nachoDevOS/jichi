import { TriangleAlert } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Textarea } from '@/components/ui/textarea';

/**
 *  VENTANA DE CONFIRMACIÓN QUE EXIGE ESCRIBIR UN MOTIVO
 */
export function ConfirmarConMotivo({
    abierto,
    titulo,
    descripcion,
    etiquetaMotivo = 'Motivo',
    ayuda,
    placeholder,
    textoConfirmar = 'Confirmar',
    confirmacion,
    minimo = 10,
    valor,
    onCambiar,
    error,
    procesando = false,
    onConfirmar,
    onCancelar,
}: {
    abierto: boolean;
    titulo: string;
    descripcion?: ReactNode;
    etiquetaMotivo?: string;
    ayuda?: string;
    placeholder?: string;
    textoConfirmar?: string;
    /**
     * Frase que hay que MARCAR antes de poder confirmar. Ver la nota de abajo.
     * Sin esta prop no aparece ninguna casilla.
     */
    confirmacion?: string;
    /** Cuántos caracteres exige el servidor. El botón espera a llegar. */
    minimo?: number;
    valor: string;
    onCambiar: (valor: string) => void;
    /** El error que devolvió el servidor para el campo. */
    error?: string;
    procesando?: boolean;
    onConfirmar: () => void;
    onCancelar: () => void;
}) {
    const campo = useRef<HTMLTextAreaElement>(null);
    const [aceptado, setAceptado] = useState(false);

    // Se limpia al cerrar, por lo mismo que en ConfirmarAccion.
    useEffect(() => {
        if (!abierto) setAceptado(false);
    }, [abierto]);

    /*
     * Escape cierra, y el foco arranca en el textarea.
     *
     * El `return` de adentro es la LIMPIEZA: sin él, cada apertura dejaría un
     * listener más pegado al documento y se irían acumulando.
     */
    useEffect(() => {
        if (!abierto) return;

        campo.current?.focus();

        const alPresionar = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onCancelar();
        };

        document.addEventListener('keydown', alPresionar);

        return () => document.removeEventListener('keydown', alPresionar);
    }, [abierto, onCancelar]);

    if (!abierto) return null;

    const escrito = valor.trim().length;
    // Dos condiciones cuando hay casilla: el texto y la marca.
    const suficiente = escrito >= minimo && (!confirmacion || aceptado);

    function enviar(e: FormEvent) {
        e.preventDefault();

        if (suficiente && !procesando) onConfirmar();
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-motivo"
        >
            <div className="w-full max-w-lg rounded-xl border border-border bg-card shadow-lg">
                <form onSubmit={enviar}>
                    <div className="flex items-start gap-4 p-6 pb-4">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                            <TriangleAlert className="size-5" />
                        </div>

                        <div className="min-w-0 space-y-1">
                            <h2 id="titulo-motivo" className="font-semibold">
                                {titulo}
                            </h2>
                            {descripcion && (
                                <div className="text-sm text-muted-foreground">{descripcion}</div>
                            )}
                        </div>
                    </div>

                    <div className="px-6 pb-2">
                        <Campo
                            etiqueta={etiquetaMotivo}
                            htmlFor="motivo-confirmacion"
                            obligatorio
                            error={error}
                            ayuda={ayuda}
                        >
                            <Textarea
                                ref={campo}
                                id="motivo-confirmacion"
                                rows={4}
                                value={valor}
                                onChange={(e) => onCambiar(e.target.value)}
                                placeholder={placeholder}
                            />
                        </Campo>

                        {/* Cuánto falta para poder confirmar. Sin esto, el botón
                            apagado no dice por qué lo está.

                            Solo habla del TEXTO: si lo que falta es marcar la
                            casilla, ya lo dice la casilla misma, que está a la
                            vista. Decir las dos cosas a la vez confunde. */}
                        {escrito < minimo && (
                            <p className="mt-1 text-xs text-muted-foreground">
                                Faltan {minimo - escrito} caracteres para poder continuar.
                            </p>
                        )}

                        {confirmacion && (
                            <label className="mt-4 flex cursor-pointer items-start gap-2.5 rounded-md border border-border bg-secondary/40 p-3 text-sm">
                                <input
                                    type="checkbox"
                                    checked={aceptado}
                                    onChange={(e) => setAceptado(e.target.checked)}
                                    className="mt-0.5 size-4 shrink-0 rounded border-input accent-primary"
                                />
                                <span>{confirmacion}</span>
                            </label>
                        )}
                    </div>

                    <div className="flex justify-end gap-2 border-t border-border p-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancelar}
                            disabled={procesando}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={procesando || !suficiente}
                        >
                            {procesando ? 'Guardando…' : textoConfirmar}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

/**
 *  LA CASILLA DE CONSENTIMIENTO
 */

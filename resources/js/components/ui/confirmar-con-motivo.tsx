import { TriangleAlert } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Textarea } from '@/components/ui/textarea';

/**
 * ============================================================================
 *  VENTANA DE CONFIRMACIÓN QUE EXIGE ESCRIBIR UN MOTIVO
 * ============================================================================
 *
 * Para las decisiones FINALES que hay que poder explicar después: rechazar un
 * trámite, eliminarlo. En las dos, alguien va a volver a ventanilla a preguntar
 * por qué, y sin el texto guardado nadie puede responderle.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ UNA VENTANA Y NO UN RECUADRO MÁS EN LA PANTALLA
 * ----------------------------------------------------------------------------
 *
 * Porque tapa todo lo demás y obliga a detenerse. Un formulario entre los otros
 * de la página se completa de pasada; estas dos operaciones no se deshacen.
 *
 * Es hermana de `ConfirmarAccion`, que es la versión sin motivo —para lo que
 * solo hay que confirmar—. Se mantienen separadas porque la de acá tiene un
 * campo obligatorio, un mínimo y un contador, y meter todo eso en la otra la
 * volvería un componente con dos modos.
 *
 * ----------------------------------------------------------------------------
 *  NO SE CIERRA AL HACER CLIC EN EL FONDO
 * ----------------------------------------------------------------------------
 *
 * A diferencia de otras ventanas: acá hay texto escrito a mano y un clic al
 * costado lo perdería entero. Se sale con «Cancelar» o con Escape, las dos
 * deliberadas.
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

import { Check, FileUp, Paperclip, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Input } from '@/components/ui/input';
import { useArchivos } from '@/hooks/use-archivos';

/**
 * ============================================================================
 *  CAMPO DE REQUISITO — un papel que el solicitante tiene que adjuntar
 * ============================================================================
 *
 * Es distinto del campo de la fotografía. La foto se imprime en la credencial,
 * así que necesita vista previa: el operador tiene que VER la cara antes de
 * mandar a plastificar. Un requisito, en cambio, no se imprime en ninguna
 * parte: se archiva como respaldo del expediente. Lo único que el operador
 * necesita saber de él es si ya está cargado y qué archivo cargó, porque el
 * error real que se comete es adjuntar dos veces el mismo papel.
 *
 * Por eso acá no hay <img> sino nombre del archivo y una marca de cargado.
 *
 * Acepta PDF además de imágenes: la certificación de la asociación suele
 * llegar escaneada desde una fotocopiadora, y esas máquinas devuelven PDF.
 *
 * El peso se revisa acá mismo, antes de subir nada. Un escaneo de 8 MB por la
 * conexión de la Gobernación tarda, y esperar todo eso para que el servidor lo
 * rechace al final es tiempo perdido con el pescador en la ventanilla. La regla
 * de verdad sigue estando en el servidor; ver el hook useArchivos().
 */
export function CampoRequisito({
    id,
    etiqueta,
    ayuda,
    error,
    archivo,
    onCambio,
}: {
    id: string;
    etiqueta: string;
    ayuda: string;
    error?: string;
    archivo: File | null;
    onCambio: (archivo: File | null) => void;
}) {
    const archivos = useArchivos();

    // El error del navegador es aparte del que devuelve Laravel: el primero
    // aparece al elegir el archivo, el segundo recién al enviar el formulario.
    const [errorLocal, setErrorLocal] = useState<string | null>(null);

    function elegir(elegido: File | null) {
        if (elegido === null) {
            setErrorLocal(null);
            onCambio(null);

            return;
        }

        const problema = archivos.validar(elegido);

        setErrorLocal(problema);

        // Un archivo que no sirve NO se guarda en el formulario: si se guardara,
        // el botón de registrar se encendería con un adjunto que el servidor va
        // a rechazar igual.
        onCambio(problema === null ? elegido : null);
    }

    return (
        <Campo
            etiqueta={etiqueta}
            htmlFor={id}
            error={error ?? errorLocal ?? undefined}
            ayuda={ayuda}
            obligatorio
        >
            <div className="flex items-start gap-3">
                <span
                    className={
                        archivo
                            ? 'flex size-10 shrink-0 items-center justify-center rounded-md border border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                            : 'flex size-10 shrink-0 items-center justify-center rounded-md border border-border bg-muted text-muted-foreground'
                    }
                    aria-hidden
                >
                    {archivo ? <Check className="size-5" /> : <FileUp className="size-5" />}
                </span>

                <div className="min-w-0 flex-1 space-y-2">
                    <Input
                        id={id}
                        type="file"
                        accept={archivos.acepta}
                        aria-invalid={Boolean(error ?? errorLocal)}
                        onChange={(e) => elegir(e.target.files?.[0] ?? null)}
                        className="h-auto py-1.5"
                    />

                    {archivo && (
                        <div className="flex items-center gap-2">
                            {/* `truncate` necesita que el contenedor pueda achicarse:
                                de ahí el min-w-0 del div de arriba. Sin eso el nombre
                                largo de un escaneo estira la tarjeta entera. */}
                            <p className="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                                <Paperclip className="size-3.5 shrink-0" />
                                <span className="truncate">{archivo.name}</span>
                            </p>

                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => elegir(null)}
                                aria-label={`Quitar ${etiqueta}`}
                            >
                                <Trash2 className="size-4" />
                                Quitar
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </Campo>
    );
}

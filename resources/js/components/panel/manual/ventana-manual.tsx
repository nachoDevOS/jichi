import { BookOpen, LoaderCircle, PlayCircle, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ReproductorTutorial } from '@/components/panel/manual/reproductor-tutorial';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { cn } from '@/lib/utils';
import type { Tutorial } from '@/types/manual';

/**
 * La ventana del manual de usuario. Pide los tutoriales al abrirse: el servidor
 * manda solo los de los trámites que el rol puede hacer.
 */
export function VentanaManual({ onCerrar }: { onCerrar: () => void }) {
    const [tutoriales, setTutoriales] = useState<Tutorial[] | null>(null);
    const [fallo, setFallo] = useState(false);
    const [elegido, setElegido] = useState<string>();

    useEffect(() => {
        fetch(route('manual.index'), { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((lista: Tutorial[]) => {
                setTutoriales(lista);
                setElegido(lista[0]?.clave);
            })
            .catch(() => setFallo(true));
    }, []);

    useEffect(() => {
        const alPresionar = (e: KeyboardEvent) => e.key === 'Escape' && onCerrar();
        document.addEventListener('keydown', alPresionar);
        // Sin esto la pantalla de atrás se desplaza con la rueda por debajo de la ventana.
        const desbordeAnterior = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', alPresionar);
            document.body.style.overflow = desbordeAnterior;
        };
    }, [onCerrar]);

    const tutorial = tutoriales?.find((t) => t.clave === elegido);

    return (
        <div
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-ventana-manual"
            onMouseDown={(e) => e.target === e.currentTarget && onCerrar()}
        >
            <div className="w-full max-w-6xl overflow-hidden rounded-xl border border-border bg-card shadow-2xl">
                <div className="flex items-start gap-4 border-b p-5">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                        <BookOpen className="size-5" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h2 id="titulo-ventana-manual" className="font-semibold">
                            Manual de usuario
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Videos cortos que muestran, paso a paso, cómo hacer cada trámite.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onCerrar}
                        className="rounded-md p-1 text-muted-foreground hover:bg-muted"
                        aria-label="Cerrar"
                    >
                        <X className="size-5" />
                    </button>
                </div>

                <div className="p-5">
                    {fallo ? (
                        <EstadoVacio icono={BookOpen} titulo="No se pudo abrir el manual" descripcion="Vuelva a intentarlo en un momento." />
                    ) : !tutoriales ? (
                        <div className="flex justify-center py-24 text-muted-foreground">
                            <LoaderCircle className="size-6 animate-spin" />
                        </div>
                    ) : !tutorial ? (
                        <EstadoVacio
                            icono={BookOpen}
                            titulo="Todavía no hay tutoriales para usted"
                            descripcion="Los videos se muestran según los trámites que su rol puede hacer."
                        />
                    ) : (
                        <div className="space-y-6">
                            {/* La key reinicia el reproductor al cambiar de tutorial. */}
                            <ReproductorTutorial key={tutorial.clave} tutorial={tutorial} />

                            {tutoriales.length > 1 && (
                                <div className="grid gap-3 border-t pt-5 sm:grid-cols-2 lg:grid-cols-4">
                                    {tutoriales.map((t) => (
                                        <button
                                            key={t.clave}
                                            type="button"
                                            onClick={() => setElegido(t.clave)}
                                            className={cn(
                                                'group overflow-hidden rounded-lg border text-left transition-shadow hover:shadow-md',
                                                t.clave === elegido && 'ring-2 ring-primary',
                                            )}
                                        >
                                            <div className="relative aspect-video bg-muted">
                                                <img src={t.portada} alt="" className="size-full object-cover" loading="lazy" />
                                                <PlayCircle className="absolute inset-0 m-auto size-10 text-white drop-shadow-lg transition-transform group-hover:scale-110" />
                                            </div>
                                            <p className="p-2.5 text-sm font-medium">{t.titulo}</p>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

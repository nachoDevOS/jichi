import {
    ArrowLeft,
    BookOpen,
    CheckCircle2,
    Clapperboard,
    Lightbulb,
    ListOrdered,
    LoaderCircle,
    Play,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { leerProgreso, minutos, puntoParaContinuar } from '@/components/panel/manual/progreso-manual';
import { ReproductorTutorial } from '@/components/panel/manual/reproductor-tutorial';
import { Badge } from '@/components/ui/badge';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { cn } from '@/lib/utils';
import type { Tutorial } from '@/types/manual';

/**
 * La ventana del manual de usuario: la biblioteca de videos y el reproductor. Pide los
 * tutoriales al abrirse; el servidor manda solo los de los trámites que el rol puede hacer.
 */
export function VentanaManual({ onCerrar }: { onCerrar: () => void }) {
    const [tutoriales, setTutoriales] = useState<Tutorial[] | null>(null);
    const [fallo, setFallo] = useState(false);
    const [elegido, setElegido] = useState<string | null>(null);

    useEffect(() => {
        fetch(route('manual.index'), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((lista: Tutorial[]) => {
                setTutoriales(lista);
                // Con un solo video la biblioteca sobra: se abre directo.
                if (lista.length === 1) setElegido(lista[0].clave);
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
    const volver = tutoriales && tutoriales.length > 1 ? () => setElegido(null) : undefined;

    return (
        <div
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/70 p-3 backdrop-blur-md animate-in fade-in duration-200 sm:items-center sm:p-6 motion-reduce:animate-none"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-ventana-manual"
            onMouseDown={(e) => e.target === e.currentTarget && onCerrar()}
        >
            <div className="w-full max-w-6xl overflow-hidden rounded-2xl border border-white/10 bg-card shadow-2xl animate-in zoom-in-95 slide-in-from-bottom-4 duration-300 motion-reduce:animate-none">
                <header
                    className="relative overflow-hidden px-5 py-4 text-white sm:px-6"
                    style={{
                        background:
                            'radial-gradient(520px 180px at 92% 0%, color-mix(in oklch, var(--rio-claro) 35%, transparent), transparent 70%),' +
                            'linear-gradient(120deg, var(--rio) 0%, var(--rio-profundo) 75%)',
                    }}
                >
                    <div className="flex items-center gap-4">
                        {tutorial && volver ? (
                            <button
                                type="button"
                                onClick={volver}
                                className="flex size-11 shrink-0 items-center justify-center rounded-full bg-white/10 ring-2 ring-white/20 transition-colors hover:bg-white/20"
                                aria-label="Volver a todos los videos"
                                title="Todos los videos"
                            >
                                <ArrowLeft className="size-5" />
                            </button>
                        ) : (
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-white/10 ring-2 ring-white/20">
                                <BookOpen className="size-5 text-institucional-dorado" />
                            </span>
                        )}
                        <div className="min-w-0 flex-1">
                            <h2 id="titulo-ventana-manual" className="text-lg leading-tight font-semibold">
                                Tutoriales en video
                            </h2>
                            <p className="mt-0.5 truncate text-sm text-white/70">
                                {tutorial
                                    ? tutorial.titulo
                                    : 'Videos cortos que muestran, paso a paso, cómo hacer cada trámite.'}
                            </p>
                        </div>
                        {tutoriales && tutoriales.length > 0 && !tutorial && (
                            <span className="hidden items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-medium ring-1 ring-white/20 sm:flex">
                                <Clapperboard className="size-3.5 text-institucional-dorado" />
                                {tutoriales.length} {tutoriales.length === 1 ? 'video' : 'videos'}
                            </span>
                        )}
                        <button
                            type="button"
                            onClick={onCerrar}
                            className="rounded-full p-2 text-white/70 transition-colors hover:bg-white/15 hover:text-white"
                            aria-label="Cerrar"
                        >
                            <X className="size-5" />
                        </button>
                    </div>
                </header>

                <div className="max-h-[calc(100vh-9rem)] overflow-y-auto p-5 sm:p-6">
                    {fallo ? (
                        <EstadoVacio
                            icono={BookOpen}
                            titulo="No se pudo abrir el manual"
                            descripcion="Vuelva a intentarlo en un momento."
                        />
                    ) : !tutoriales ? (
                        <div className="flex flex-col items-center gap-3 py-24 text-muted-foreground">
                            <LoaderCircle className="size-7 animate-spin text-primary" />
                            <p className="text-sm">Cargando los videos…</p>
                        </div>
                    ) : tutoriales.length === 0 ? (
                        <EstadoVacio
                            icono={BookOpen}
                            titulo="Todavía no hay tutoriales para usted"
                            descripcion="Los videos se muestran según los trámites que su rol puede hacer."
                        />
                    ) : tutorial ? (
                        // La key reinicia el reproductor al cambiar de tutorial.
                        <div
                            key={tutorial.clave}
                            className="animate-in fade-in slide-in-from-right-4 duration-300 motion-reduce:animate-none"
                        >
                            <ReproductorTutorial tutorial={tutorial} onVolver={volver} />
                        </div>
                    ) : (
                        <Biblioteca tutoriales={tutoriales} onElegir={setElegido} />
                    )}
                </div>
            </div>
        </div>
    );
}

function Biblioteca({ tutoriales, onElegir }: { tutoriales: Tutorial[]; onElegir: (clave: string) => void }) {
    return (
        <div className="space-y-5">
            <p className="flex items-start gap-2.5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-100">
                <Lightbulb className="mt-0.5 size-4 shrink-0 text-amber-500" />
                Elija un video. Mientras mira, toque cualquier paso de la lista para ir directo a esa parte; si lo deja
                a la mitad, la próxima vez sigue desde ahí.
            </p>

            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                {tutoriales.map((t, i) => (
                    <TarjetaTutorial key={t.clave} tutorial={t} orden={i} onElegir={() => onElegir(t.clave)} />
                ))}
            </div>
        </div>
    );
}

function TarjetaTutorial({ tutorial, orden, onElegir }: { tutorial: Tutorial; orden: number; onElegir: () => void }) {
    const progreso = leerProgreso(tutorial.clave);
    const continuar = puntoParaContinuar(progreso);
    const avance = progreso && progreso.duracion > 0 ? progreso.segundo / progreso.duracion : 0;
    const previa = useRef<HTMLVideoElement>(null);
    const [andando, setAndando] = useState(false);
    const [vuelta, setVuelta] = useState(0);
    // Quien pidió menos movimiento ve la portada quieta.
    const [conPrevia] = useState(
        () => tutorial.previa !== null && !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    );

    return (
        <button
            type="button"
            onClick={onElegir}
            // Entran una tras otra: la mirada recorre la biblioteca en orden.
            style={{ animationDelay: `${orden * 80}ms` }}
            className="group flex flex-col overflow-hidden rounded-xl border bg-card text-left shadow-sm transition-all duration-300 animate-in fade-in slide-in-from-bottom-3 fill-mode-both hover:-translate-y-1 hover:border-primary/40 hover:shadow-xl focus-visible:ring-2 focus-visible:ring-primary motion-reduce:animate-none motion-reduce:hover:translate-y-0"
        >
            <div
                className="relative aspect-video overflow-hidden bg-muted"
                // Al pasar el mouse la vista previa se apura: invita a entrar.
                onMouseEnter={() => previa.current && (previa.current.playbackRate = 1.6)}
                onMouseLeave={() => previa.current && (previa.current.playbackRate = 1)}
            >
                <img
                    src={tutorial.portada}
                    alt=""
                    loading="lazy"
                    className={cn('size-full object-cover', !conPrevia && 'animar-paneo')}
                />
                {conPrevia && (
                    <video
                        ref={previa}
                        src={tutorial.previa!}
                        muted
                        loop
                        autoPlay
                        playsInline
                        preload="auto"
                        aria-hidden
                        onPlaying={() => setAndando(true)}
                        onTimeUpdate={(e) => setVuelta(e.currentTarget.currentTime / (e.currentTarget.duration || 1))}
                        className={cn(
                            'absolute inset-0 size-full object-cover transition-[opacity,transform] duration-700 group-hover:scale-105',
                            andando ? 'opacity-100' : 'opacity-0',
                        )}
                    />
                )}
                <div className="absolute inset-0 bg-linear-to-t from-slate-950/70 via-slate-950/10 to-transparent" />
                {andando && (
                    <span className="absolute right-2.5 bottom-2.5 flex items-center gap-1.5 rounded-full bg-slate-950/65 px-2 py-0.5 text-[11px] font-medium text-white backdrop-blur animate-in fade-in">
                        <span className="flex h-2.5 items-end gap-px" aria-hidden>
                            {[0, 0.25, 0.5].map((retraso) => (
                                <span
                                    key={retraso}
                                    className="animar-ecualizador w-0.5 rounded-full bg-institucional-dorado"
                                    style={{ height: '100%', animationDelay: `${retraso}s` }}
                                />
                            ))}
                        </span>
                        Vista previa
                    </span>
                )}
                {andando && avance === 0 && !progreso?.visto && (
                    <span className="absolute inset-x-0 bottom-0 h-0.5 bg-white/20">
                        <span className="block h-full bg-institucional-dorado" style={{ width: `${vuelta * 100}%` }} />
                    </span>
                )}
                <span className="absolute top-2.5 left-2.5">
                    <Badge color="sky">{tutorial.modulo}</Badge>
                </span>
                <span className="absolute top-2.5 right-2.5 flex items-center gap-1 rounded-full bg-slate-950/60 px-2 py-0.5 text-[11px] font-medium text-white backdrop-blur">
                    <ListOrdered className="size-3" />
                    {tutorial.capitulos.length} pasos
                </span>
                <span className="absolute inset-0 m-auto flex size-14 items-center justify-center rounded-full bg-white/90 text-primary shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:bg-white">
                    <span className="absolute inset-0 rounded-full bg-white/50 opacity-0 transition-opacity group-hover:animate-ping group-hover:opacity-100 motion-reduce:hidden" />
                    <Play className="relative ml-0.5 size-6 fill-current" />
                </span>
                {progreso?.visto && (
                    <span className="absolute bottom-2.5 left-2.5 flex items-center gap-1 rounded-full bg-emerald-500 px-2 py-0.5 text-[11px] font-semibold text-white shadow">
                        <CheckCircle2 className="size-3" />
                        Visto
                    </span>
                )}
                {avance > 0 && !progreso?.visto && (
                    <span className="absolute inset-x-0 bottom-0 h-1 bg-white/25">
                        <span className="block h-full bg-institucional-dorado" style={{ width: `${avance * 100}%` }} />
                    </span>
                )}
            </div>

            <div className="flex flex-1 flex-col gap-1.5 p-4">
                <p className="leading-snug font-semibold group-hover:text-primary">{tutorial.titulo}</p>
                <p className="line-clamp-2 text-sm text-muted-foreground">{tutorial.descripcion}</p>
                <span className="mt-auto flex items-center gap-1.5 pt-2 text-sm font-medium text-primary">
                    <Play className="size-3.5 fill-current" />
                    {continuar !== null
                        ? `Continuar desde ${minutos(continuar)}`
                        : progreso?.visto
                          ? 'Ver de nuevo'
                          : 'Ver el video'}
                </span>
            </div>
        </button>
    );
}

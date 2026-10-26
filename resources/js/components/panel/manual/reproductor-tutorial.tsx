import { Check, ChevronLeft, ChevronRight, Gauge, LayoutGrid, PartyPopper, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { guardarProgreso, leerProgreso, minutos, puntoParaContinuar } from '@/components/panel/manual/progreso-manual';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Tutorial } from '@/types/manual';

const VELOCIDADES = [0.75, 1, 1.25, 1.5];

/**
 * El video con su barra de capítulos y, al costado, los pasos: un clic salta a ese momento.
 * Recuerda hasta dónde se vio (progreso-manual.ts) y retoma desde ahí.
 */
export function ReproductorTutorial({ tutorial, onVolver }: { tutorial: Tutorial; onVolver?: () => void }) {
    const video = useRef<HTMLVideoElement>(null);
    const lista = useRef<HTMLOListElement>(null);
    const [segundo, setSegundo] = useState(0);
    const [duracion, setDuracion] = useState(0);
    const [velocidad, setVelocidad] = useState(1);
    const [terminado, setTerminado] = useState(false);
    const [retomado, setRetomado] = useState<number | null>(null);
    const ultimoGuardado = useRef(0);

    const capitulos = tutorial.capitulos;
    // El paso en curso es el último que ya empezó.
    const actual = capitulos.reduce((ultimo, c, i) => (c.segundo <= segundo + 0.25 ? i : ultimo), 0);
    const total = duracion || capitulos[capitulos.length - 1].segundo + 10;
    const fin = (i: number) => capitulos[i + 1]?.segundo ?? total;

    // El paso en curso, siempre a la vista en la lista.
    useEffect(() => {
        lista.current
            ?.querySelector(`[data-paso="${actual}"]`)
            ?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }, [actual]);

    function alCargar(e: React.SyntheticEvent<HTMLVideoElement>) {
        const v = e.currentTarget;
        setDuracion(v.duration);
        v.playbackRate = velocidad;
        const desde = puntoParaContinuar(leerProgreso(tutorial.clave));
        if (desde !== null) {
            v.currentTime = desde;
            setRetomado(desde);
            window.setTimeout(() => setRetomado(null), 6000);
        }
    }

    function alAvanzar(e: React.SyntheticEvent<HTMLVideoElement>) {
        const v = e.currentTarget;
        setSegundo(v.currentTime);
        // Cada 2 s alcanza: guardar en cada cuadro es escribir 4 veces por segundo.
        if (Math.abs(v.currentTime - ultimoGuardado.current) > 2) {
            ultimoGuardado.current = v.currentTime;
            const previo = leerProgreso(tutorial.clave);
            guardarProgreso(tutorial.clave, {
                segundo: v.currentTime,
                duracion: v.duration,
                visto: previo?.visto ?? false,
            });
        }
    }

    function alTerminar() {
        setTerminado(true);
        guardarProgreso(tutorial.clave, {
            segundo: 0,
            duracion: total,
            visto: true,
        });
    }

    function irA(s: number) {
        if (!video.current) return;
        setTerminado(false);
        setRetomado(null);
        video.current.currentTime = s;
        void video.current.play();
    }

    function cambiarVelocidad(v: number) {
        setVelocidad(v);
        if (video.current) video.current.playbackRate = v;
    }

    return (
        <div className="grid gap-6 lg:grid-cols-3">
            <div className="min-w-0 space-y-4 lg:col-span-2">
                <div className="relative overflow-hidden rounded-xl bg-black shadow-lg ring-1 ring-black/10">
                    <video
                        ref={video}
                        src={tutorial.video}
                        poster={tutorial.portada}
                        controls
                        autoPlay
                        preload="metadata"
                        playsInline
                        onLoadedMetadata={alCargar}
                        onTimeUpdate={alAvanzar}
                        onEnded={alTerminar}
                        onPlay={() => setTerminado(false)}
                        className="aspect-video w-full"
                    />

                    {retomado !== null && (
                        <div className="absolute top-3 left-1/2 flex -translate-x-1/2 items-center gap-3 rounded-full bg-slate-900/90 py-1.5 pr-1.5 pl-4 text-sm text-white shadow-lg animate-in fade-in slide-in-from-top-2 motion-reduce:animate-none">
                            Seguimos donde lo dejó ({minutos(retomado)})
                            <button
                                type="button"
                                onClick={() => irA(0)}
                                className="flex items-center gap-1 rounded-full bg-white/15 px-3 py-1 text-xs font-medium hover:bg-white/25"
                            >
                                <RotateCcw className="size-3.5" />
                                Desde el inicio
                            </button>
                        </div>
                    )}

                    {terminado && (
                        <div className="absolute inset-0 flex items-center justify-center bg-slate-950/75 backdrop-blur-sm animate-in fade-in motion-reduce:animate-none">
                            <div className="mx-4 max-w-sm rounded-2xl bg-card p-6 text-center shadow-2xl animate-in zoom-in-90 duration-300 motion-reduce:animate-none">
                                <span className="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300">
                                    <PartyPopper className="size-7" />
                                </span>
                                <p className="mt-3 text-lg font-semibold">¡Listo, ya lo vio completo!</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Ahora puede hacer el trámite usted mismo. Si se olvida de un paso, vuelva y tóquelo
                                    en la lista.
                                </p>
                                <div className="mt-5 flex flex-wrap justify-center gap-2">
                                    <Button variant="outline" onClick={() => irA(0)}>
                                        <RotateCcw className="size-4" />
                                        Ver de nuevo
                                    </Button>
                                    {onVolver && (
                                        <Button onClick={onVolver}>
                                            <LayoutGrid className="size-4" />
                                            Otros videos
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Un tramo por paso: se llena con el avance y un clic salta a su comienzo. */}
                <div className="flex h-2.5 gap-1" role="presentation">
                    {capitulos.map((c, i) => {
                        const lleno = Math.min(1, Math.max(0, (segundo - c.segundo) / (fin(i) - c.segundo)));
                        return (
                            <button
                                key={c.segundo}
                                type="button"
                                title={`${i + 1}. ${c.titulo}`}
                                aria-label={`Ir al paso ${i + 1}: ${c.titulo}`}
                                onClick={() => irA(c.segundo)}
                                style={{ flexGrow: fin(i) - c.segundo }}
                                className={cn(
                                    'relative basis-0 overflow-hidden rounded-full bg-muted transition-transform hover:scale-y-150',
                                    i === actual && 'ring-2 ring-primary/30',
                                )}
                            >
                                <span
                                    className="absolute inset-y-0 left-0 rounded-full bg-primary transition-[width] duration-300"
                                    style={{ width: `${lleno * 100}%` }}
                                />
                            </button>
                        );
                    })}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={actual === 0}
                            onClick={() => irA(capitulos[actual - 1].segundo)}
                        >
                            <ChevronLeft className="size-4" />
                            Paso anterior
                        </Button>
                        <Button
                            size="sm"
                            disabled={actual === capitulos.length - 1}
                            onClick={() => irA(capitulos[actual + 1].segundo)}
                        >
                            Siguiente paso
                            <ChevronRight className="size-4" />
                        </Button>
                    </div>
                    <div
                        className="flex items-center gap-1 rounded-full bg-muted p-1"
                        role="group"
                        aria-label="Velocidad"
                    >
                        <Gauge className="mx-1.5 size-4 text-muted-foreground" />
                        {VELOCIDADES.map((v) => (
                            <button
                                key={v}
                                type="button"
                                onClick={() => cambiarVelocidad(v)}
                                aria-pressed={velocidad === v}
                                className={cn(
                                    'rounded-full px-2.5 py-0.5 text-xs font-medium tabular-nums transition-colors',
                                    velocidad === v
                                        ? 'bg-card text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {v}×
                            </button>
                        ))}
                    </div>
                </div>

                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-lg font-semibold">{tutorial.titulo}</h3>
                        <Badge color="sky">{tutorial.modulo}</Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">{tutorial.descripcion}</p>
                </div>
            </div>

            <div className="min-w-0">
                <div className="mb-3 flex items-baseline justify-between px-1">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Paso {actual + 1} de {capitulos.length}
                    </p>
                    <p className="text-xs text-muted-foreground tabular-nums">
                        {minutos(segundo)} / {minutos(total)}
                    </p>
                </div>
                <ol ref={lista} className="relative max-h-[28rem] space-y-1 overflow-y-auto pr-1">
                    {capitulos.map((c, i) => {
                        const hecho = i < actual || (terminado && i === actual);
                        const enCurso = i === actual && !terminado;
                        return (
                            <li key={c.segundo} data-paso={i}>
                                <button
                                    type="button"
                                    onClick={() => irA(c.segundo)}
                                    className={cn(
                                        'group flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left text-sm transition-all hover:bg-muted',
                                        enCurso && 'bg-primary/10 shadow-sm ring-1 ring-primary/20',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'relative flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-colors',
                                            hecho && 'bg-emerald-500 text-white',
                                            enCurso && 'bg-primary text-primary-foreground',
                                            !hecho &&
                                                !enCurso &&
                                                'bg-muted text-muted-foreground group-hover:bg-secondary',
                                        )}
                                    >
                                        {enCurso && (
                                            <span className="absolute inset-0 animate-ping rounded-full bg-primary/40 motion-reduce:hidden" />
                                        )}
                                        {hecho ? <Check className="size-4" /> : i + 1}
                                    </span>
                                    <span
                                        className={cn(
                                            'min-w-0 flex-1',
                                            enCurso ? 'font-medium text-primary' : hecho && 'text-muted-foreground',
                                        )}
                                    >
                                        {c.titulo}
                                    </span>
                                    <span className="shrink-0 font-mono text-xs text-muted-foreground tabular-nums">
                                        {minutos(c.segundo)}
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ol>
            </div>
        </div>
    );
}

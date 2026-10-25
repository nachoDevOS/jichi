import { useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { Tutorial } from '@/types/manual';

/**
 * El video del tutorial y, al costado, sus pasos: un clic salta a ese momento.
 */
export function ReproductorTutorial({ tutorial }: { tutorial: Tutorial }) {
    const video = useRef<HTMLVideoElement>(null);
    const [segundo, setSegundo] = useState(0);

    // El paso en curso es el último que ya empezó.
    const actual = tutorial.capitulos.reduce((ultimo, c, i) => (c.segundo <= segundo ? i : ultimo), 0);

    function irA(s: number) {
        if (!video.current) return;
        video.current.currentTime = s;
        void video.current.play();
    }

    return (
        <div className="grid gap-5 lg:grid-cols-3">
            <div className="min-w-0 space-y-3 lg:col-span-2">
                <video
                    ref={video}
                    src={tutorial.video}
                    poster={tutorial.portada}
                    controls
                    autoPlay
                    preload="metadata"
                    playsInline
                    onTimeUpdate={(e) => setSegundo(e.currentTarget.currentTime)}
                    className="aspect-video w-full rounded-lg bg-black"
                />
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-semibold">{tutorial.titulo}</h3>
                        <Badge color="sky">{tutorial.modulo}</Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">{tutorial.descripcion}</p>
                </div>
            </div>

            <div className="min-w-0">
                <p className="mb-2 px-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Pasos</p>
                <ol className="space-y-0.5">
                    {tutorial.capitulos.map((c, i) => (
                        <li key={c.segundo}>
                            <button
                                type="button"
                                onClick={() => irA(c.segundo)}
                                className={cn(
                                    'flex w-full items-center gap-3 rounded-md px-3 py-1.5 text-left text-sm transition-colors hover:bg-muted',
                                    i === actual && 'bg-primary/10 font-medium text-primary',
                                )}
                            >
                                <span className="w-9 shrink-0 font-mono text-xs text-muted-foreground tabular-nums">
                                    {minutos(c.segundo)}
                                </span>
                                <span>{c.titulo}</span>
                            </button>
                        </li>
                    ))}
                </ol>
            </div>
        </div>
    );
}

function minutos(segundos: number): string {
    return `${Math.floor(segundos / 60)}:${String(Math.floor(segundos % 60)).padStart(2, '0')}`;
}

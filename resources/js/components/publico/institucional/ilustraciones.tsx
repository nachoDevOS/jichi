import type { CSSProperties } from 'react';

/**
 * Dibujos de la portada, hechos en SVG: pesan nada y no tienen derechos de
 * autor que cuidar. El color lo pone quien los usa, con `text-*`.
 */

// Un período de 720 repetido cuatro veces: correr la mitad (1440) cierra el bucle sin salto.
const TRAZO_OLA =
    'M0,40 C180,0 540,80 720,40 C900,0 1260,80 1440,40 C1620,0 1980,80 2160,40 C2340,0 2700,80 2880,40 L2880,100 L0,100 Z';

/** Agua que corre: dos capas a distinta velocidad dan profundidad. */
export function Olas({ className = '' }: { className?: string }) {
    return (
        <div
            className={`pointer-events-none absolute inset-x-0 bottom-0 h-24 overflow-hidden sm:h-32 ${className}`}
            aria-hidden
        >
            <svg
                viewBox="0 0 2880 100"
                preserveAspectRatio="none"
                className="animar-agua-lenta absolute bottom-3 left-0 h-full w-[200%] text-rio-claro/25"
            >
                <path d={TRAZO_OLA} fill="currentColor" />
            </svg>
            <svg
                viewBox="0 0 2880 100"
                preserveAspectRatio="none"
                className="animar-agua absolute bottom-0 left-0 h-3/4 w-[200%] text-white"
            >
                <path d={TRAZO_OLA} fill="currentColor" />
            </svg>
        </div>
    );
}

/** Borde ondulado entre dos secciones. `invertida` la pone arriba, mirando para abajo. */
export function OlaDivisoria({
    className = '',
    invertida = false,
}: {
    className?: string;
    invertida?: boolean;
}) {
    return (
        <svg
            viewBox="0 0 1440 100"
            preserveAspectRatio="none"
            className={`block h-10 w-full sm:h-16 ${invertida ? 'rotate-180' : ''} ${className}`}
            aria-hidden
        >
            <path d={TRAZO_OLA} fill="currentColor" />
        </svg>
    );
}

/** Silueta de pez mirando a la izquierda, que es hacia donde nada. */
export function Pez({ className = '', style }: { className?: string; style?: CSSProperties }) {
    return (
        <svg viewBox="0 0 64 28" className={className} style={style} aria-hidden>
            <path
                d="M4 14 C12 3 30 2 46 12 L60 3 L56 14 L60 25 L46 16 C30 26 12 25 4 14 Z"
                fill="currentColor"
            />
            <circle cx="12" cy="12.5" r="1.8" className="fill-rio-profundo" />
        </svg>
    );
}

/** Canoa con pescador, en contraluz sobre el agua. */
export function Canoa({ className = '' }: { className?: string }) {
    return (
        <svg viewBox="0 0 200 90" className={className} aria-hidden>
            <g fill="currentColor">
                {/* Casco */}
                <path d="M8 62 Q100 86 192 62 L180 72 Q100 90 20 72 Z" />
                {/* Pescador */}
                <circle cx="96" cy="26" r="7" />
                <path d="M90 34 L102 34 L106 60 L86 60 Z" />
                {/* Remo */}
                <path d="M104 38 L150 76 L147 79 L101 41 Z" />
                <ellipse cx="152" cy="80" rx="8" ry="3" transform="rotate(40 152 80)" />
            </g>
        </svg>
    );
}

/** Peces que cruzan el cuadro. Cada uno con su altura, tamaño y ritmo. */
const CARDUMEN: {
    arriba: string;
    ancho: string;
    duracion: string;
    retraso: string;
    tono: string;
}[] = [
    {
        arriba: '22%',
        ancho: 'w-14',
        duracion: '26s',
        retraso: '0s',
        tono: 'text-white/20',
    },
    {
        arriba: '48%',
        ancho: 'w-9',
        duracion: '19s',
        retraso: '-7s',
        tono: 'text-rio-claro/35',
    },
    {
        arriba: '66%',
        ancho: 'w-20',
        duracion: '34s',
        retraso: '-15s',
        tono: 'text-white/15',
    },
    {
        arriba: '36%',
        ancho: 'w-7',
        duracion: '23s',
        retraso: '-3s',
        tono: 'text-institucional-dorado/40',
    },
];

export function Cardumen() {
    return (
        <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden>
            {CARDUMEN.map((p) => (
                <div
                    key={p.arriba}
                    className="animar-nado absolute left-full"
                    style={
                        {
                            top: p.arriba,
                            '--duracion': p.duracion,
                            '--retraso': p.retraso,
                        } as CSSProperties
                    }
                >
                    <Pez className={`animar-flote ${p.ancho} ${p.tono}`} />
                </div>
            ))}
        </div>
    );
}

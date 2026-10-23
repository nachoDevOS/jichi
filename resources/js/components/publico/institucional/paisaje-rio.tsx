/**
 * Atardecer sobre un río del Beni: monte con motacú, pescador lanzando la
 * tarrafa y un pez que salta. SVG puro, sin fotos ni derechos que cuidar.
 */

const HORIZONTE = 246;
const OSCURO = '#0a2027';

type Punto = [number, number];

/** Punto de una curva cuadrática en t. */
function cuadratica(p0: Punto, p1: Punto, p2: Punto, t: number): Punto {
    const u = 1 - t;

    return [u * u * p0[0] + 2 * u * t * p1[0] + t * t * p2[0], u * u * p0[1] + 2 * u * t * p1[1] + t * t * p2[1]];
}

/** Perfil del monte: ondas superpuestas, deterministas para que no cambie entre renders. */
function perfil(base: number, amplitud: number, fase: number, paso = 4): Punto[] {
    const puntos: Punto[] = [];

    for (let x = 0; x <= 400; x += paso) {
        const y =
            base -
            amplitud *
                (0.55 * Math.sin(x * 0.045 + fase) +
                    0.3 * Math.sin(x * 0.13 + fase * 2) +
                    0.15 * Math.sin(x * 0.37 + fase * 3) +
                    1);
        puntos.push([x, y]);
    }

    return puntos;
}

const linea = (puntos: Punto[]) =>
    puntos.map(([x, y], i) => `${i ? 'L' : 'M'}${x.toFixed(1)},${y.toFixed(1)}`).join(' ');

// Palmeras de motacú: [x, alto del tronco, inclinación, escala].
const PALMERAS: [number, number, number, number][] = [
    [34, 50, -3, 0.9],
    [62, 62, 2, 1],
    [84, 44, 4, 0.75],
    [304, 54, -2, 0.95],
    [330, 42, 3, 0.75],
    [362, 66, -3, 1.05],
];

function Palmera({ x, alto, inclinacion, escala }: { x: number; alto: number; inclinacion: number; escala: number }) {
    const base = HORIZONTE - 2;
    const copa: Punto = [x + inclinacion, base - alto];
    const hojas = [-172, -150, -128, -100, -72, -48, -20, -5];

    return (
        <g>
            {/* Tronco que se afina hacia arriba, con los anillos de las vainas viejas. */}
            <path
                d={`M${x - 1.4},${base} Q${x + inclinacion * 0.4},${base - alto / 2} ${copa[0] - 0.7},${copa[1]} L${copa[0] + 0.7},${copa[1]} Q${x + inclinacion * 0.4 + 2},${base - alto / 2} ${x + 1.4},${base} Z`}
            />
            {hojas.map((angulo) => {
                const rad = (angulo * Math.PI) / 180;
                const largo = (angulo < -150 || angulo > -20 ? 24 : 30) * escala;
                const fin: Punto = [
                    copa[0] + Math.cos(rad) * largo,
                    copa[1] + Math.sin(rad) * largo * 0.45 + 12 * escala,
                ];
                const control: Punto = [(copa[0] + fin[0]) / 2, Math.min(copa[1], fin[1]) - 9 * escala];
                const foliolos = [];

                for (let t = 0.18; t < 0.98; t += 0.07) {
                    const [px, py] = cuadratica(copa, control, fin, t);
                    const l = (1.2 + (1 - t) * 4.5) * escala;
                    foliolos.push(
                        `M${px.toFixed(1)},${py.toFixed(1)} l${(-l * 0.5).toFixed(1)},${(l * 0.9).toFixed(1)}`,
                    );
                    foliolos.push(
                        `M${px.toFixed(1)},${py.toFixed(1)} l${(l * 0.5).toFixed(1)},${(l * 0.9).toFixed(1)}`,
                    );
                }

                return (
                    <g key={angulo} fill="none" stroke="currentColor" strokeLinecap="round">
                        <path
                            d={`M${copa[0]},${copa[1]} Q${control[0]},${control[1]} ${fin[0]},${fin[1]}`}
                            strokeWidth="0.9"
                        />
                        <path d={foliolos.join(' ')} strokeWidth="0.45" />
                    </g>
                );
            })}
        </g>
    );
}

// Brillo del sol en el agua: angosto en el horizonte, más ancho hacia adelante.
const BRILLO = Array.from({ length: 34 }, (_, i) => {
    const y = HORIZONTE + 3 + Math.pow(i, 1.32) * 1.45;
    const ancho = 4 + i * 1.6 + Math.sin(i * 1.7) * 4;

    return {
        y,
        x: 200 - ancho / 2 + Math.sin(i * 2.3) * 3,
        ancho,
        opacidad: Math.max(0.08, 0.85 - i * 0.024),
    };
});

// Ondas finas sueltas por el agua.
const ONDAS: [number, number, number][] = [
    [40, 292, 26],
    [92, 330, 34],
    [300, 286, 22],
    [330, 320, 30],
    [250, 352, 40],
    [60, 368, 44],
    [150, 342, 22],
    [356, 360, 28],
    [24, 318, 18],
    [276, 378, 36],
];

/** Malla de la tarrafa abierta en el aire: radios desde la mano y anillos concéntricos. */
function Tarrafa({ mano }: { mano: Punto }) {
    const centro: Punto = [mano[0] + 30, mano[1] + 16];
    const borde = Array.from({ length: 15 }, (_, i) => {
        const a = ((-105 + i * 17) * Math.PI) / 180;

        return [centro[0] + Math.cos(a) * 19, centro[1] + Math.sin(a) * 24] as Punto;
    });
    const anillo = (k: number) =>
        linea(borde.map(([x, y]) => [mano[0] + (x - mano[0]) * k, mano[1] + (y - mano[1]) * k] as Punto));

    return (
        <g fill="none" stroke={OSCURO} strokeLinecap="round" opacity="0.85">
            {borde.map(([x, y], i) => (
                <path key={i} d={`M${mano[0]},${mano[1]} L${x.toFixed(1)},${y.toFixed(1)}`} strokeWidth="0.35" />
            ))}
            {[0.3, 0.5, 0.7, 0.86].map((k) => (
                <path key={k} d={anillo(k)} strokeWidth="0.35" />
            ))}
            <path d={anillo(1)} strokeWidth="0.7" />
            {/* Las plomadas del borde. */}
            {borde.map(([x, y], i) => (
                <circle key={i} cx={x} cy={y} r="0.7" fill={OSCURO} stroke="none" />
            ))}
        </g>
    );
}

export function PaisajeRio({ className = '' }: { className?: string }) {
    const monteLejano = perfil(HORIZONTE - 5, 4.5, 0.6);
    const monteCercano = perfil(HORIZONTE - 1, 2.6, 2.1);
    const reflejoMonte = monteCercano.map(([x, y]) => [x, HORIZONTE + (HORIZONTE - y) * 0.7] as Punto);

    return (
        <svg viewBox="0 0 400 400" className={className} aria-hidden>
            <defs>
                <linearGradient id="pr-cielo" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#0c3550" />
                    <stop offset="0.3" stopColor="#2b6e8a" />
                    <stop offset="0.58" stopColor="#d38a7e" />
                    <stop offset="0.8" stopColor="#f39a52" />
                    <stop offset="1" stopColor="#ffd07a" />
                </linearGradient>
                <radialGradient id="pr-sol" cx="0.5" cy="0.4" r="0.6">
                    <stop offset="0" stopColor="#fffaf0" />
                    <stop offset="0.5" stopColor="#ffe3a3" />
                    <stop offset="1" stopColor="#f7a440" />
                </radialGradient>
                <radialGradient id="pr-resplandor" cx="0.5" cy="0.5" r="0.5">
                    <stop offset="0" stopColor="#ffe2a0" stopOpacity="0.8" />
                    <stop offset="0.35" stopColor="#ffab5c" stopOpacity="0.35" />
                    <stop offset="1" stopColor="#ffab5c" stopOpacity="0" />
                </radialGradient>
                <linearGradient id="pr-nube" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#7a6f97" stopOpacity="0.55" />
                    <stop offset="1" stopColor="#ffb98a" stopOpacity="0.9" />
                </linearGradient>
                <linearGradient id="pr-agua" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#e9a066" />
                    <stop offset="0.1" stopColor="#8f8478" />
                    <stop offset="0.22" stopColor="#2f7c88" />
                    <stop offset="0.6" stopColor="#145566" />
                    <stop offset="1" stopColor="#072835" />
                </linearGradient>
                <linearGradient id="pr-bruma" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#ffbf85" stopOpacity="0" />
                    <stop offset="1" stopColor="#ffbf85" stopOpacity="0.5" />
                </linearGradient>
                <linearGradient id="pr-pez" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#1b3b40" />
                    <stop offset="1" stopColor="#0a1f24" />
                </linearGradient>
                <radialGradient id="pr-vineta" cx="0.5" cy="0.45" r="0.62">
                    <stop offset="0.7" stopColor="#000" stopOpacity="0" />
                    <stop offset="1" stopColor="#021216" stopOpacity="0.55" />
                </radialGradient>
            </defs>

            <rect width="400" height={HORIZONTE + 4} fill="url(#pr-cielo)" />

            {/* Nubes: vetas largas y finas, iluminadas desde abajo. */}
            <g fill="url(#pr-nube)">
                <path d="M30,122 C70,114 130,116 172,121 C140,126 80,127 30,122 Z" opacity="0.8" />
                <path d="M84,134 C110,130 150,131 176,134 C150,137 112,138 84,134 Z" opacity="0.6" />
                <path d="M226,104 C270,96 340,98 384,104 C340,110 270,111 226,104 Z" opacity="0.75" />
                <path d="M250,116 C280,112 320,113 346,116 C320,119 282,120 250,116 Z" opacity="0.5" />
                <path d="M150,82 C180,78 220,79 244,82 C220,85 182,86 150,82 Z" opacity="0.35" />
            </g>

            <circle cx="200" cy="226" r="130" fill="url(#pr-resplandor)" />
            <circle cx="200" cy="232" r="38" fill="url(#pr-sol)" />

            {/* Garzas a lo lejos. */}
            <g
                fill="none"
                stroke={OSCURO}
                strokeWidth="0.75"
                strokeLinecap="round"
                strokeLinejoin="round"
                opacity="0.75"
            >
                <path d="M120,152 q3,-3.2 6.5,-0.6 q3,-2.8 6.5,0.4" />
                <path d="M141,141 q2.2,-2.4 4.6,-0.4 q2.2,-2 4.6,0.3" />
                <path d="M132,163 q1.8,-1.9 3.6,-0.3 q1.8,-1.6 3.6,0.2" />
                <path d="M270,166 q2.6,-2.8 5.4,-0.5 q2.6,-2.4 5.4,0.4" />
            </g>

            <rect y={HORIZONTE - 46} width="400" height="50" fill="url(#pr-bruma)" />

            {/* Monte: el plano lejano más claro por la bruma, el cercano casi negro, con las palmeras. */}
            <path
                d={`${linea(monteLejano)} L400,${HORIZONTE + 2} L0,${HORIZONTE + 2} Z`}
                fill="#6b6f86"
                opacity="0.7"
            />
            <g fill="#11302f" color="#11302f">
                {PALMERAS.map(([x, alto, inclinacion, escala]) => (
                    <Palmera key={x} x={x} alto={alto} inclinacion={inclinacion} escala={escala} />
                ))}
                <path d={`${linea(monteCercano)} L400,${HORIZONTE + 1} L0,${HORIZONTE + 1} Z`} />
            </g>

            <rect y={HORIZONTE} width="400" height={400 - HORIZONTE} fill="url(#pr-agua)" />

            {/* El monte reflejado, apagado y cortado por el agua. */}
            <path d={`${linea(reflejoMonte)} L400,${HORIZONTE} L0,${HORIZONTE} Z`} fill="#0f2c2e" opacity="0.45" />
            <rect y={HORIZONTE} width="400" height="0.8" fill="#ffe2a0" opacity="0.5" />

            {/* Cada franja va partida en trazos desparejos: así se mueve como agua y no como escalera. */}
            <g fill="#ffd38a">
                {BRILLO.flatMap((b, i) => {
                    const corte = 0.35 + 0.25 * Math.sin(i * 3.1);
                    const hueco = 1.5 + Math.abs(Math.sin(i * 1.3)) * 3;

                    return [
                        [b.x, b.ancho * corte],
                        [b.x + b.ancho * corte + hueco, b.ancho * (1 - corte) - hueco],
                    ]
                        .filter(([, ancho]) => ancho > 1)
                        .map(([x, ancho], j) => (
                            <rect
                                key={`${b.y}-${j}`}
                                x={x}
                                y={b.y}
                                width={ancho}
                                height={0.5 + b.y / 450}
                                rx="0.5"
                                opacity={b.opacidad * (j ? 0.75 : 1)}
                            />
                        ));
                })}
            </g>

            <g fill="none" stroke="#cfeae4" strokeLinecap="round" strokeWidth="0.5" opacity="0.3">
                {ONDAS.map(([x, y, ancho]) => (
                    <path key={`${x}-${y}`} d={`M${x},${y} q${ancho / 2},-1.6 ${ancho},0`} />
                ))}
            </g>

            {/* El pez que salta: lomo con luz del sol, gotas y anillos en el agua. */}
            <g className="animar-flote">
                <path
                    d="M92,268 C96,259 108,254 120,257 C123,256 126,254 129,250 L128,257.5 L134,262 L126,261 C116,268 102,271 92,268 Z"
                    fill="url(#pr-pez)"
                />
                <path
                    d="M100,259 C106,255 114,254 119,256"
                    fill="none"
                    stroke="#f2c47c"
                    strokeWidth="0.5"
                    opacity="0.8"
                />
                <path d="M106,257 L110,252 L114,256" fill="#0f2a30" />
                <path d="M104,267 L107,271 L110,267" fill="#0f2a30" />
                <circle cx="96.5" cy="264.5" r="0.8" fill="#f2c47c" />
                <g fill="#dff3ee" opacity="0.8">
                    <circle cx="88" cy="272" r="0.7" />
                    <circle cx="85" cy="268" r="0.5" />
                    <circle cx="137" cy="266" r="0.6" />
                    <circle cx="140" cy="270" r="0.45" />
                </g>
            </g>
            <g fill="none" stroke="#dff3ee" strokeWidth="0.5">
                <ellipse cx="112" cy="284" rx="11" ry="1.6" opacity="0.5" />
                <ellipse cx="112" cy="284" rx="19" ry="2.7" opacity="0.3" />
                <ellipse cx="112" cy="284" rx="28" ry="3.8" opacity="0.15" />
            </g>

            {/* Canoa con el pescador lanzando la tarrafa. */}
            <g className="animar-flote">
                <path d="M138,304 Q210,317 286,300 L281,306 Q210,322 145,310 Z" fill={OSCURO} />
                <path d="M141,305 Q210,318 283,301" fill="none" stroke="#d99a55" strokeWidth="0.6" opacity="0.7" />
                <path d="M160,309 L148,316" stroke={OSCURO} strokeWidth="1.2" strokeLinecap="round" />

                <g fill="none" stroke={OSCURO} strokeLinecap="round" strokeLinejoin="round">
                    <path d="M203,266 L201.5,285" strokeWidth="6.4" />
                    <path d="M201,284 L197,305" strokeWidth="2.8" />
                    <path d="M202.5,284 L208,305" strokeWidth="2.8" />
                    <path d="M204.5,268 L215,259 L225,249" strokeWidth="2.1" />
                    <path d="M202,269 L194,277 L190,283" strokeWidth="2" />
                </g>
                <circle cx="204" cy="259.5" r="3.9" fill={OSCURO} />
                {/* Sombrero de ala ancha, con el borde encendido por el sol. */}
                <ellipse cx="204" cy="256.4" rx="7.6" ry="1.5" fill={OSCURO} />
                <path d="M200.2,256.4 Q204,249.6 207.8,256.4 Z" fill={OSCURO} />
                <path
                    d="M196.6,256.2 Q204,254.4 211.4,256.2"
                    fill="none"
                    stroke="#f2c47c"
                    strokeWidth="0.4"
                    opacity="0.8"
                />
                {/* Cuerda de la tarrafa, de la mano izquierda a la red. */}
                <path
                    d="M190,283 C200,276 214,262 225,249"
                    fill="none"
                    stroke={OSCURO}
                    strokeWidth="0.35"
                    opacity="0.7"
                />
                <Tarrafa mano={[225, 249]} />
            </g>

            {/* Sombra de la canoa en el agua, ondulada. */}
            <g fill={OSCURO} opacity="0.4">
                <ellipse cx="212" cy="315" rx="66" ry="2.6" />
                <rect x="170" y="320" width="70" height="0.9" rx="0.45" />
                <rect x="186" y="324" width="44" height="0.7" rx="0.35" />
            </g>

            {/* Juncos en primer plano, para dar profundidad. */}
            <g fill="none" stroke="#06181d" strokeLinecap="round">
                <path d="M356,400 Q352,360 340,330" strokeWidth="1.1" />
                <path d="M364,400 Q364,356 372,322" strokeWidth="1" />
                <path d="M372,400 Q376,370 392,348" strokeWidth="0.9" />
                <path d="M348,400 Q338,374 322,358" strokeWidth="0.8" />
                <path d="M30,400 Q34,372 46,352" strokeWidth="0.9" />
                <path d="M22,400 Q16,370 6,350" strokeWidth="0.8" />
            </g>

            <rect width="400" height="400" fill="url(#pr-vineta)" />
        </svg>
    );
}

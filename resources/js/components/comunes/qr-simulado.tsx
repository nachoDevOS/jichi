import { useMemo } from 'react';

const TAMANO = 25;

/**
 * Un QR de MUESTRA: tiene la forma de uno pero no se puede leer. Sirve para mostrar
 * el pago por QR mientras SIREB no da el suyo. El dibujo sale del texto, así que es fijo.
 */
export function QrSimulado({ texto, className }: { texto: string; className?: string }) {
    const modulos = useMemo(() => armarMatriz(texto), [texto]);

    return (
        <svg
            viewBox={`-2 -2 ${TAMANO + 4} ${TAMANO + 4}`}
            className={className}
            shapeRendering="crispEdges"
            role="img"
            aria-label="Código QR de muestra"
        >
            <rect x={-2} y={-2} width={TAMANO + 4} height={TAMANO + 4} fill="#fff" />
            {modulos.map(([x, y]) => (
                <rect key={`${x}-${y}`} x={x} y={y} width={1} height={1} fill="#0f172a" />
            ))}
        </svg>
    );
}

function armarMatriz(texto: string): [number, number][] {
    // Generador con semilla (mulberry32): el mismo texto da siempre el mismo dibujo.
    let semilla = [...texto].reduce((h, c) => Math.imul(h ^ c.charCodeAt(0), 16777619), 2166136261);
    const azar = () => {
        semilla = (semilla + 0x6d2b79f5) | 0;
        let t = Math.imul(semilla ^ (semilla >>> 15), 1 | semilla);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };

    const esquinas = [
        [0, 0],
        [TAMANO - 7, 0],
        [0, TAMANO - 7],
    ];
    const enEsquina = (x: number, y: number) => esquinas.find(([ex, ey]) => x >= ex - 1 && x <= ex + 7 && y >= ey - 1 && y <= ey + 7);

    const puntos: [number, number][] = [];
    for (let y = 0; y < TAMANO; y++) {
        for (let x = 0; x < TAMANO; x++) {
            const esquina = enEsquina(x, y);
            if (esquina) {
                // Los tres cuadrados de posición: anillo de 7, blanco, centro de 3.
                const dx = x - esquina[0];
                const dy = y - esquina[1];
                if (dx < 0 || dy < 0 || dx > 6 || dy > 6) continue;
                const anillo = dx === 0 || dy === 0 || dx === 6 || dy === 6;
                const centro = dx >= 2 && dx <= 4 && dy >= 2 && dy <= 4;
                if (anillo || centro) puntos.push([x, y]);
            } else if (x === 6 || y === 6) {
                if ((x + y) % 2 === 0) puntos.push([x, y]);
            } else if (azar() < 0.5) {
                puntos.push([x, y]);
            }
        }
    }
    return puntos;
}

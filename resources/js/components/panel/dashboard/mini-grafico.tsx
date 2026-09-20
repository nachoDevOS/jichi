/**
 *  LAS LÍNEAS CHICAS DEL PIE DE LOS INDICADORES
 */

/** Alto del sistema de coordenadas interno. El ancho siempre es 100. */
const ALTO = 32;

/**
 * Convierte la serie a coordenadas.
 */
function coordenadas(valores: number[]): { x: number; y: number }[] {
    const max = Math.max(...valores, 1);
    const paso = valores.length > 1 ? 100 / (valores.length - 1) : 0;

    return valores.map((valor, i) => ({
        x: i * paso,
        // Se reservan 3 unidades arriba y 2 abajo: sin ese aire, el punto más
        // alto queda cortado por el borde y el más bajo se pega al filo.
        y: ALTO - 2 - (valor / max) * (ALTO - 5),
    }));
}

/**
 * La serie como línea con el área rellena debajo.
 *
 * Se usa para el dinero: lo que importa de la recaudación es el acumulado —el
 * bulto bajo la curva— y no cada día suelto.
 */
export function MiniLinea({ valores, className }: { valores: number[]; className?: string }) {
    if (valores.length < 2) {
        return null;
    }

    const puntos = coordenadas(valores);
    const linea = puntos.map((p) => `${p.x},${p.y}`).join(' ');

    return (
        <svg
            viewBox={`0 0 100 ${ALTO}`}
            preserveAspectRatio="none"
            aria-hidden
            className={className}
        >
            {/* El área se cierra bajando a la base en los dos extremos. */}
            <polygon points={`0,${ALTO} ${linea} 100,${ALTO}`} fill="currentColor" opacity={0.25} />

            <polyline
                points={linea}
                fill="none"
                stroke="currentColor"
                strokeWidth={2}
                strokeLinejoin="round"
                vectorEffect="non-scaling-stroke"
            />
        </svg>
    );
}

/**
 * La serie como barras sueltas.
 */
export function MiniBarras({ valores, className }: { valores: number[]; className?: string }) {
    if (valores.length === 0) {
        return null;
    }

    const max = Math.max(...valores, 1);
    const ancho = 100 / valores.length;

    return (
        <svg
            viewBox={`0 0 100 ${ALTO}`}
            preserveAspectRatio="none"
            aria-hidden
            className={className}
        >
            {valores.map((valor, i) => {
                // Piso de 1,5 unidades: un día en cero tiene que dejar una
                // marca visible, o la barra desaparece y el dibujo miente
                // diciendo que ese día no existió.
                const alto = Math.max(1.5, (valor / max) * (ALTO - 3));

                return (
                    <rect
                        // Separación de un 20% del ancho para que se lean como
                        // barras y no como un bloque macizo.
                        key={i}
                        x={i * ancho + ancho * 0.1}
                        y={ALTO - alto}
                        width={ancho * 0.8}
                        height={alto}
                        fill="currentColor"
                        opacity={valor > 0 ? 0.75 : 0.3}
                    />
                );
            })}
        </svg>
    );
}

/**
 * ============================================================================
 *  LAS LÍNEAS CHICAS DEL PIE DE LOS INDICADORES
 * ============================================================================
 *
 * POR QUÉ ESTO NO USA RECHARTS, que ya está en el proyecto.
 *
 * Un dibujo de estos son catorce puntos unidos, sin ejes, sin cuadrícula, sin
 * leyenda y sin globito al pasar el mouse. Recharts pesa más de 100 kB y trae
 * todo eso; acá se usaría el 2%. Y no es un costo cualquiera: los cuatro
 * indicadores son lo PRIMERO que se mira al entrar al sistema, la parte que el
 * tablero carga aparte justamente para que aparezca de inmediato (ver el
 * comentario de `lazy()` en pages/panel/dashboard.tsx). Meterles una librería
 * pesada adentro desarma esa decisión.
 *
 * Un SVG a mano son treinta líneas y se dibuja en el primer cuadro.
 *
 * ----------------------------------------------------------------------------
 *  LOS DOS DETALLES QUE HACEN FALTA PARA QUE UN SVG ESTIRADO NO SE VEA MAL
 * ----------------------------------------------------------------------------
 *
 * 1. `preserveAspectRatio="none"` es lo que permite que el mismo dibujo llene
 *    una tarjeta angosta y una ancha. Sin eso el SVG conserva su proporción y
 *    deja aire a los costados.
 *
 * 2. Estirar el dibujo estira TAMBIÉN el grosor del trazo, y de forma despareja
 *    —mucho a lo ancho, poco a lo alto—, así que la línea sale como una cuña.
 *    `vector-effect="non-scaling-stroke"` le dice al navegador que dibuje el
 *    trazo con el grosor pedido sin importar cuánto se haya estirado la caja.
 *
 * El color no se elige acá: lo pone quien lo usa, porque estos dibujos van
 * sobre las tarjetas de color entero y tienen que salir del color del texto de
 * ESA tarjeta. `currentColor` los hereda solo.
 */

/** Alto del sistema de coordenadas interno. El ancho siempre es 100. */
const ALTO = 32;

/**
 * Convierte la serie a coordenadas.
 *
 * El máximo se fuerza a 1 como mínimo para que una serie de puros ceros —un
 * feriado, una ventanilla recién instalada— no divida por cero: sale una línea
 * apoyada en el piso, que es exactamente lo que pasó.
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
 *
 * Se usa para los conteos: «entraron 3 trámites» es una cantidad discreta, y
 * una línea que sube y baja entre enteros sugiere una continuidad que no
 * existe —no hubo medio trámite a las once de la mañana—.
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

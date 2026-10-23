import { useEffect, useState } from 'react';

/** Si el equipo pide reducir movimiento: las animaciones se saltan y se muestra el estado final. */
export function useReducirMovimiento(): boolean {
    const [reducir] = useState(
        () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    );

    return reducir;
}

/** Falso en el primer cuadro y verdadero en el siguiente: lo que cambia de valor con él se anima con CSS. */
export function useMontado(): boolean {
    const [montado, setMontado] = useState(false);

    useEffect(() => {
        const cuadro = requestAnimationFrame(() => setMontado(true));

        return () => cancelAnimationFrame(cuadro);
    }, []);

    return montado;
}

/** Un número que sube de 0 a `valor` con freno al final. */
export function useConteo(valor: number, duracion = 900): number {
    const reducir = useReducirMovimiento();
    const [actual, setActual] = useState(reducir ? valor : 0);

    useEffect(() => {
        if (reducir) {
            setActual(valor);

            return;
        }

        let cuadro = 0;
        const inicio = performance.now();
        const paso = (ahora: number) => {
            const t = Math.min(1, (ahora - inicio) / duracion);
            setActual(valor * (1 - Math.pow(1 - t, 3)));
            if (t < 1) cuadro = requestAnimationFrame(paso);
        };
        cuadro = requestAnimationFrame(paso);

        return () => cancelAnimationFrame(cuadro);
    }, [valor, duracion, reducir]);

    return actual;
}

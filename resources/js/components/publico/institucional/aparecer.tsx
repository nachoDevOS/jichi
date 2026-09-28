import { useEffect, useRef, useState, type PropsWithChildren } from 'react';

/**
 * Muestra su contenido con un deslizamiento suave al entrar en pantalla.
 * Sin IntersectionObserver se muestra de una: el efecto nunca esconde texto.
 */
export function Aparecer({
    retraso = 0,
    className = '',
    children,
}: PropsWithChildren<{ retraso?: number; className?: string }>) {
    const ref = useRef<HTMLDivElement>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const nodo = ref.current;

        if (!nodo || typeof IntersectionObserver === 'undefined') {
            setVisible(true);

            return;
        }

        const observador = new IntersectionObserver(
            ([entrada]) => {
                if (entrada.isIntersecting) {
                    setVisible(true);
                    observador.disconnect();
                }
            },
            { rootMargin: '0px 0px -10% 0px' },
        );

        observador.observe(nodo);

        return () => observador.disconnect();
    }, []);

    return (
        <div
            ref={ref}
            className={`aparecer ${visible ? 'visible' : ''} ${className}`}
            style={{ transitionDelay: `${retraso}ms` }}
        >
            {children}
        </div>
    );
}

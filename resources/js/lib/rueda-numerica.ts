/**
 * Evita que la rueda del mouse cambie el valor de un <input type="number">.
 * El navegador solo lo cambia si el campo tiene el foco: se lo quita y la
 * rueda sigue desplazando la página, sin tocar el número.
 */
export function bloquearRuedaEnNumericos() {
    document.addEventListener(
        'wheel',
        (evento) => {
            const campo = evento.target;

            if (campo instanceof HTMLInputElement && campo.type === 'number' && campo === document.activeElement) {
                campo.blur();
            }
        },
        { passive: true },
    );
}

import { useCallback, useEffect, useState } from 'react';

export type Apariencia = 'light' | 'dark' | 'system';

const COOKIE = 'apariencia';
const consultaOscuro = () => window.matchMedia('(prefers-color-scheme: dark)');

function aplicar(apariencia: Apariencia) {
    const oscuro = apariencia === 'dark' || (apariencia === 'system' && consultaOscuro().matches);

    document.documentElement.classList.toggle('dark', oscuro);
}

function leerCookie(): Apariencia | null {
    const valor = document.cookie.match(new RegExp(`(?:^|;\\s*)${COOKIE}=([^;]+)`))?.[1];

    return valor === 'light' || valor === 'dark' || valor === 'system' ? valor : null;
}

function guardarCookie(apariencia: Apariencia) {
    // La cookie también la lee Blade para pintar el HTML sin parpadeo.
    document.cookie = `${COOKIE}=${apariencia};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`;
    localStorage.setItem(COOKIE, apariencia);
}

/**
 * Se llama una sola vez al arrancar la app, antes de montar React.
 */
export function inicializarApariencia() {
    const guardada = (leerCookie() ?? localStorage.getItem(COOKIE) ?? 'system') as Apariencia;

    aplicar(guardada);

    // Si el usuario sigue al sistema, hay que reaccionar cuando el SO cambia.
    consultaOscuro().addEventListener('change', () => {
        if ((leerCookie() ?? 'system') === 'system') {
            aplicar('system');
        }
    });
}

export function useApariencia() {
    const [apariencia, setApariencia] = useState<Apariencia>('system');

    useEffect(() => {
        setApariencia((leerCookie() ?? localStorage.getItem(COOKIE) ?? 'system') as Apariencia);
    }, []);

    const cambiar = useCallback((valor: Apariencia) => {
        setApariencia(valor);
        guardarCookie(valor);
        aplicar(valor);
    }, []);

    return { apariencia, cambiar } as const;
}

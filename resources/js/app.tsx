import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { inicializarApariencia } from '@/hooks/use-apariencia';

/**
 *  PUNTO DE ENTRADA DE TODO EL FRONTEND
 */

const nombreApp = import.meta.env.VITE_APP_NAME || 'Jichi';

/*
 * import.meta.glob es de Vite: registra de golpe TODOS los archivos .tsx que
 * estén dentro de pages/, incluidas las subcarpetas (por el **).
 */
const paginas = import.meta.glob<{ default: ComponentType<any> }>('./pages/**/*.tsx');

createInertiaApp({
    // Título de la pestaña: "Beneficiarios — Jichi".
    title: (titulo) => (titulo ? `${titulo} — ${nombreApp}` : nombreApp),

    // Traduce el nombre que mandó PHP al archivo que le corresponde.
    // resolvePageComponent devuelve el módulo completo; Inertia espera el
    // componente, por eso se toma su `.default`.
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, paginas).then((m) => m.default),

    // Monta React dentro del <div id="app"> que dejó el Blade.
    setup({ el, App, props }) {
        createRoot(el!).render(<App {...props} />);
    },

    // Barra de progreso dorada en la parte de arriba al navegar.
    // delay: 150 evita que parpadee en las respuestas rápidas.
    progress: {
        color: '#c9a84c',
        delay: 150,
    },
});

// Aplica el tema claro/oscuro guardado antes de que se vea la pantalla.
inicializarApariencia();

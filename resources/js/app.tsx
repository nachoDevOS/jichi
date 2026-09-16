import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { inicializarApariencia } from '@/hooks/use-apariencia';

/**
 * ============================================================================
 *  PUNTO DE ENTRADA DE TODO EL FRONTEND
 * ============================================================================
 *
 * Este es el primer archivo de JavaScript que corre en el navegador. Es corto
 * a propósito: solo arranca Inertia y le explica dónde buscar las pantallas.
 *
 * EL RECORRIDO COMPLETO, DE PRINCIPIO A FIN
 *
 *   1. El navegador pide /panel/beneficiarios
 *   2. Laravel atiende con BeneficiarioController@index
 *   3. El controlador devuelve Inertia::render('panel/beneficiarios/index', [...])
 *   4. Laravel pinta resources/views/app.blade.php, que carga ESTE archivo
 *   5. Inertia lee el nombre 'panel/beneficiarios/index' y llama a resolve()
 *   6. resolve() carga resources/js/pages/panel/beneficiarios/index.tsx
 *   7. React lo dibuja dentro del <div id="app"> del Blade
 *
 * A partir de ahí, al navegar dentro del sistema los pasos 4 a 7 se repiten
 * SIN recargar el navegador: Inertia pide solo los datos nuevos y cambia el
 * componente. Por eso se siente rápido aunque las rutas sean de Laravel.
 */

const nombreApp = import.meta.env.VITE_APP_NAME || 'Jichi';

/*
 * import.meta.glob es de Vite: registra de golpe TODOS los archivos .tsx que
 * estén dentro de pages/, incluidas las subcarpetas (por el **).
 *
 * No los carga todos al arrancar: deja preparada una función por archivo y
 * descarga cada pantalla recién cuando se visita. Eso se llama "code
 * splitting" y es lo que evita que el primer ingreso al sistema tenga que
 * bajar el código de los seis módulos de una vez.
 *
 * Consecuencia práctica: cada archivo nuevo en pages/ queda disponible solo,
 * sin registrarlo en ninguna lista.
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

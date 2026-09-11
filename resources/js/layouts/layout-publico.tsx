import type { PropsWithChildren } from 'react';

/**
 * ============================================================================
 *  EL MARCO DE LAS PANTALLAS PÚBLICAS
 * ============================================================================
 *
 * Hay DOS layouts en el sistema y no se mezclan nunca:
 *
 *   layout-panel.tsx    -> el panel de administración. Barra lateral con el
 *                          menú, avisos flotantes, datos del funcionario.
 *                          Sirve a quien tiene sesión iniciada.
 *
 *   layout-publico.tsx  -> ESTE. Lo ve cualquier ciudadano desde la calle, sin
 *                          login. Sin menú, sin nombres de usuario, sin nada
 *                          interno: solo la marca de la institución.
 *
 * Por qué separarlos: la pantalla pública se abre escaneando un QR desde un
 * teléfono, muchas veces con mala señal. Tiene que cargar poco y no mostrar ni
 * una pista de la estructura interna del sistema.
 *
 * ----------------------------------------------------------------------------
 *  QUÉ HACE ESTE MARCO Y QUÉ NO
 * ----------------------------------------------------------------------------
 *
 * Es el ESCRITORIO sobre el que se apoya la hoja, y nada más: el fondo verde
 * oscuro, la franja tricolor arriba y abajo, y la marca de agua del escudo.
 *
 * El membrete —escudo, nombre de la institución, pie legal— NO está acá: va
 * dentro de la hoja, en `components/publico/hoja-oficial.tsx`. Es a propósito.
 * Un membrete flotando sobre el fondo pertenece a una página web; impreso
 * sobre el papel pertenece a un documento, que es justo lo que esta pantalla
 * tiene que parecer.
 *
 * El fondo es oscuro porque su único trabajo es hacer resaltar el papel
 * blanco. Al imprimir desaparece (ver los estilos `@media print` de app.css,
 * que enganchan con la clase `fondo-escritorio`).
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ESTE MARCO NO SIGUE EL TEMA CLARO/OSCURO
 * ----------------------------------------------------------------------------
 *
 * El resto del sistema cambia con el tema del dispositivo. Acá no, y es a
 * propósito: esta pantalla es un ACTO DE VERIFICACIÓN OFICIAL. El inspector la
 * abre delante del pescador y los dos miran el teléfono. Que se vea siempre
 * igual —hoja blanca, escudo, franja tricolor— es parte de lo que la hace
 * creíble. Una pantalla que a veces es blanca y a veces negra parece una
 * página cualquiera, no el respaldo de la Gobernación.
 */
export default function LayoutPublico({ children }: PropsWithChildren) {
    return (
        <div
            className="fondo-escritorio relative flex min-h-screen flex-col overflow-x-hidden"
            style={{
                background:
                    'radial-gradient(900px 480px at 50% 0%, rgba(255,255,255,.10) 0%, transparent 62%),' +
                    'linear-gradient(165deg, #0b5e2c 0%, #07401e 55%, #042a13 100%)',
            }}
        >
            {/* Marca de agua del escudo, fija y muy tenue. */}
            <img
                src="/image/icon.png"
                alt=""
                aria-hidden
                className="solo-pantalla pointer-events-none fixed top-1/2 left-1/2 w-[34rem] max-w-[130%] -translate-x-1/2 -translate-y-1/2 opacity-[0.05] select-none"
            />

            {/* Franja tricolor de Bolivia. Va arriba y abajo, como en los
                documentos oficiales impresos. */}
            <FranjaTricolor />

            <main className="relative mx-auto w-full max-w-xl flex-1 px-4 py-7 sm:px-6 sm:py-10">
                {children}
            </main>

            <FranjaTricolor className="sticky bottom-0" />
        </div>
    );
}

function FranjaTricolor({ className = '' }: { className?: string }) {
    return (
        <div
            className={`solo-pantalla relative z-10 flex h-1.5 w-full shrink-0 ${className}`}
            aria-hidden
        >
            <i className="flex-1 bg-[#d52b1e]" />
            <i className="flex-1 bg-[#f4c500]" />
            <i className="flex-1 bg-[#0c6b32]" />
        </div>
    );
}

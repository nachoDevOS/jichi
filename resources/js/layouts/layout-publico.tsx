import type { PropsWithChildren } from 'react';

/**
 *  EL MARCO DE LAS PANTALLAS PÚBLICAS
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

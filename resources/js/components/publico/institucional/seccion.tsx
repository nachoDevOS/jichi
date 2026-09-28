import type { PropsWithChildren } from 'react';

/**
 * El envoltorio de cada bloque de la portada: el ancho, el aire y el par
 * título/bajada. Escrito una vez para que las seis secciones respiren igual.
 */
export function Seccion({
    id,
    titulo,
    bajada,
    oscuro = false,
    children,
}: PropsWithChildren<{
    id: string;
    titulo: string;
    bajada?: string;
    /** Fondo gris claro, para alternar y que los bloques se distingan. */
    oscuro?: boolean;
}>) {
    return (
        // scroll-mt: la cabecera es sticky y sin esto el ancla deja el título
        // escondido debajo de ella.
        <section
            id={id}
            className={`scroll-mt-24 px-4 py-14 sm:px-6 sm:py-18 ${oscuro ? 'bg-rio-espuma' : 'bg-white'}`}
        >
            <div className="mx-auto max-w-6xl">
                <h2 className="text-center text-3xl font-extrabold tracking-tight text-rio-profundo sm:text-4xl">
                    {titulo}
                </h2>

                <i className="mx-auto mt-4 block h-1.5 w-20 rounded-full bg-linear-to-r from-rio-claro to-institucional-dorado" />

                {bajada && (
                    <p className="mx-auto mt-4 max-w-2xl text-center text-sm leading-relaxed text-slate-600 sm:text-base">
                        {bajada}
                    </p>
                )}

                <div className="mt-10">{children}</div>
            </div>
        </section>
    );
}

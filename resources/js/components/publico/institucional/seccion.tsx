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
            className={`scroll-mt-24 px-4 py-14 sm:px-6 sm:py-18 ${oscuro ? 'bg-slate-50' : 'bg-white'}`}
        >
            <div className="mx-auto max-w-6xl">
                <h2 className="text-center text-2xl font-bold text-institucional-azul sm:text-3xl">
                    {titulo}
                </h2>

                <i className="mx-auto mt-3 block h-1 w-16 rounded-full bg-institucional-dorado" />

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

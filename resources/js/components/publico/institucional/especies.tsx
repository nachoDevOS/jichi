import { Pez } from '@/components/publico/institucional/ilustraciones';

/**
 * Franja con las especies del catálogo de productos, que desfilan como el río.
 * La lista va dos veces para que el bucle no tenga salto; la copia no se lee en voz alta.
 */
export function Especies({ especies }: { especies: string[] }) {
    if (especies.length === 0) {
        return null;
    }

    const fila = (copia: boolean) => (
        <ul className="flex shrink-0 items-center" aria-hidden={copia || undefined}>
            {especies.map((e) => (
                <li
                    key={e}
                    className="flex items-center gap-3 px-5 text-lg font-bold whitespace-nowrap text-white/90 sm:text-xl"
                >
                    <Pez className="w-7 text-institucional-dorado" />
                    {e}
                </li>
            ))}
        </ul>
    );

    return (
        <section
            className="overflow-hidden bg-rio-profundo py-6"
            aria-label="Especies de nuestros ríos"
        >
            <p className="mb-4 text-center text-xs font-semibold tracking-[0.25em] text-rio-claro uppercase">
                Especies de nuestros ríos
            </p>
            <div className="animar-desfile flex w-max">
                {fila(false)}
                {fila(true)}
            </div>
        </section>
    );
}

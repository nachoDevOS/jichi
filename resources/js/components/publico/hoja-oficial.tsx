import type { PropsWithChildren, ReactNode } from 'react';
import type { InstitucionPublica } from '@/types/publico';

/**
 *  LA HOJA
 */
export function HojaOficial({
    institucion,
    children,
    /** Pie propio del contenido, si lo necesita. */
    pie,
    /**
     * ¿La hoja está dejando constancia de algo, o es solo el formulario para
     * escribir el código?
     */
    esConstancia = true,
}: PropsWithChildren<{
    institucion: InstitucionPublica;
    pie?: ReactNode;
    esConstancia?: boolean;
}>) {
    return (
        <article
            /*
             * `papel-acta` no pinta nada en pantalla: es el gancho que usan
             * los estilos de impresión de app.css para soltar el ancho del
             * teléfono y quitar la sombra. Se deja acá, junto al resto del
             * papel, para que quien toque este componente lo vea.
             */
            className="papel-acta animar-entrada relative overflow-hidden bg-white shadow-[0_25px_60px_-15px_rgba(0,0,0,0.6)]"
        >
            {/* Guarda del borde: dos líneas finas, como la orla de un
                certificado. Va por dentro del papel, no pegada al canto. */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-2.5 border border-emerald-900/25"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute inset-[13px] border border-emerald-900/15"
            />

            <div className="relative px-6 py-7 sm:px-9">
                <Membrete institucion={institucion} />

                {children}

                <footer className="mt-7 border-t border-dashed border-slate-300 pt-3.5">
                    {pie}

                    {esConstancia && (
                        <p className="mt-2 text-center text-[10px] leading-relaxed text-slate-500">
                            Esta constancia acredita la consulta al sistema de la Gobernación y{' '}
                            <b className="font-semibold text-slate-600">
                                no reemplaza al documento
                            </b>{' '}
                            original ni a su presentación física cuando sea exigida.
                        </p>
                    )}

                    {institucion.pie_legal && (
                        <p className="mt-1.5 text-center text-[10px] leading-relaxed text-slate-400">
                            {institucion.pie_legal}
                        </p>
                    )}
                </footer>
            </div>
        </article>
    );
}

/**
 * El membrete: escudo arriba y el nombre de la institución en tres renglones,
 * del más general al más específico, como en el papel membretado real.
 */
function Membrete({ institucion }: { institucion: InstitucionPublica }) {
    return (
        <header className="text-center">
            <img
                src="/image/icon.png"
                alt=""
                aria-hidden
                className="mx-auto h-16 w-auto object-contain"
            />

            <p className="mt-3 font-serif text-[11px] tracking-[0.16em] text-slate-500 uppercase">
                Estado Plurinacional de Bolivia
            </p>

            <h2 className="mt-1 font-serif text-[15px] leading-tight font-bold tracking-wide text-emerald-900 uppercase">
                {institucion.municipio ?? 'Gobierno Autónomo Departamental del Beni'}
            </h2>

            <p className="mt-1 text-[10px] tracking-[0.2em] text-slate-400 uppercase">
                {institucion.sistema}
            </p>

            {/* Filete del membrete: grueso en el medio, fino a los costados. Es
                el remate clásico de una hoja oficial y separa el encabezado del
                cuerpo sin necesidad de una caja. */}
            <div aria-hidden className="mx-auto mt-4 flex items-center gap-1.5">
                <i className="h-px w-14 bg-emerald-900/30" />
                <i className="size-1 rotate-45 bg-emerald-900/40" />
                <i className="h-[2px] w-24 bg-emerald-900/50" />
                <i className="size-1 rotate-45 bg-emerald-900/40" />
                <i className="h-px w-14 bg-emerald-900/30" />
            </div>
        </header>
    );
}

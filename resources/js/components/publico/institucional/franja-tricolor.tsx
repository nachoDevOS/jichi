/**
 * La franja roja-amarilla-verde que llevan los documentos y los sitios
 * oficiales. Va arriba de la cabecera y abajo del pie.
 */
export function FranjaTricolor({ className = '' }: { className?: string }) {
    return (
        <div className={`flex h-1.5 w-full shrink-0 ${className}`} aria-hidden>
            <i className="flex-1 bg-[#d52b1e]" />
            <i className="flex-1 bg-[#f4c500]" />
            <i className="flex-1 bg-[#0c6b32]" />
        </div>
    );
}

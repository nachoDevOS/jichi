import { cn } from '@/lib/utils';

/**
 * Escudo del Gobierno Autónomo Departamental del Beni.
 *
 * Es el emblema institucional oficial, no una marca inventada para el sistema:
 * el mismo que va impreso en la cédula de pescador y en los talonarios.
 *
 * OJO CON EL FONDO. El archivo trae el texto «GOBIERNO AUTÓNOMO DEPARTAMENTAL
 * DEL BENI» en verde oscuro debajo del escudo. Sobre la barra lateral azul ese
 * texto desaparece, por eso MarcaJichi lo apoya sobre un recuadro blanco. Si
 * alguna vez hace falta el escudo suelto —sin la leyenda— conviene recortar una
 * segunda versión del PNG en vez de escalar esta, que a tamaño chico se vuelve
 * una mancha verde.
 */
export function LogoJichi({ className }: { className?: string }) {
    return (
        <img
            src="/image/icon.png"
            alt="Gobierno Autónomo Departamental del Beni"
            className={cn('size-8 object-contain', className)}
        />
    );
}

export function MarcaJichi({
    sigla,
    className,
}: {
    sigla?: string | null;
    className?: string;
}) {
    return (
        <div className={cn('flex items-center gap-3', className)}>
            {/* El recuadro blanco es lo que hace legible al escudo sobre el
                azul de la barra lateral. */}
            <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-white p-1">
                <LogoJichi className="size-full" />
            </span>

            <div className="leading-tight">
                <p className="text-base font-bold tracking-tight">Jichi</p>
                <p className="text-[11px] tracking-wide uppercase opacity-70">
                    {sigla ?? 'GAD-BENI'}
                </p>
            </div>
        </div>
    );
}

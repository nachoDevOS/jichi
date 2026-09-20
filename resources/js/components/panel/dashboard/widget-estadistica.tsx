import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 *  UNO DE LOS CUATRO NÚMEROS DE ARRIBA DEL TABLERO
 */

/**
 * Qué color de los cuatro usa la tarjeta.
 */
export type TonoWidget = 1 | 2 | 3 | 4;

/**
 * Las clases van ESCRITAS ENTERAS, no armadas con `bg-widget-${tono}`.
 */
const TONOS: Record<TonoWidget, string> = {
    1: 'bg-widget-1 text-widget-1-fg',
    2: 'bg-widget-2 text-widget-2-fg',
    3: 'bg-widget-3 text-widget-3-fg',
    4: 'bg-widget-4 text-widget-4-fg',
};

export function WidgetEstadistica({
    tono,
    icono: Icono,
    etiqueta,
    valor,
    pie,
    children,
}: {
    tono: TonoWidget;
    /*
     * El icono se recibe como componente, no como texto. Por eso se renombra
     * a `Icono` con mayúscula al desestructurarlo: React solo trata como
     * componente lo que empieza con mayúscula. En minúscula, <icono /> se
     * interpretaría como una etiqueta HTML llamada "icono" y no se vería nada.
     */
    icono: LucideIcon;
    etiqueta: string;
    /** Ya viene formateado como texto ("1.250,50 Bs"), no como número. */
    valor: string;
    /** Línea chica de contexto debajo del número. */
    pie?: string;
    /** La franja de abajo: una línea chica, un desglose, o nada. */
    children?: ReactNode;
}) {
    return (
        <div className={cn('flex flex-col overflow-hidden rounded-xl shadow-sm', TONOS[tono])}>
            <div className="flex items-start gap-3 p-5 pb-4">
                <div className="min-w-0 flex-1">
                    {/* tabular-nums hace que todos los dígitos ocupen lo mismo:
                        los montos quedan alineados y no bailan al actualizarse. */}
                    <p className="truncate text-3xl font-semibold tabular-nums">{valor}</p>

                    <p className="mt-0.5 truncate text-xs font-medium tracking-wide uppercase opacity-80">
                        {etiqueta}
                    </p>

                    {pie && <p className="mt-1.5 truncate text-xs opacity-75">{pie}</p>}
                </div>

                {/* El icono va apagado a propósito: es una ayuda para reconocer
                    la tarjeta de un vistazo, no un dato. Si compitiera con el
                    número, el número dejaría de ser lo primero que se lee. */}
                <Icono className="size-5 shrink-0 opacity-50" />
            </div>

            {/*
                mt-auto empuja la franja al piso de la tarjeta. Sin eso, en una
                fila donde una tarjeta es más alta que las otras —un pie de dos
                renglones— las franjas quedan a distinta altura y la fila se ve
                desprolija.
            */}
            {children && <div className="mt-auto bg-black/15 px-4 pt-3 pb-3">{children}</div>}
        </div>
    );
}

/**
 * La franja de abajo cuando el número se puede PARTIR en pedazos.
 */
export function DesglosePie({ partes }: { partes: { etiqueta: string; cantidad: number }[] }) {
    const total = partes.reduce((suma, p) => suma + p.cantidad, 0);

    return (
        <div className="space-y-2">
            <div className="flex h-1.5 overflow-hidden rounded-full bg-current/20">
                {partes.map((parte, i) => (
                    <div
                        key={parte.etiqueta}
                        // bg-current toma el color del texto de la tarjeta, así
                        // que los pedazos se distinguen por opacidad y esto
                        // funciona sobre los cuatro tonos sin tocar nada.
                        className={cn('h-full', i === 0 ? 'bg-current' : 'bg-current/45')}
                        style={{ width: total > 0 ? `${(parte.cantidad / total) * 100}%` : '0%' }}
                    />
                ))}
            </div>

            <div className="flex flex-wrap gap-x-4 gap-y-1 text-[11px] opacity-85">
                {partes.map((parte) => (
                    <span key={parte.etiqueta} className="tabular-nums">
                        <b className="font-semibold">{parte.cantidad}</b> {parte.etiqueta}
                    </span>
                ))}
            </div>
        </div>
    );
}

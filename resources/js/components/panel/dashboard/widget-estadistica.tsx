import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * ============================================================================
 *  UNO DE LOS CUATRO NÚMEROS DE ARRIBA DEL TABLERO
 * ============================================================================
 *
 * Es el mismo componente repetido cuatro veces con datos distintos. Ese es el
 * sentido de un componente: se escribe el diseño UNA vez y se reutiliza
 * cambiándole las props.
 *
 * VA PINTADO DE COLOR ENTERO y no como tarjeta blanca con un icono de color. La
 * diferencia no es estética: estos cuatro son el resumen del día y compiten por
 * la atención con dos gráficos, una tabla y un aviso de vencimiento. Siendo
 * blancos, pesan lo mismo que todo lo demás y hay que buscarlos; pintados, el
 * ojo cae ahí primero y recién después recorre el resto.
 *
 * El pie es una franja translúcida que se apoya en el borde de abajo, y ahí va
 * el contexto: la línea de los últimos catorce días, o el desglose del número.
 * Puede no haber ninguno —no todo número tiene una serie detrás— y entonces la
 * tarjeta termina en la cifra.
 */

/**
 * Qué color de los cuatro usa la tarjeta.
 *
 * Es un número y no un nombre de color a propósito: los tonos están definidos
 * en app.css como una escala institucional, y nombrarlos «azul» o «dorado» acá
 * ataría el tablero a un color concreto. El día que la paleta cambie se toca el
 * CSS y este archivo no se entera.
 */
export type TonoWidget = 1 | 2 | 3 | 4;

/**
 * Las clases van ESCRITAS ENTERAS, no armadas con `bg-widget-${tono}`.
 *
 * Es la trampa clásica de Tailwind, la misma que está documentada en
 * components/ui/badge.tsx: solo llegan a la hoja de estilos final las clases
 * que Tailwind puede leer literalmente en el código. Armada juntando textos, la
 * clase no existe, la tarjeta sale transparente y no hay ningún error que lo
 * explique.
 *
 * Fondo y color de texto van SIEMPRE juntos: el dorado necesita texto oscuro y
 * los tres azules texto claro (ver el comentario de los tokens en app.css), así
 * que separarlos permitiría combinar un par ilegible.
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
 *
 * «12 trámites sin resolver» no dice si son doce recién llegados o doce trabados
 * en revisión desde hace una semana, y esas dos situaciones piden cosas
 * distintas. La barra parte el número en sus pedazos reales y los rotula.
 *
 * LOS PEDAZOS TIENEN QUE SER EXCLUYENTES entre sí: la barra los dibuja uno al
 * lado del otro sumando el total, así que dos categorías que se pisan —un mismo
 * expediente contado en las dos— dibujan una barra que miente sobre el total.
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

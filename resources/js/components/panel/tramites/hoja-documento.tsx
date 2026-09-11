import type { ReactNode } from 'react';
import { CodigoQr } from '@/components/comunes/codigo-qr';
import { cn } from '@/lib/utils';
import type { MembreteTipoTramite } from '@/types/tramites';

/**
 * Código de muestra que llevan las vistas previas.
 *
 * En un documento real esto lo genera el sistema al emitirlo y queda guardado
 * en `documentos.codigo_verificacion`. Acá es fijo porque el módulo todavía no
 * guarda nada: sirve para ver dónde cae el QR y para probar que el escaneo
 * lleva a la pantalla pública.
 */
export const CODIGO_MUESTRA = 'AJGDP5SS2MAF';

/**
 * ============================================================================
 *  LA HOJA: simulación del formulario en papel
 * ============================================================================
 *
 * Envuelve las vistas previas de los documentos para que se vean como el
 * talonario del SEDAG: papel claro, tinta verde, tipografía condensada.
 *
 * ¿POR QUÉ NO SE ADAPTA AL MODO OSCURO?
 *
 * A propósito. Todo el resto del sistema cambia con el tema, pero esto no es
 * una pantalla: es la representación de una hoja impresa. En modo oscuro un
 * papel blanco tiene que seguir siendo blanco, o el operador no puede comparar
 * lo que ve con el talonario que tiene en la mano. Por eso los colores están
 * escritos fijos y no salen de las variables del tema.
 */
export function HojaDocumento({
    titulo,
    membrete,
    numero,
    children,
    pie,
}: {
    titulo: string;
    membrete: MembreteTipoTramite;
    /** El correlativo preimpreso, en rojo, arriba a la derecha. */
    numero: string;
    children: ReactNode;
    pie?: ReactNode;
}) {
    return (
        <div className="overflow-x-auto rounded-lg border border-border bg-[#fdfdf7] p-5 text-[#1f5c3d] shadow-sm sm:p-7">
            <div className="relative min-w-[22rem]">
                {/*
                    Marca de agua: el sello del SEDAG, tenue y centrado, igual
                    que en el talonario impreso.

                    Va DETRÁS del contenido —absolute, sin ocupar espacio en el
                    flujo— para no empujar ningún renglón. `pointer-events-none`
                    evita que se pueda arrastrar como imagen suelta, y
                    `select-none` que se seleccione al copiar el texto del
                    documento.
                */}
                <img
                    src="/image/sedag.png"
                    alt=""
                    aria-hidden
                    className="pointer-events-none absolute top-1/2 left-1/2 w-[62%] max-w-[20rem] -translate-x-1/2 -translate-y-1/2 opacity-10 select-none"
                />

                {/* relative para que el contenido quede por encima del sello. */}
                <div className="relative">
                    <Membrete membrete={membrete} numero={numero} />

                    <h2 className="mt-3 mb-4 text-center text-lg font-bold tracking-tight text-[#1a7a45] sm:text-xl">
                        {titulo}
                    </h2>

                    {children}

                    {pie && <div className="mt-5 border-t border-[#1f5c3d]/25 pt-3">{pie}</div>}
                </div>
            </div>
        </div>
    );
}

function Membrete({ membrete, numero }: { membrete: MembreteTipoTramite; numero: string }) {
    /*
     * Escudo del GAD a la izquierda, leyenda al centro y sello del SEDAG a la
     * derecha, como están impresos en los talonarios.
     *
     * El correlativo NO va posicionado en absoluto sobre la esquina, aunque en
     * el papel esté ahí: al agregarse el sello del SEDAG a la derecha, el
     * número en rojo le quedaba encima y no se leía ninguno de los dos. Va
     * apilado sobre el sello, dentro del flujo, que se lee igual y no puede
     * chocar con nada.
     */
    return (
        <div className="flex items-start gap-3">
            <img
                src="/image/icon.png"
                alt=""
                aria-hidden
                className="h-12 w-auto shrink-0 object-contain"
            />

            <div className="flex-1 text-center text-[10px] leading-tight font-semibold tracking-tight uppercase sm:text-[11px]">
                <p>{membrete.institucion}</p>
                <p>{membrete.secretaria}</p>
                {/* La cédula de pescador no lleva línea de programa. */}
                {membrete.programa && <p>{membrete.programa}</p>}
                <p className="mt-0.5">{membrete.unidad}</p>
            </div>

            <div className="flex shrink-0 flex-col items-center gap-1">
                {/* El correlativo va impreso en rojo en el talonario real. */}
                <span className="font-mono text-sm font-bold whitespace-nowrap text-[#c2352b]">
                    N° {numero}
                </span>

                <img
                    src="/image/sedag.png"
                    alt=""
                    aria-hidden
                    className="h-11 w-auto object-contain"
                />
            </div>
        </div>
    );
}

/**
 * Un renglón con línea de puntos, como los del talonario.
 *
 * Si el campo está vacío muestra la línea sola: así la vista previa se parece
 * al formulario en blanco y se entiende qué falta por llenar.
 */
export function Renglon({
    etiqueta,
    valor,
    className,
}: {
    etiqueta: string;
    valor?: string | null;
    className?: string;
}) {
    return (
        <p className={cn('flex items-baseline gap-2 text-[13px]', className)}>
            <span className="shrink-0">{etiqueta}</span>
            <span className="min-w-0 flex-1 border-b border-dotted border-[#1f5c3d]/50 pb-0.5 font-medium">
                {valor || ' '}
            </span>
        </p>
    );
}

/**
 * El QR de verificación con su leyenda, tal como va impreso.
 *
 * La leyenda importa tanto como el código: un QR suelto no le dice a nadie
 * para qué sirve. El inspector tiene que entender, sin preguntar, que
 * escaneándolo comprueba si el documento es auténtico.
 */
export function BloqueQr({ codigo }: { codigo: string }) {
    return (
        <div className="flex shrink-0 flex-col items-center gap-1">
            <CodigoQr codigo={codigo} tamano={76} />

            <p className="max-w-[7rem] text-center text-[8px] leading-tight font-semibold">
                Escanee para verificar la autenticidad
            </p>

            <p className="font-mono text-[8px] tracking-wider">{codigo}</p>
        </div>
    );
}

/** Título de sección: "A.- INTERESADO". */
export function SeccionHoja({ titulo, children }: { titulo: string; children: ReactNode }) {
    return (
        <section className="mt-4 first:mt-0">
            <p className="mb-1.5 text-[11px] font-bold tracking-wide uppercase">{titulo}</p>
            {children}
        </section>
    );
}

import { Link } from '@inertiajs/react';
import { BadgePlus, IdCard, ShieldOff } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { fecha } from '@/lib/utils';
import type { SituacionBeneficiario } from '@/types/beneficiarios';

/**
 * ============================================================================
 *  QUÉ TIENE ESTA PERSONA, ANTES DE CARGAR NADA
 * ============================================================================
 *
 * Se dibuja apenas el operador elige al beneficiario y responde lo que hasta
 * ahora solo se sabía después de guardar: qué actividades ya tiene cubiertas
 * este año, cuáles siguen valiendo y cuáles están cortadas.
 *
 * ----------------------------------------------------------------------------
 *  LO QUE ESTA TARJETA YA NO PUEDE DECIR
 * ----------------------------------------------------------------------------
 *
 * El tipo de trámite. Antes lo anunciaba —«le corresponde una adición de
 * rubro»— porque con un carnet por persona la respuesta dependía solo de quién
 * era. Hoy depende del RUBRO: la misma persona hace una emisión inicial si pide
 * Comercializador y una actualización si pide Pescador, y el rubro se elige en
 * el paso siguiente del formulario.
 *
 * Así que acá se muestra el INVENTARIO, y el paso del rubro saca la conclusión.
 * Forzar un cartel de tipo en este punto obligaría a inventar una respuesta
 * para una pregunta que todavía no está hecha.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ IMPORTA MOSTRARLO ACÁ Y NO DEJAR QUE FALLE AL GUARDAR
 * ----------------------------------------------------------------------------
 *
 * El servidor ya rechaza un rubro repetido: `SolicitudCarnetService` lo
 * comprueba y el índice único (beneficiario, rubro, gestión) lo garantiza. Pero
 * rechazarlo al final significa que el operador ya escaneó dos papeles, los
 * adjuntó y cargó una boleta —y al volver con el error los archivos NO se
 * recuperan, porque los navegadores no permiten rellenar un campo de tipo file—.
 *
 * Mostrarlo antes no es una comodidad: es la diferencia entre un aviso y un
 * trabajo perdido.
 */
export function SituacionBeneficiarioCard({ situacion }: { situacion: SituacionBeneficiario }) {
    // --- Sin ningún carnet del año: todo lo que pida es emisión inicial.
    if (situacion.carnets.length === 0) {
        return (
            <div className="rounded-lg border border-sky-300 bg-sky-50 p-4 dark:border-sky-500/40 dark:bg-sky-500/10">
                <div className="flex items-start gap-3">
                    <BadgePlus className="mt-0.5 size-5 shrink-0 text-sky-700 dark:text-sky-300" />

                    <div className="min-w-0 text-sm">
                        <p className="font-medium text-sky-900 dark:text-sky-200">
                            Sin carnets de la gestión {situacion.gestion}
                        </p>
                        <p className="text-sky-800/80 dark:text-sky-200/80">
                            Cualquier rubro que elija será una <strong>emisión inicial</strong>: al
                            registrar la solicitud se crea el carnet de esa actividad y queda a la
                            espera de aprobación.
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="rounded-lg border border-violet-300 bg-violet-50 p-4 dark:border-violet-500/40 dark:bg-violet-500/10">
            <div className="flex items-start gap-3">
                <IdCard className="mt-0.5 size-5 shrink-0 text-violet-700 dark:text-violet-300" />

                <div className="min-w-0 flex-1 space-y-3 text-sm">
                    <div>
                        <p className="font-medium text-violet-900 dark:text-violet-200">
                            Ya tiene {situacion.carnets.length} carnet(s) de la gestión{' '}
                            {situacion.gestion}
                        </p>

                        <p className="text-violet-800/80 dark:text-violet-200/80">
                            Esas actividades no se pueden volver a pedir este año. Cualquier otro
                            rubro emite un carnet nuevo, con su propio plástico.
                        </p>
                    </div>

                    <ul className="space-y-2">
                        {situacion.carnets.map((carnet) => (
                            <li
                                key={carnet.id}
                                className="flex flex-wrap items-center gap-x-2 gap-y-1"
                            >
                                <Badge color={carnet.estado_color}>
                                    {/*
                                        El icono del candado solo en los cortados.
                                        Es redundante con el color a propósito: el
                                        color solo no alcanza para quien no
                                        distingue el ámbar del verde, y esto se mira
                                        en una pantalla de ventanilla con el brillo
                                        bajo.
                                    */}
                                    {carnet.estado !== 'vigente' && (
                                        <ShieldOff className="mr-1 size-3" aria-hidden />
                                    )}
                                    {carnet.rubro}
                                    <span className="ml-1 opacity-70">
                                        · {carnet.estado_etiqueta.toLowerCase()}
                                    </span>
                                </Badge>

                                <span className="text-xs text-violet-800/70 dark:text-violet-200/70">
                                    <Link
                                        href={route('carnets.show', carnet.id)}
                                        className="underline underline-offset-2"
                                    >
                                        Nº {carnet.registro}
                                    </Link>
                                    {carnet.capacidad && ` · ${carnet.capacidad}`}
                                    {` · vence ${fecha(carnet.fecha_vencimiento)}`}
                                </span>
                            </li>
                        ))}
                    </ul>

                    {/*
                        EL AVISO DEL ANULADO, que es el caso que más confunde en
                        ventanilla: el carnet anulado SIGUE OCUPANDO su lugar en el
                        índice único, así que tampoco se puede emitir otro de ese
                        mismo rubro este año. Sin el aviso, el operador prueba, el
                        servidor rechaza y nadie entiende por qué — el rubro está
                        deshabilitado en el selector y el carnet dice «anulado»,
                        que suena a «ya no cuenta».
                    */}
                    {situacion.carnets.some((c) => c.estado === 'anulado') && (
                        <p className="text-xs text-violet-800/80 dark:text-violet-200/80">
                            Un carnet <strong>anulado</strong> sigue ocupando su rubro en la gestión{' '}
                            {situacion.gestion}: no se puede emitir uno nuevo de esa actividad hasta
                            el año siguiente.
                        </p>
                    )}

                    {/*
                        Y el del suspendido, que se resuelve distinto: no hay que
                        tramitar nada, hay que pedirle a un supervisor que levante
                        la suspensión desde la ficha del carnet.
                    */}
                    {situacion.carnets.some((c) => c.estado === 'suspendido') && (
                        <p className="text-xs text-violet-800/80 dark:text-violet-200/80">
                            Un carnet <strong>suspendido</strong> no se vuelve a tramitar: un
                            supervisor tiene que levantar la suspensión desde la ficha del carnet.
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

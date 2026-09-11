import { Check, Circle, CircleSlash } from 'lucide-react';
import { fechaHora } from '@/lib/utils';
import type { HitoTramite, TramiteDetalle } from '@/types/tramites';

/**
 * ============================================================================
 *  POR DÓNDE VA EL TRÁMITE
 * ============================================================================
 *
 * Los cinco pasos del circuito, en orden, con la fecha y el nombre de quien
 * hizo cada uno.
 *
 * Es lo primero que se mira cuando alguien pregunta «¿en qué quedó este
 * trámite?». Sin esto, la respuesta hay que reconstruirla cruzando cinco
 * columnas de fecha y tres de usuario en la base, y en ventanilla eso
 * directamente no se hace: se contesta «está en proceso».
 *
 * Los pasos que todavía no ocurrieron igual se dibujan, en gris. Mostrar solo
 * los cumplidos escondería lo que falta, que es justamente lo que el que
 * pregunta quiere saber.
 */
export function LineaTiempoTramite({ tramite }: { tramite: TramiteDetalle }) {
    const pasos: { clave: string; titulo: string; hito: HitoTramite | null }[] = [
        { clave: 'recepcion', titulo: 'Recibido en ventanilla', hito: tramite.hitos.recepcion },
        /*
         * Ya no se dibuja «Tomado para revisión».
         *
         * Era un paso propio cuando existía el botón de tomar el expediente.
         * Al retirarlo, `fecha_revision` se llena en el mismo momento que la
         * aprobación y con la misma persona: dos renglones seguidos con la
         * misma hora y el mismo nombre, que se leen como un error de la
         * pantalla. El dato sigue guardado en la base y viaja en `hitos`, por
         * si mañana la revisión vuelve a ser un acto aparte.
         */
        { clave: 'aprobacion', titulo: 'Aprobado', hito: tramite.hitos.aprobacion },
        { clave: 'emision', titulo: 'Documento emitido', hito: tramite.hitos.emision },
        { clave: 'entrega', titulo: 'Entregado', hito: tramite.hitos.entrega },
    ];

    /*
     * Un trámite rechazado se corta donde estaba: los pasos que siguen no van a
     * ocurrir nunca, y dejarlos en gris haría creer que todavía está en curso.
     */
    const rechazado = tramite.estado === 'rechazado';

    return (
        <ol className="space-y-0">
            {pasos.map((paso, i) => {
                const cumplido = paso.hito !== null;
                const ultimo = i === pasos.length - 1;

                // Después del rechazo no se dibuja nada más.
                if (rechazado && !cumplido && i > 0 && pasos[i - 1].hito === null) {
                    return null;
                }

                return (
                    <li key={paso.clave} className="flex gap-3">
                        {/* La columna del hilo: el punto y la línea que baja al
                            siguiente paso. La línea se pinta sólida hasta donde
                            el trámite llegó y punteada de ahí en adelante. */}
                        <div className="flex flex-col items-center">
                            <span
                                className={
                                    cumplido
                                        ? 'flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                        : 'flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground'
                                }
                                aria-hidden
                            >
                                {cumplido ? (
                                    <Check className="size-3.5" />
                                ) : (
                                    <Circle className="size-2.5" />
                                )}
                            </span>

                            {!ultimo && (
                                <span
                                    className={
                                        cumplido
                                            ? 'w-px flex-1 bg-emerald-500/40'
                                            : 'w-px flex-1 border-l border-dashed border-border'
                                    }
                                    aria-hidden
                                />
                            )}
                        </div>

                        <div className={ultimo ? 'pb-0' : 'pb-5'}>
                            <p
                                className={
                                    cumplido ? 'text-sm font-medium' : 'text-sm text-muted-foreground'
                                }
                            >
                                {paso.titulo}
                            </p>

                            {paso.hito ? (
                                <p className="text-xs text-muted-foreground">
                                    {fechaHora(paso.hito.fecha)}
                                    {paso.hito.quien && ` · ${paso.hito.quien}`}
                                </p>
                            ) : (
                                <p className="text-xs text-muted-foreground/70">Pendiente</p>
                            )}
                        </div>
                    </li>
                );
            })}

            {/* El rechazo no es un paso más del circuito: es el final por otro
                lado, y por eso se dibuja aparte y en rojo. */}
            {rechazado && (
                <li className="flex gap-3 pt-1">
                    <span
                        className="flex size-6 shrink-0 items-center justify-center rounded-full bg-rose-500/15 text-rose-600 dark:text-rose-400"
                        aria-hidden
                    >
                        <CircleSlash className="size-3.5" />
                    </span>

                    <div>
                        <p className="text-sm font-medium text-rose-600 dark:text-rose-400">
                            Rechazado
                        </p>
                        {tramite.motivo_rechazo && (
                            <p className="text-xs text-muted-foreground">
                                {tramite.motivo_rechazo}
                            </p>
                        )}
                    </div>
                </li>
            )}
        </ol>
    );
}

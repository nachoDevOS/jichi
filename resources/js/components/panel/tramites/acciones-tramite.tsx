import { router } from '@inertiajs/react';
import { BadgeCheck, CircleSlash, Handshake, LoaderCircle, Printer } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { usePermisos } from '@/hooks/use-permisos';
import { bs } from '@/lib/utils';
import type { TramiteDetalle } from '@/types/tramites';

/**
 * ============================================================================
 *  LOS BOTONES DEL CIRCUITO
 * ============================================================================
 *
 * Aprobar, rechazar, emitir y entregar. Cuatro acciones y cuatro permisos
 * distintos: quien atiende en ventanilla recepciona y entrega, pero no
 * aprueba.
 *
 * ----------------------------------------------------------------------------
 *  QUÉ BOTÓN SE VE Y POR QUÉ
 * ----------------------------------------------------------------------------
 *
 * Un botón aparece cuando se cumplen DOS cosas:
 *
 *   1. el trámite puede pasar a ese estado —lo dice `tramite.siguientes`, que
 *      viene calculado del enum `EstadoTramite` en el servidor—
 *   2. el usuario tiene el permiso
 *
 * La excepción es EMITIR, que no es un cambio de estado: el trámite se queda
 * aprobado hasta que se entregue. Para ese botón la condición la calcula el
 * servidor aparte, en `puede_emitirse`.
 *
 * La primera condición no se escribe acá. Si esta pantalla tuviera su propia
 * lista de «desde aprobado se puede emitir», habría dos copias de las reglas
 * del circuito y tarde o temprano dirían cosas distintas: el botón se vería
 * pero el servidor lo rechazaría, o al revés.
 *
 * Esconder un botón es comodidad, no seguridad. Quien de verdad bloquea es el
 * middleware `permiso:` de las rutas. Ver routes/panel.php.
 */
export function AccionesTramite({ tramite }: { tramite: TramiteDetalle }) {
    const { puede } = usePermisos();

    const [enviando, setEnviando] = useState<string | null>(null);

    // Los dos formularios que piden un dato antes de resolver. Se abren dentro
    // del panel y no en una ventana aparte: el revisor tiene que poder mirar
    // los adjuntos de la izquierda mientras escribe el motivo.
    const [rechazando, setRechazando] = useState(false);
    const [motivo, setMotivo] = useState('');
    const [modoEntrega, setModoEntrega] = useState('fisica');

    const puedePasarA = (estado: string) => tramite.siguientes.includes(estado);

    function ejecutar(ruta: string, datos: Record<string, string> = {}) {
        setEnviando(ruta);

        router.post(route(ruta, tramite.id), datos, {
            preserveScroll: true,
            onFinish: () => setEnviando(null),
            onSuccess: () => {
                setRechazando(false);
                setMotivo('');
            },
        });
    }

    /*
     * Un trámite entregado o rechazado no tiene nada más que hacer. Se dice con
     * texto en vez de dejar el panel vacío: un recuadro sin nada se lee como
     * que la pantalla no cargó.
     */
    if (tramite.siguientes.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                El trámite está {tramite.estado_etiqueta.toLowerCase()} y no admite más
                cambios.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {puedePasarA('aprobado') && puede('tramites.aprobar') && (
                <Button
                    type="button"
                    className="w-full"
                    disabled={enviando !== null}
                    onClick={() => ejecutar('tramites.aprobar')}
                >
                    {enviando === 'tramites.aprobar' ? (
                        <LoaderCircle className="size-4 animate-spin" />
                    ) : (
                        <BadgeCheck className="size-4" />
                    )}
                    Aprobar trámite
                </Button>
            )}

            {/*
                Emitir es el único botón que no sale de `siguientes`: emitir el
                documento ya no cambia el estado del trámite, que se queda
                aprobado hasta que se entregue. Quién puede emitir lo calcula el
                servidor en `puede_emitirse`, en el mismo lugar donde lo
                comprueba antes de aceptar.
            */}
            {tramite.puede_emitirse && puede('documentos.emitir') && (
                <div className="space-y-2">
                    <Button
                        type="button"
                        className="w-full"
                        // El saldo pendiente apaga el botón acá y lo rechaza el
                        // servidor: una credencial emitida ya está en la calle y
                        // el saldo se vuelve incobrable.
                        disabled={enviando !== null || !tramite.esta_pagado}
                        onClick={() => ejecutar('tramites.emitir')}
                    >
                        {enviando === 'tramites.emitir' ? (
                            <LoaderCircle className="size-4 animate-spin" />
                        ) : (
                            <Printer className="size-4" />
                        )}
                        Emitir documento
                    </Button>

                    {!tramite.esta_pagado && (
                        <p className="text-xs text-muted-foreground">
                            No se puede emitir con un saldo de {bs(tramite.saldo_pendiente)}{' '}
                            pendiente.
                        </p>
                    )}
                </div>
            )}

            {/*
                No se entrega lo que no se emitió. Antes lo garantizaba el
                orden de los estados —a «entregado» solo se llegaba desde
                «emitido»—; ahora la condición es que exista el documento, que
                es la misma que comprueba el servidor.
            */}
            {puedePasarA('entregado') && tramite.documento !== null && puede('documentos.entregar') && (
                <div className="space-y-3 rounded-lg border border-border p-3">
                    {/* Cómo se entregó no es un detalle: cuando alguien reclama
                        que nunca recibió su credencial, esa línea es toda la
                        respuesta que hay. */}
                    <Campo etiqueta="Cómo se entrega" htmlFor="modo_entrega">
                        <Select
                            id="modo_entrega"
                            value={modoEntrega}
                            onChange={(e) => setModoEntrega(e.target.value)}
                        >
                            <option value="fisica">En mano, físicamente</option>
                            <option value="digital">Digital (correo o mensajería)</option>
                        </Select>
                    </Campo>

                    <Button
                        type="button"
                        className="w-full"
                        disabled={enviando !== null}
                        onClick={() => ejecutar('tramites.entregar', { modo_entrega: modoEntrega })}
                    >
                        {enviando === 'tramites.entregar' ? (
                            <LoaderCircle className="size-4 animate-spin" />
                        ) : (
                            <Handshake className="size-4" />
                        )}
                        Registrar entrega
                    </Button>
                </div>
            )}

            {puedePasarA('rechazado') && puede('tramites.rechazar') && (
                <div className="border-t border-border pt-3">
                    {rechazando ? (
                        <div className="space-y-3">
                            {/*
                                El motivo es obligatorio y tiene mínimo de largo
                                a propósito: el pescador vuelve a preguntar por
                                qué, y quien lo atiende casi nunca es el mismo
                                que rechazó. Un «no corresponde» de dos palabras
                                no le sirve a nadie.
                            */}
                            <Campo
                                etiqueta="Motivo del rechazo"
                                htmlFor="motivo_rechazo"
                                ayuda="Se lo va a leer quien lo atienda cuando vuelva a preguntar."
                                obligatorio
                            >
                                <Textarea
                                    id="motivo_rechazo"
                                    rows={3}
                                    value={motivo}
                                    onChange={(e) => setMotivo(e.target.value)}
                                    placeholder="La certificación de la asociación está vencida."
                                />
                            </Campo>

                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="destructive"
                                    disabled={enviando !== null || motivo.trim().length < 10}
                                    onClick={() =>
                                        ejecutar('tramites.rechazar', {
                                            motivo_rechazo: motivo.trim(),
                                        })
                                    }
                                >
                                    {enviando === 'tramites.rechazar' ? (
                                        <LoaderCircle className="size-4 animate-spin" />
                                    ) : (
                                        <CircleSlash className="size-4" />
                                    )}
                                    Confirmar rechazo
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setRechazando(false)}
                                >
                                    Cancelar
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <Button
                            type="button"
                            variant="ghost"
                            className="w-full text-destructive"
                            onClick={() => setRechazando(true)}
                        >
                            <CircleSlash className="size-4" />
                            Rechazar trámite
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}

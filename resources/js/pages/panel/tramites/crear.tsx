import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, User } from 'lucide-react';
import { AvisoMaqueta } from '@/components/panel/tramites/aviso-maqueta';
import { BuscadorSolicitante } from '@/components/panel/tramites/buscador-solicitante';
import { EstadoCredencial } from '@/components/panel/tramites/estado-credencial';
import { SelectorTipoTramite } from '@/components/panel/tramites/selector-tipo-tramite';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import LayoutPanel from '@/layouts/layout-panel';
import type { SolicitanteDelTramite, TipoTramiteOpcion } from '@/types/tramites';

/**
 * ============================================================================
 *  NUEVO TRÁMITE — ELEGIR SOLICITANTE Y SERVICIO
 * ============================================================================
 *
 * Una sola pantalla con dos momentos, según haya solicitante elegido o no:
 *
 *   sin solicitante  → buscador
 *   con solicitante  → su credencial arriba, y el catálogo de servicios
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ PRIMERO LA PERSONA Y DESPUÉS EL SERVICIO
 * ----------------------------------------------------------------------------
 *
 * Por la regla del negocio: el Permiso por Faena y la Guía Única de Transporte
 * exigen Cédula de Pescador VIGENTE; la cédula, no. Hasta no saber de quién se
 * trata, el sistema no puede decir qué se le puede emitir.
 *
 * Al revés —servicio primero— el operador cargaría media pantalla para
 * descubrir recién al final que la persona no está habilitada, con el pescador
 * esperando en la ventanilla.
 *
 * Los dos momentos viven en la MISMA dirección y no en dos rutas separadas
 * porque para el operador es un solo paso mental: «a quién atiendo y qué
 * necesita». Partirlo obligaría a volver atrás cada vez que se equivoca de
 * persona.
 */
interface Props {
    esMaqueta: boolean;
    /** null = todavía no se eligió a nadie: se muestra el buscador. */
    solicitante: SolicitanteDelTramite | null;
    busqueda: string | null;
    solicitantes: SolicitanteDelTramite[];
    tipos: TipoTramiteOpcion[];
}

export default function CrearTramite({
    esMaqueta,
    solicitante,
    busqueda,
    solicitantes,
    tipos,
}: Props) {
    return (
        <LayoutPanel
            titulo="Nuevo trámite"
            descripcion={
                solicitante
                    ? 'Elegí el servicio que se va a tramitar'
                    : 'Empezá por el pescador que hace el trámite'
            }
            acciones={
                solicitante && (
                    <Link href={route('tramites.create')}>
                        <Button variant="outline">
                            <ArrowLeft className="size-4" />
                            Cambiar de solicitante
                        </Button>
                    </Link>
                )
            }
        >
            <Head title="Nuevo trámite" />

            <div className="mx-auto max-w-5xl space-y-4">
                {esMaqueta && (
                    <AvisoMaqueta>
                        El catálogo, las vigencias y la exigencia de credencial ya salen de la
                        base de datos. Lo que todavía no se guarda es el trámite en sí.
                    </AvisoMaqueta>
                )}

                {solicitante === null ? (
                    <BuscadorSolicitante resultados={solicitantes} busqueda={busqueda} />
                ) : (
                    <>
                        <FichaSolicitante solicitante={solicitante} />
                        <SelectorTipoTramite tipos={tipos} solicitante={solicitante} />
                    </>
                )}
            </div>
        </LayoutPanel>
    );
}

/**
 * Quién es y cómo está su credencial.
 *
 * Va arriba de todo y antes del catálogo a propósito: es el dato que decide
 * qué servicios van a estar habilitados más abajo, así que el operador tiene
 * que leerlo primero y no descubrirlo tarjeta por tarjeta.
 */
function FichaSolicitante({ solicitante }: { solicitante: SolicitanteDelTramite }) {
    return (
        <Card>
            <CardContent className="space-y-4 p-5">
                <div className="flex items-center gap-4">
                    <div className="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border bg-muted">
                        {solicitante.foto_url ? (
                            <img
                                src={solicitante.foto_url}
                                alt={`Fotografía de ${solicitante.nombreCompleto}`}
                                className="size-full object-cover"
                            />
                        ) : (
                            <User className="size-6 text-muted-foreground" />
                        )}
                    </div>

                    <div className="min-w-0 flex-1">
                        <p className="truncate font-semibold">{solicitante.nombreCompleto}</p>
                        <p className="font-mono text-xs text-muted-foreground">
                            {solicitante.documento_identidad}
                            {solicitante.telefono ? ` · ${solicitante.telefono}` : ''}
                        </p>
                    </div>

                    <Link href={route('solicitantes.show', solicitante.id)}>
                        <Button variant="ghost" size="sm">
                            Ver ficha
                        </Button>
                    </Link>
                </div>

                <EstadoCredencial
                    credencial={solicitante.credencial}
                    enTramite={solicitante.credencial_en_tramite}
                />
            </CardContent>
        </Card>
    );
}

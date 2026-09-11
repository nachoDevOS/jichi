import { Head, Link } from '@inertiajs/react';
import { ChevronRight, FilePlus2, FileText } from 'lucide-react';
import { AvisoMaqueta } from '@/components/panel/tramites/aviso-maqueta';
import { FiltrosTramites } from '@/components/panel/tramites/filtros-tramites';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Paginacion } from '@/components/ui/paginacion';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fechaHora } from '@/lib/utils';
import type { Paginado } from '@/types';
import type { FiltrosTramites as Filtros, OpcionEstado, TramiteFila } from '@/types/tramites';

/**
 * ============================================================================
 *  LISTADO DE TRÁMITES
 * ============================================================================
 *
 * La tabla no trae todos los trámites: el servidor manda solo la página pedida
 * y, si hay algo escrito en el buscador, solo lo que coincide. Las dos cosas
 * viajan en la barra de direcciones —?buscar=perez&page=2—, así que una
 * búsqueda se puede recargar, guardar o pasarle a otra ventanilla.
 *
 * De dónde vienen las props: TramiteController@index las devolvió con
 * Inertia::render('panel/tramites/index', [...]).
 */
interface Props {
    esMaqueta: boolean;
    /** Objeto paginado de Laravel: las filas van en .data. */
    tramites: Paginado<TramiteFila>;
    filtros: Filtros;
    /** Cuántas filas por página se puede elegir. La lista la define el controlador. */
    opcionesPorPagina: number[];
    /** Los estados del circuito, para el selector. Salen de EstadoTramite. */
    opcionesEstado: OpcionEstado[];
}

export default function IndiceTramites({
    esMaqueta,
    tramites,
    filtros,
    opcionesPorPagina,
    opcionesEstado,
}: Props) {
    const { puede } = usePermisos();

    const hayFiltros = Boolean(filtros.buscar) || Boolean(filtros.estado);

    return (
        <LayoutPanel
            titulo="Trámites"
            // El total es el de la consulta, no el de la página: con una
            // búsqueda puesta dice cuántos coincidieron, que es justo lo que se
            // quiere saber después de escribir un apellido.
            descripcion={
                hayFiltros
                    ? `${tramites.total} coinciden con el filtro`
                    : `${tramites.total} registrados en el sistema`
            }
            acciones={
                puede('tramites.crear') && (
                    <Link href={route('tramites.create')}>
                        <Button>
                            <FilePlus2 className="size-4" />
                            Nuevo trámite
                        </Button>
                    </Link>
                )
            }
        >
            <Head title="Trámites" />

            <div className="space-y-4">
                {esMaqueta && (
                    <AvisoMaqueta>
                        Las filas de abajo son inventadas y están escritas en el controlador. No
                        hay ninguna consulta a la base de datos.
                    </AvisoMaqueta>
                )}

                <FiltrosTramites
                    filtros={filtros}
                    opcionesPorPagina={opcionesPorPagina}
                    opcionesEstado={opcionesEstado}
                />

                {/* p-0 porque la tabla pone su propio espaciado y tiene que
                    llegar hasta el borde de la tarjeta. */}
                <Card>
                    <CardContent className="p-0">
                        {tramites.data.length === 0 ? (
                            <VacioTramites hayFiltros={hayFiltros} puedeCrear={puede('tramites.crear')} />
                        ) : (
                            <>
                                {/* La tabla scrollea sola en pantallas angostas
                                    para que la página nunca se desplace en
                                    horizontal. */}
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-b border-border text-left text-xs text-muted-foreground">
                                            <tr>
                                                <th className="px-5 py-3 font-medium">N°</th>
                                                <th className="px-5 py-3 font-medium">
                                                    Solicitante
                                                </th>
                                                <th className="px-5 py-3 font-medium">
                                                    Embarcación
                                                </th>
                                                <th className="px-5 py-3 font-medium">Servicio</th>
                                                <th className="px-5 py-3 font-medium">Estado</th>
                                                <th className="px-5 py-3 text-right font-medium">
                                                    Monto
                                                </th>
                                                <th className="px-5 py-3 font-medium">Recibido</th>
                                                {/* Sin texto: la columna es solo
                                                    la flecha que entra a la
                                                    ficha. */}
                                                <th className="px-5 py-3">
                                                    <span className="sr-only">Ver</span>
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            {tramites.data.map((t) => (
                                                <tr
                                                    key={t.id}
                                                    className="border-b border-border last:border-0 hover:bg-accent/40"
                                                >
                                                    {/*
                                                        El número es el enlace a
                                                        la ficha, donde el
                                                        trámite se revisa, se
                                                        aprueba y se emite.

                                                        Debajo va el código
                                                        impreso en la credencial,
                                                        cuando el servicio lo
                                                        lleva: es lo que el
                                                        pescador tiene en la mano
                                                        y por lo que pregunta
                                                        cuando vuelve a
                                                        ventanilla.
                                                    */}
                                                    <td className="px-5 py-3">
                                                        <Link
                                                            href={route('tramites.show', t.id)}
                                                            className="font-mono text-xs font-medium text-primary hover:underline"
                                                        >
                                                            N° {t.id}
                                                        </Link>

                                                        {t.registro && (
                                                            <p className="font-mono text-[0.65rem] text-muted-foreground">
                                                                {t.registro}
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td className="px-5 py-3">
                                                        <p className="font-medium">
                                                            {t.solicitante}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {t.ci_nit}
                                                        </p>
                                                    </td>
                                                    <td className="px-5 py-3">{t.embarcacion}</td>
                                                    <td className="px-5 py-3">
                                                        <span className="mr-1" aria-hidden>
                                                            {t.icono}
                                                        </span>
                                                        {t.tipo}
                                                    </td>
                                                    <td className="px-5 py-3">
                                                        <Badge color={t.estado_color}>
                                                            {t.estado_etiqueta}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-5 py-3 text-right font-medium">
                                                        {bs(t.monto_total)}
                                                    </td>
                                                    <td className="px-5 py-3 text-xs text-muted-foreground">
                                                        {fechaHora(t.creado)}
                                                    </td>
                                                    <td className="px-5 py-3 text-right">
                                                        <Link
                                                            href={route('tramites.show', t.id)}
                                                            aria-label={`Ver el trámite N° ${t.id}`}
                                                            className="text-muted-foreground hover:text-foreground"
                                                        >
                                                            <ChevronRight className="size-4" />
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {/* La barra de paginación se esconde sola
                                    cuando hay una sola página. */}
                                <Paginacion paginado={tramites} />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </LayoutPanel>
    );
}

/**
 * Una tabla vacía sin explicación parece un error del sistema. Acá se aclara
 * cuál de las dos cosas pasó, que son bien distintas: no hay trámites todavía,
 * o los hay pero ninguno coincide con lo buscado.
 */
function VacioTramites({
    hayFiltros,
    puedeCrear,
}: {
    hayFiltros: boolean;
    puedeCrear: boolean;
}) {
    if (hayFiltros) {
        return (
            <EstadoVacio
                icono={FileText}
                titulo="Ningún trámite coincide con el filtro"
                descripcion="Probá con otro estado, o buscá por número de trámite, registro de la credencial, nombre del solicitante o cédula."
            />
        );
    }

    return (
        <EstadoVacio
            icono={FileText}
            titulo="Todavía no hay trámites registrados"
            descripcion="Los trámites se cargan cuando el solicitante se acerca a ventanilla a pedir un servicio."
            accion={
                puedeCrear && (
                    <Link href={route('tramites.create')}>
                        <Button>
                            <FilePlus2 className="size-4" />
                            Registrar el primero
                        </Button>
                    </Link>
                )
            }
        />
    );
}

import { Head, Link } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { FiltrosSolicitantes } from '@/components/panel/solicitantes/filtros-solicitantes';
import { TablaSolicitantes } from '@/components/panel/solicitantes/tabla-solicitantes';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import type { Paginado } from '@/types';
import type { FiltrosSolicitantes as Filtros, SolicitanteFila } from '@/types/solicitantes';

/**
 * ============================================================================
 *  LISTADO DE SOLICITANTES
 * ============================================================================
 *
 * Es la pantalla que más se usa en ventanilla: acá se busca al pescador antes
 * de iniciarle un trámite.
 *
 * De dónde vienen las props: SolicitanteController@index las devolvió con
 * Inertia::render('solicitantes/index', [...]). El nombre 'solicitantes/index'
 * es la ruta de ESTE archivo dentro de resources/js/pages/, sin la extensión.
 * Así es como Inertia sabe qué componente pintar.
 */

interface Props {
    /** Objeto paginado de Laravel: filas en .data más los datos de navegación. */
    solicitantes: Paginado<SolicitanteFila>;
    filtros: Filtros;
    /** Cuántas filas por página se puede elegir. La lista la define el controlador. */
    opcionesPorPagina: number[];
}

export default function IndiceSolicitantes({
    solicitantes,
    filtros,
    opcionesPorPagina,
}: Props) {
    const { puede } = usePermisos();

    const hayFiltros = Boolean(filtros.buscar);

    return (
        <LayoutPanel
            titulo="Solicitantes"
            descripcion={`${solicitantes.total} registrados en el sistema`}
            // `acciones` es el hueco de la esquina superior derecha que ofrece
            // el layout. El botón solo aparece si el usuario tiene el permiso.
            acciones={
                puede('solicitantes.crear') && (
                    <Link href={route('solicitantes.create')}>
                        <Button>
                            <UserPlus className="size-4" />
                            Nuevo solicitante
                        </Button>
                    </Link>
                )
            }
        >
            <Head title="Solicitantes" />

            <div className="space-y-4">
                <FiltrosSolicitantes filtros={filtros} opcionesPorPagina={opcionesPorPagina} />

                {/* p-0 porque la tabla pone su propio espaciado y tiene que
                    llegar hasta el borde de la tarjeta. */}
                <Card>
                    <CardContent className="p-0">
                        <TablaSolicitantes solicitantes={solicitantes} hayFiltros={hayFiltros} />
                    </CardContent>
                </Card>
            </div>
        </LayoutPanel>
    );
}

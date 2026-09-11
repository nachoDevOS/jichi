import { Link } from '@inertiajs/react';
import { Pencil, User, UserPlus, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Paginacion } from '@/components/ui/paginacion';
import { usePermisos } from '@/hooks/use-permisos';
import type { Paginado } from '@/types';
import type { SolicitanteFila } from '@/types/solicitantes';

/**
 * Tabla del listado de solicitantes, con su paginación.
 *
 * Recibe el objeto paginado COMPLETO de Laravel, no solo las filas: adentro
 * vienen las filas (en `.data`) y la información para navegar entre páginas.
 */
export function TablaSolicitantes({
    solicitantes,
    hayFiltros,
}: {
    solicitantes: Paginado<SolicitanteFila>;
    /** Cambia el mensaje del estado vacío: "no hay nada" vs "no encontré nada". */
    hayFiltros: boolean;
}) {
    const { puede } = usePermisos();

    if (solicitantes.data.length === 0) {
        return hayFiltros ? (
            <EstadoVacio
                icono={Users}
                titulo="Ningún solicitante coincide con la búsqueda"
                descripcion="Probá con otro nombre o número de documento, o quitá los filtros."
            />
        ) : (
            <EstadoVacio
                icono={Users}
                titulo="Todavía no hay solicitantes registrados"
                descripcion="Los solicitantes se cargan la primera vez que se acercan a ventanilla."
                accion={
                    puede('solicitantes.crear') && (
                        <Link href={route('solicitantes.create')}>
                            <Button>
                                <UserPlus className="size-4" />
                                Registrar el primero
                            </Button>
                        </Link>
                    )
                }
            />
        );
    }

    return (
        <>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th className="px-5 py-2 font-medium">Solicitante</th>
                            <th className="px-5 py-2 font-medium">Documento</th>
                            <th className="px-5 py-2 font-medium">Contacto</th>
                            <th className="px-5 py-2 text-center font-medium">Trámites</th>
                            <th className="px-5 py-2 text-right font-medium">
                                <span className="sr-only">Acciones</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-border">
                        {/*
                            .map() recorre las filas y devuelve una <tr> por cada
                            una. La prop `key` tiene que ser única y estable: se
                            usa el id de la base de datos, nunca la posición del
                            array (al reordenar o filtrar, las posiciones cambian
                            y React confundiría una fila con otra).
                        */}
                        {solicitantes.data.map((s) => (
                            <tr key={s.id} className="hover:bg-muted/30">
                                <td className="px-5 py-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <User className="size-4" />
                                        </div>

                                        <div className="min-w-0">
                                            <Link
                                                href={route('solicitantes.show', s.id)}
                                                className="font-medium hover:text-primary hover:underline"
                                            >
                                                {s.nombreCompleto}
                                            </Link>
                                        </div>
                                    </div>
                                </td>

                                <td className="whitespace-nowrap px-5 py-3 font-mono text-xs">
                                    {s.documento_identidad}
                                </td>

                                <td className="px-5 py-3">
                                    {/* El guion largo indica "no hay dato", que se
                                        lee mejor que una celda en blanco. */}
                                    {s.telefono ?? '—'}
                                    {s.email && (
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {s.email}
                                        </span>
                                    )}
                                </td>

                                <td className="px-5 py-3 text-center tabular-nums">
                                    {s.tramites_count}
                                </td>

                                <td className="whitespace-nowrap px-5 py-3 text-right">
                                    {puede('solicitantes.editar') && (
                                        <Link href={route('solicitantes.edit', s.id)}>
                                            <Button variant="ghost" size="sm" title="Editar">
                                                <Pencil className="size-4" />
                                                <span className="sr-only">Editar</span>
                                            </Button>
                                        </Link>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Paginacion paginado={solicitantes} />
        </>
    );
}

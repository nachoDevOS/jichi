import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Tags } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { RubroFila } from '@/types/rubros';

/**
 * El catálogo de rubros.
 *
 * SIN PAGINACIÓN NI BUSCADOR, a propósito: son media docena de filas y entran
 * enteras en una pantalla. Agregar paginación acá sería maquinaria para un
 * problema que no existe.
 *
 * TAMPOCO HAY BOTÓN DE BORRAR. Un rubro no se borra nunca: los carnets
 * históricos apuntan a él, y desaparecerlo haría que un carnet del año pasado
 * dejara de mostrar una actividad que en su momento estuvo autorizada. Se pasa a
 * «inactivo» desde el formulario de edición.
 */
export default function IndiceRubros({ rubros }: { rubros: RubroFila[] }) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;

    return (
        <LayoutPanel
            titulo="Rubros"
            descripcion="Actividades que un carnet puede habilitar, con su tarifa vigente."
            acciones={
                puede('rubros.gestionar') && (
                    <Button onClick={() => router.visit(route('rubros.create'))}>
                        <Plus className="size-4" />
                        Nuevo rubro
                    </Button>
                )
            }
        >
            <Head title="Rubros" />

            <Card>
                {rubros.length === 0 ? (
                    <EstadoVacio
                        icono={Tags}
                        titulo="Sin rubros"
                        descripcion="Cargue al menos uno para poder registrar trámites."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3 font-medium">Rubro</th>
                                    <th className="px-5 py-3 text-right font-medium">Costo</th>
                                    <th className="px-5 py-3 font-medium">Estado</th>
                                    <th className="px-5 py-3 text-right font-medium">Carnets</th>
                                    <th className="px-5 py-3 text-right font-medium">Trámites</th>
                                    <th className="px-5 py-3" />
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border">
                                {rubros.map((r) => (
                                    <tr key={r.id} className="hover:bg-secondary/50">
                                        <td className="px-5 py-3">
                                            <p className="font-medium">{r.nombre}</p>
                                            {r.descripcion && (
                                                <p className="max-w-lg text-sm text-muted-foreground">
                                                    {r.descripcion}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-5 py-3 text-right tabular-nums">
                                            {bs(r.costo, institucion.moneda)}
                                        </td>
                                        <td className="px-5 py-3">
                                            <Badge color={r.estado_color}>{r.estado_etiqueta}</Badge>
                                        </td>
                                        <td className="px-5 py-3 text-right tabular-nums text-muted-foreground">
                                            {r.carnets_count}
                                        </td>
                                        <td className="px-5 py-3 text-right tabular-nums text-muted-foreground">
                                            {r.tramites_count}
                                        </td>
                                        <td className="px-5 py-3 text-right">
                                            {puede('rubros.gestionar') && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => router.visit(route('rubros.edit', r.id))}
                                                >
                                                    <Pencil className="size-4" />
                                                    Editar
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </LayoutPanel>
    );
}

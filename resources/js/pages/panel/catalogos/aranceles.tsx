import { Head, Link, usePage } from '@inertiajs/react';
import { Coins, History, Pencil, TriangleAlert } from 'lucide-react';
import { CeldaSireb } from '@/components/panel/catalogos/celda-sireb';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { ArancelFila } from '@/types/catalogos';

/**
 *  Aranceles de SIREB — la tarifa de los cobros que no cuelgan de un catálogo
 */
export default function CatalogoAranceles({
    aranceles,
    sirebDisponible,
}: {
    aranceles: ArancelFila[];
    /** false = SIREB no respondió: los precios no se pueden mostrar. */
    sirebDisponible: boolean;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;

    return (
        <LayoutPanel
            titulo="Aranceles de SIREB"
            descripcion="La tarifa de Recaudaciones de cada documento que no sale de un catálogo. Se corrigen; no se agregan."
        >
            <Head title="Aranceles de SIREB" />

            <div className="space-y-6">
                {!sirebDisponible && (
                    <Card className="border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
                        <CardContent className="flex items-start gap-3 pt-5 text-sm">
                            <TriangleAlert className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
                            <p className="text-amber-900 dark:text-amber-200">
                                Recaudaciones (SIREB) no responde: no se pueden mostrar los precios.
                            </p>
                        </CardContent>
                    </Card>
                )}

                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle>Aranceles</CardTitle>
                    </CardHeader>

                    <CardContent className="p-0">
                        {aranceles.length === 0 ? (
                            <EstadoVacio
                                icono={Coins}
                                titulo="Sin aranceles"
                                descripcion="Falta la fila de la faena: se crea con el seeder de catálogos."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">Documento</th>
                                            <th className="px-5 py-2.5 font-medium">SIREB</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Precio</th>
                                            <th className="px-5 py-2.5" />
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {aranceles.map((a) => (
                                            <tr key={a.id} className="hover:bg-secondary/50">
                                                <td className="px-5 py-2.5">
                                                    <p className="font-medium">{a.etiqueta}</p>
                                                    <p className="text-xs text-muted-foreground">{a.descripcion}</p>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <CeldaSireb
                                                        servicioId={a.servicio_sireb}
                                                        tarifaId={a.tarifa_sireb}
                                                        servicio={a.sireb_servicio}
                                                        etiqueta={a.sireb_etiqueta}
                                                    />
                                                </td>

                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {a.precio !== null ? bs(a.precio, institucion.moneda) : '—'}
                                                </td>

                                                <td className="px-5 py-2.5 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Link
                                                            href={route('aranceles.show', a.id)}
                                                            className={cn(buttonVariants({ variant: 'ver', size: 'sm' }))}
                                                            title="Historial de SIREB"
                                                        >
                                                            <History className="size-4" />
                                                        </Link>

                                                        {puede('catalogos.gestionar') && (
                                                            <Link
                                                                href={route('aranceles.edit', a.id)}
                                                                className={cn(buttonVariants({ variant: 'editar', size: 'sm' }))}
                                                                title="Editar"
                                                            >
                                                                <Pencil className="size-4" />
                                                            </Link>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </LayoutPanel>
    );
}

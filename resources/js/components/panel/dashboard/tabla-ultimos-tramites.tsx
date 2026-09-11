import { FileText } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { bs, fechaHora } from '@/lib/utils';
import type { UltimoTramite } from '@/types/dashboard';

/**
 * Los últimos trámites que pasaron por ventanilla.
 *
 * El color del badge de estado NO se decide acá: viene calculado desde PHP en
 * `estado_color`, que sale de App\Enums\EstadoTramite::color(). De ese modo,
 * el día que "Aprobado" deje de ser celeste se cambia en el enum y cambia en
 * toda la aplicación de una sola vez.
 */
export function TablaUltimosTramites({ tramites }: { tramites: UltimoTramite[] }) {
    return (
        <Card className="lg:col-span-3">
            <CardHeader>
                <CardTitle>Últimos trámites registrados</CardTitle>
            </CardHeader>

            {/* px-0 quita el margen lateral para que la tabla llegue al borde. */}
            <CardContent className="px-0">
                {tramites.length === 0 ? (
                    <EstadoVacio
                        icono={FileText}
                        titulo="Todavía no hay trámites registrados"
                        descripcion="Los trámites que se recepcionen en ventanilla aparecerán acá."
                    />
                ) : (
                    /*
                        overflow-x-auto: en celular la tabla no cabe y se
                        desplaza sola de costado, en vez de deformar la página.
                    */
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-2 font-medium">Código</th>
                                    <th className="px-5 py-2 font-medium">Solicitante</th>
                                    <th className="px-5 py-2 font-medium">Trámite</th>
                                    <th className="px-5 py-2 font-medium">Estado</th>
                                    <th className="px-5 py-2 text-right font-medium">Monto</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border">
                                {tramites.map((t) => (
                                    <tr key={t.id} className="hover:bg-muted/30">
                                        <td className="whitespace-nowrap px-5 py-2.5 font-mono text-xs">
                                            {t.codigo}
                                            <span className="block text-[11px] text-muted-foreground">
                                                {fechaHora(t.creado)}
                                            </span>
                                        </td>

                                        <td className="px-5 py-2.5">
                                            {t.solicitante}
                                            <span className="block text-xs text-muted-foreground">
                                                CI/NIT {t.ci_nit}
                                            </span>
                                        </td>

                                        <td className="px-5 py-2.5">
                                            <span className="mr-1">{t.icono}</span>
                                            {t.tipo}
                                            <span className="block text-xs text-muted-foreground">
                                                {t.area}
                                            </span>
                                        </td>

                                        <td className="px-5 py-2.5">
                                            <Badge color={t.estado_color}>{t.estado_etiqueta}</Badge>
                                        </td>

                                        <td className="whitespace-nowrap px-5 py-2.5 text-right tabular-nums">
                                            {bs(t.monto_total)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

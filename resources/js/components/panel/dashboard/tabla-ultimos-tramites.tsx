import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { bs, fecha } from '@/lib/utils';
import type { UltimoTramite } from '@/types/dashboard';

/**
 * Los últimos diez expedientes que entraron.
 *
 * Se muestra el SALDO y no el monto total: lo que le interesa a quien mira el
 * tablero es qué falta cobrar, no cuánto costaba el trámite. Un expediente
 * cubierto se ve de un vistazo porque dice «Pagado» en verde.
 */
export function TablaUltimosTramites({ tramites, moneda }: { tramites: UltimoTramite[]; moneda: string }) {
    return (
        /*
         * `min-w-0` NO ES DECORACIÓN, y sin él el `overflow-x-auto` de abajo no
         * sirve para nada.
         *
         * Un elemento dentro de una grilla arranca con `min-width: auto`, que
         * significa «no te encojas por debajo de tu contenido». El contenido acá
         * es una tabla de seis columnas que mide 675 px, así que la tarjeta se
         * estira a 675 px aunque la pantalla tenga 375: el que termina con
         * barra de desplazamiento es el DOCUMENTO ENTERO, y en un celular se
         * corre de costado la pantalla completa —menú, encabezado y todo— para
         * leer una columna.
         *
         * `min-w-0` devuelve el permiso de encogerse. Recién entonces la
         * tarjeta se queda en 375, la tabla desborda DENTRO suyo y el
         * `overflow-x-auto` la hace desplazable sola, que era la intención.
         */
        <Card className="min-w-0 lg:col-span-3">
            <CardHeader>
                <CardTitle>Últimos trámites</CardTitle>
            </CardHeader>

            <CardContent className="p-0">
                {tramites.length === 0 ? (
                    <p className="px-5 py-8 text-center text-sm text-muted-foreground">
                        Todavía no se registró ningún trámite.
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-2.5 font-medium">Beneficiario</th>
                                    <th className="px-5 py-2.5 font-medium">Rubro</th>
                                    <th className="px-5 py-2.5 font-medium">Tipo</th>
                                    <th className="px-5 py-2.5 font-medium">Estado</th>
                                    <th className="px-5 py-2.5 text-right font-medium">Saldo</th>
                                    <th className="px-5 py-2.5 font-medium">Fecha</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border">
                                {tramites.map((t) => (
                                    <tr key={t.id} className="hover:bg-secondary/50">
                                        <td className="px-5 py-2.5">
                                            <Link
                                                href={route('tramites.show', t.id)}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {t.beneficiario ?? '—'}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {t.carnet_registro ?? '—'}
                                            </p>
                                        </td>
                                        <td className="px-5 py-2.5">{t.rubro ?? '—'}</td>
                                        <td className="px-5 py-2.5">
                                            <Badge color={t.tipo_color}>{t.tipo_etiqueta}</Badge>
                                        </td>
                                        <td className="px-5 py-2.5">
                                            <Badge color={t.estado_color}>{t.estado_etiqueta}</Badge>
                                        </td>
                                        <td className="px-5 py-2.5 text-right tabular-nums">
                                            {t.saldo_pendiente > 0 ? (
                                                <span className="text-amber-700 dark:text-amber-400">
                                                    {bs(t.saldo_pendiente, moneda)}
                                                </span>
                                            ) : (
                                                <span className="text-emerald-700 dark:text-emerald-400">
                                                    Pagado
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-2.5 text-muted-foreground">
                                            {fecha(t.fecha_solicitud)}
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

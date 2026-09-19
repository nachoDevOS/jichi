import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { bs, fecha } from '@/lib/utils';
import type { UltimoCarnet } from '@/types/dashboard';

/**
 * Las últimas diez credenciales emitidas.
 *
 * Se muestra el SALDO y no el precio: lo que le interesa a quien mira el
 * tablero es qué falta cobrar, no cuánto salía el carnet. Uno cubierto se ve de
 * un vistazo porque dice «Pagado» en verde.
 */
export function TablaUltimosCarnets({ carnets, moneda }: { carnets: UltimoCarnet[]; moneda: string }) {
    return (
        /*
         * `min-w-0` NO ES DECORACIÓN, y sin él el `overflow-x-auto` de abajo no
         * sirve para nada.
         *
         * Un elemento dentro de una grilla arranca con `min-width: auto`, que
         * significa «no te encojas por debajo de tu contenido». El contenido acá
         * es una tabla de seis columnas que mide unos 675 px, así que la tarjeta
         * se estira a 675 px aunque la pantalla tenga 375: el que termina con
         * barra de desplazamiento es el DOCUMENTO ENTERO, y en un celular se
         * corre de costado la pantalla completa —menú, encabezado y todo— para
         * leer una columna.
         *
         * `min-w-0` devuelve el permiso de encogerse. Recién entonces la
         * tarjeta se queda en 375, la tabla desborda DENTRO suyo y el
         * `overflow-x-auto` la hace desplazable sola, que era la intención.
         *
         * NO SE NOTA EN EL ESCRITORIO, que es donde se prueba: aparece solo al
         * angostar la ventana.
         */
        <Card className="min-w-0 lg:col-span-3">
            <CardHeader>
                <CardTitle>Últimos carnets emitidos</CardTitle>
            </CardHeader>

            <CardContent className="p-0">
                {carnets.length === 0 ? (
                    <p className="px-5 py-8 text-center text-sm text-muted-foreground">
                        Todavía no se emitió ningún carnet.
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-2.5 font-medium">Beneficiario</th>
                                    <th className="px-5 py-2.5 font-medium">Asociación</th>
                                    <th className="px-5 py-2.5 font-medium">Actividad</th>
                                    <th className="px-5 py-2.5 font-medium">Estado</th>
                                    <th className="px-5 py-2.5 text-right font-medium">Saldo</th>
                                    <th className="px-5 py-2.5 font-medium">Emisión</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border">
                                {carnets.map((c) => (
                                    <tr key={c.id} className="hover:bg-secondary/50">
                                        <td className="px-5 py-2.5">
                                            <Link
                                                href={route('carnets.show', c.id)}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {c.beneficiario ?? '—'}
                                            </Link>
                                            {/*
                                                El código va en grupos de cuatro —lo arma
                                                el servidor con `codigoLegible`— porque
                                                catorce caracteres seguidos no se pueden
                                                dictar por teléfono ni tipear de un
                                                plástico gastado.
                                            */}
                                            <p className="font-mono text-xs text-muted-foreground">
                                                {c.codigo}
                                            </p>
                                        </td>
                                        <td className="px-5 py-2.5">{c.asociacion ?? '—'}</td>
                                        <td className="px-5 py-2.5">
                                            <Badge color={c.tipo_actor_color}>{c.tipo_actor_etiqueta}</Badge>
                                        </td>
                                        <td className="px-5 py-2.5">
                                            <Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>
                                        </td>
                                        <td className="px-5 py-2.5 text-right tabular-nums">
                                            {c.saldo_pendiente > 0 ? (
                                                <span className="text-amber-700 dark:text-amber-400">
                                                    {bs(c.saldo_pendiente, moneda)}
                                                </span>
                                            ) : (
                                                <span className="text-emerald-700 dark:text-emerald-400">
                                                    Pagado
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-2.5 text-muted-foreground">
                                            {fecha(c.fecha_emision)}
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

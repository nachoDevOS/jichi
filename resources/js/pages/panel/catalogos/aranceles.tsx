import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Coins, History, Link2, ListOrdered, Pencil, Plus, RefreshCw, TriangleAlert } from 'lucide-react';
import { Fragment, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { ArancelFila } from '@/types/catalogos';

/**
 *  Aranceles de SIREB — qué servicio cobra cada trámite. Solo el servicio: la
 *  tarifa la elige ventanilla al emitir
 */
export default function CatalogoAranceles({
    aranceles,
    sirebDisponible,
}: {
    aranceles: ArancelFila[];
    /** false = SIREB no respondió: los nombres de los servicios no se pueden mostrar. */
    sirebDisponible: boolean;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [actualizando, setActualizando] = useState(false);
    // La fila con sus tarifas desplegadas: una a la vez.
    const [abierto, setAbierto] = useState<number | null>(null);

    // Lo cambiado en SIREB se ve sin esperar a que venza la caché del catálogo.
    function actualizar() {
        router.post(
            route('aranceles.refrescar'),
            {},
            {
                preserveScroll: true,
                onStart: () => setActualizando(true),
                onFinish: () => setActualizando(false),
            },
        );
    }

    return (
        <LayoutPanel
            titulo="Aranceles de SIREB"
            descripcion="Lo que se cobra en Recaudaciones (SIREB): un nombre y su servicio."
            acciones={
                <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" onClick={actualizar} disabled={actualizando}>
                        <RefreshCw className={cn('size-4', actualizando && 'animate-spin')} />
                        Actualizar desde SIREB
                    </Button>

                    {puede('catalogos.crear') && (
                        <Link href={route('aranceles.create')} className={cn(buttonVariants())}>
                            <Plus className="size-4" />
                            Registrar
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Aranceles de SIREB" />

            <div className="space-y-6">
                {!sirebDisponible && (
                    <Card className="border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
                        <CardContent className="flex items-start gap-3 pt-5 text-sm">
                            <TriangleAlert className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
                            <p className="text-amber-900 dark:text-amber-200">
                                Recaudaciones (SIREB) no responde: se ven los ids, pero no el nombre de cada servicio.
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
                                descripcion="Faltan las filas de los trámites: se crean con el seeder de catálogos."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">Documento</th>
                                            <th className="px-5 py-2.5 font-medium">Servicio SIREB</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Tarifas</th>
                                            <th className="px-5 py-2.5" />
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {aranceles.map((a) => (
                                            <Fragment key={a.id}>
                                                <tr className="hover:bg-secondary/50">
                                                    <td className="px-5 py-2.5">
                                                        <p className="font-medium">{a.etiqueta}</p>
                                                        <p className="text-xs text-muted-foreground">{a.descripcion}</p>
                                                        {a.usa_tarifas_definidas && (
                                                            <p className="text-xs text-muted-foreground">
                                                                {a.tarifas_definidas === 0
                                                                    ? 'Sin tarifas definidas'
                                                                    : `${a.tarifas_definidas} tarifa(s) definida(s)`}
                                                            </p>
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-2.5" title={a.servicio_sireb ?? undefined}>
                                                        {a.servicio_sireb === null ? (
                                                            <Badge color="amber">Sin servicio</Badge>
                                                        ) : a.servicio !== null ? (
                                                            <>
                                                                <p>{a.servicio.nombre}</p>
                                                                {!a.servicio.activo && (
                                                                    <Badge color="slate">De baja en SIREB</Badge>
                                                                )}
                                                            </>
                                                        ) : (
                                                            // Sin SIREB, o ya no lo tiene: queda el id.
                                                            <p className="break-all font-mono text-xs text-muted-foreground">
                                                                {a.servicio_sireb}
                                                            </p>
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-2.5 text-right tabular-nums">
                                                        {a.servicio !== null ? a.servicio.tarifas : '—'}
                                                    </td>

                                                    <td className="px-5 py-2.5 text-right">
                                                        <div className="flex justify-end gap-2">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setAbierto(abierto === a.id ? null : a.id)
                                                                }
                                                                disabled={a.servicio === null}
                                                                aria-expanded={abierto === a.id}
                                                                title="Ver tarifas"
                                                            >
                                                                <ListOrdered className="size-4" />
                                                                <ChevronDown
                                                                    className={cn(
                                                                        'size-3.5 transition-transform',
                                                                        abierto === a.id && 'rotate-180',
                                                                    )}
                                                                />
                                                            </Button>

                                                            <Link
                                                                href={route('aranceles.show', a.id)}
                                                                className={cn(
                                                                    buttonVariants({
                                                                        variant: 'ver',
                                                                        size: 'sm',
                                                                    }),
                                                                )}
                                                                title="Historial de SIREB"
                                                            >
                                                                <History className="size-4" />
                                                            </Link>

                                                            {puede('catalogos.editar') && a.usa_tarifas_definidas && (
                                                                <Link
                                                                    href={route('aranceles.tarifas', a.id)}
                                                                    className={cn(buttonVariants({ variant: 'outline', size: 'sm' }))}
                                                                    title="Definir tarifas"
                                                                >
                                                                    <Link2 className="size-4" />
                                                                </Link>
                                                            )}

                                                            {puede('catalogos.editar') && (
                                                                <Link
                                                                    href={route('aranceles.edit', a.id)}
                                                                    className={cn(
                                                                        buttonVariants({
                                                                            variant: 'editar',
                                                                            size: 'sm',
                                                                        }),
                                                                    )}
                                                                    title="Editar"
                                                                >
                                                                    <Pencil className="size-4" />
                                                                </Link>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>

                                                {abierto === a.id && (
                                                    <tr className="bg-secondary/30">
                                                        <td colSpan={4} className="px-5 py-3">
                                                            <TablaTarifas arancel={a} moneda={institucion.moneda} />
                                                        </td>
                                                    </tr>
                                                )}
                                            </Fragment>
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

/** Las tarifas del servicio, como las manda SIREB hoy: etiqueta, precio y si está activa. */
function TablaTarifas({ arancel, moneda }: { arancel: ArancelFila; moneda: string }) {
    if (arancel.tarifas.length === 0) {
        return <p className="text-sm text-muted-foreground">El servicio no tiene tarifas en SIREB.</p>;
    }

    return (
        <div className="min-w-0 overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th className="py-1.5 pr-4 font-medium">Tarifa</th>
                        <th className="py-1.5 pr-4 font-medium">Rango</th>
                        <th className="py-1.5 pr-4 text-right font-medium">Precio</th>
                        <th className="py-1.5 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-border/60">
                    {arancel.tarifas.map((t) => (
                        <tr key={t.id} title={t.id}>
                            <td className="py-1.5 pr-4">{t.etiqueta || 'Sin etiqueta'}</td>
                            {/* Tal como lo manda SIREB: hoy llega vacío. */}
                            <td className="py-1.5 pr-4 tabular-nums text-muted-foreground">
                                {t.rango_desde === null && t.rango_hasta === null
                                    ? '—'
                                    : `${t.rango_desde ?? '…'} – ${t.rango_hasta ?? '…'}`}
                            </td>
                            <td className="py-1.5 pr-4 text-right tabular-nums">{bs(t.monto, moneda)}</td>
                            <td className="py-1.5">
                                {t.activa ? (
                                    <Badge color="emerald">Activa</Badge>
                                ) : (
                                    <Badge color="slate">Inactiva en SIREB</Badge>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

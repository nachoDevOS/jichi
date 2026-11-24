import { Head, Link, router } from '@inertiajs/react';
import { FileSpreadsheet, FileText, TableProperties } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha } from '@/lib/utils';
import type { Paginado } from '@/types';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Columna {
    clave: string;
    etiqueta: string;
    formato: 'texto' | 'fecha' | 'bs' | 'kg';
}

interface Filtros {
    servicio: string;
    /** Días sueltos 'AAAA-MM-DD'. */
    desde: string;
    hasta: string;
    estado: string | null;
}

type Fila = Record<string, string | number | null> & { _url: string; estado_color: string };

/**
 *  REPORTES — el detalle de un tipo de servicio en un rango de fechas. Lo arma
 *  ReportePorServicioService; el Excel y el PDF salen con los mismos filtros.
 */
export default function Reportes({
    servicios,
    estados,
    filtros,
    columnas,
    totales,
    filas,
}: {
    servicios: Opcion[];
    estados: Opcion[];
    filtros: Filtros;
    columnas: Columna[];
    totales: { cantidad: number; monto: number; cobrado: number; por_cobrar: number };
    filas: Paginado<Fila>;
}) {
    function filtrar(cambios: Partial<Filtros>) {
        const nuevos = { ...filtros, ...cambios };
        // Cada servicio tiene sus estados: al cambiar de servicio, el estado elegido ya no aplica.
        if (cambios.servicio) nuevos.estado = null;
        router.get(route('reportes.index'), limpio(nuevos), { preserveScroll: true, replace: true });
    }

    const nombre = servicios.find((s) => s.valor === filtros.servicio)?.etiqueta ?? '';

    return (
        <LayoutPanel titulo="Reportes" descripcion="El detalle de cada tipo de servicio en el rango de fechas que elija.">
            <Head title={`Reporte de ${nombre}`} />

            <div className="space-y-6">
                <Card className="min-w-0">
                    <CardContent className="flex flex-wrap items-end gap-4 p-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="servicio">Tipo de servicio</Label>
                            <Select id="servicio" className="w-56" value={filtros.servicio} onChange={(e) => filtrar({ servicio: e.target.value })}>
                                {servicios.map((s) => (
                                    <option key={s.valor} value={s.valor}>
                                        {s.etiqueta}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="desde">Desde</Label>
                            <Input id="desde" type="date" className="w-auto" value={filtros.desde} onChange={(e) => e.target.value && filtrar({ desde: e.target.value })} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="hasta">Hasta</Label>
                            <Input id="hasta" type="date" className="w-auto" value={filtros.hasta} onChange={(e) => e.target.value && filtrar({ hasta: e.target.value })} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="estado">Estado</Label>
                            <Select id="estado" className="w-44" value={filtros.estado ?? ''} onChange={(e) => filtrar({ estado: e.target.value || null })}>
                                <option value="">Todos</option>
                                {estados.map((e) => (
                                    <option key={e.valor} value={e.valor}>
                                        {e.etiqueta}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div className="ml-auto flex gap-2">
                            <a href={route('reportes.excel', limpio(filtros))} className={buttonVariants({ variant: 'outline' })}>
                                <FileSpreadsheet />
                                Excel
                            </a>
                            <a href={route('reportes.pdf', limpio(filtros))} target="_blank" rel="noopener" className={buttonVariants()}>
                                <FileText />
                                PDF
                            </a>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Total rotulo="Trámites" valor={totales.cantidad.toLocaleString('es-BO')} />
                    <Total rotulo="Monto liquidado" valor={bs(totales.monto)} />
                    <Total rotulo="Cobrado (con recibo)" valor={bs(totales.cobrado)} tono="text-emerald-700 dark:text-emerald-300" />
                    <Total rotulo="Por cobrar (pendientes)" valor={bs(totales.por_cobrar)} tono="text-amber-700 dark:text-amber-300" />
                </div>

                {/* `min-w-0`: sin él la tarjeta se estira al ancho de la tabla y el celular se corre de costado. */}
                <Card className="min-w-0">
                    <CardContent className="p-0">
                        {filas.data.length === 0 ? (
                            <EstadoVacio
                                icono={TableProperties}
                                titulo="Sin trámites"
                                descripcion={`Ningún trámite de ${nombre.toLowerCase()} entre el ${fecha(filtros.desde)} y el ${fecha(filtros.hasta)}.`}
                            />
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-[13px]">
                                        <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                {columnas.map((c) => (
                                                    <th key={c.clave} className={cn('px-4 py-2.5 font-medium whitespace-nowrap', numerica(c) && 'text-right')}>
                                                        {c.etiqueta}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filas.data.map((f) => (
                                                <tr key={f._url} className="border-b border-border/60 last:border-0 hover:bg-secondary/50">
                                                    {columnas.map((c) => (
                                                        <td key={c.clave} className={cn('px-4 py-2.5', numerica(c) && 'text-right tabular-nums whitespace-nowrap')}>
                                                            <Celda fila={f} columna={c} />
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot className="border-t-2 border-border font-semibold">
                                            <tr>
                                                {columnas.map((c, i) => (
                                                    <td key={c.clave} className={cn('px-4 py-2.5', numerica(c) && 'text-right tabular-nums')}>
                                                        {i === 0 ? `Total (${totales.cantidad})` : c.clave === 'monto' ? bs(totales.monto) : ''}
                                                    </td>
                                                ))}
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <Paginacion paginado={filas} />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </LayoutPanel>
    );
}

/** Los filtros sin los vacíos: la URL queda limpia y se puede compartir. */
function limpio(filtros: Filtros): Record<string, string> {
    return Object.fromEntries(Object.entries(filtros).filter(([, v]) => v)) as Record<string, string>;
}

function numerica(c: Columna): boolean {
    return c.formato === 'bs' || c.formato === 'kg';
}

function Celda({ fila, columna }: { fila: Fila; columna: Columna }) {
    const valor = fila[columna.clave];

    if (columna.clave === 'numero') {
        return (
            <Link href={fila._url} className="font-mono font-medium text-primary hover:underline">
                {valor}
            </Link>
        );
    }
    if (columna.clave === 'estado') return <Badge color={fila.estado_color}>{valor}</Badge>;
    if (valor === null || valor === '') return <span className="text-muted-foreground">—</span>;
    if (columna.formato === 'fecha') return <span className="whitespace-nowrap">{fecha(String(valor))}</span>;
    if (columna.formato === 'bs') return <>{bs(Number(valor))}</>;
    if (columna.formato === 'kg') return <>{Number(valor).toLocaleString('es-BO', { maximumFractionDigits: 2 })} kg</>;

    return <>{valor}</>;
}

function Total({ rotulo, valor, tono }: { rotulo: string; valor: string; tono?: string }) {
    return (
        <Card className="min-w-0">
            <CardContent className="p-4">
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{rotulo}</p>
                <p className={cn('mt-1 text-2xl font-semibold tabular-nums', tono)}>{valor}</p>
            </CardContent>
        </Card>
    );
}

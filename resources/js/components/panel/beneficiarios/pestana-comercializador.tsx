import { Link } from '@inertiajs/react';
import { Truck } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { bs, fechaHora } from '@/lib/utils';
import type { CarnetResumen, GuiaDelBeneficiario } from '@/types/beneficiarios';
import { AvisoRegistroAnterior, CredencialMini, Seccion, TarjetaPeriodo } from './partes-ficha';

/**
 * Todo lo del comercializador: su cédula y sus guías de traslado.
 */
export function PestanaComercializador({
    carnets,
    guias,
    moneda,
}: {
    carnets: CarnetResumen[];
    guias: GuiaDelBeneficiario[];
    moneda: string;
}) {
    // Cada gestión es una cédula nueva: la cédula ES el período, y sus guías cuelgan de ella.
    const propios = carnets.filter((c) => c.tipo_actor === 'comercializador');
    const vigente = propios.find((c) => c.vigente) ?? propios[0] ?? null;
    const [elegido, setElegido] = useState<number | null>(vigente?.id ?? null);
    const actual = propios.find((c) => c.id === elegido) ?? vigente;
    const esVigente = actual?.id === vigente?.id;
    const guiasDelPeriodo = guias.filter((g) => g.carnet_id === actual?.id);

    return (
        <div className="space-y-6">
            {propios.length > 1 && (
                <Seccion titulo={`Períodos (${propios.length})`}>
                    <div className="flex gap-3 overflow-x-auto pb-1 [scrollbar-width:thin]">
                        {propios.map((c) => {
                            const suyas = guias.filter((g) => g.carnet_id === c.id);
                            return (
                                <TarjetaPeriodo
                                    key={c.id}
                                    gestion={gestionDe(c)}
                                    esActual={c.id === vigente?.id}
                                    activo={c.id === actual?.id}
                                    insignia={<Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>}
                                    titulo={`Cédula N° ${c.registro ?? '—'}`}
                                    detalle={`${suyas.length} ${suyas.length === 1 ? 'guía' : 'guías'} · ${suyas.reduce((t, g) => t + g.peso_total_kg, 0)} kg`}
                                    onClick={() => setElegido(c.id)}
                                />
                            );
                        })}
                    </div>
                </Seccion>
            )}

            {actual && !esVigente && (
                <AvisoRegistroAnterior gestion={gestionDe(actual)} onVolver={() => setElegido(vigente?.id ?? null)} />
            )}

            <div className="space-y-3 lg:max-w-md">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Cédula</h3>
                {actual ? (
                    <CredencialMini carnet={actual} moneda={moneda} />
                ) : (
                    <div className="flex h-40 items-center justify-center rounded-xl border-2 border-dashed border-border text-sm text-muted-foreground">
                        Todavía no tiene cédula de comercializador
                    </div>
                )}
            </div>

            <Seccion titulo="Guías de traslado">
                {guiasDelPeriodo.length === 0 ? (
                    <div className="rounded-lg border border-border">
                        <EstadoVacio icono={Truck} titulo="Sin guías" descripcion="Todavía no trasladó producto con guía." />
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border bg-secondary/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2.5 font-medium">N°</th>
                                    <th className="px-4 py-2.5 font-medium">Ruta</th>
                                    <th className="px-4 py-2.5 text-right font-medium">Peso</th>
                                    <th className="px-4 py-2.5 font-medium">Vence</th>
                                    <th className="px-4 py-2.5 text-right font-medium">Arancel</th>
                                    <th className="px-4 py-2.5 font-medium">Estado</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {guiasDelPeriodo.map((g) => (
                                    <tr key={g.id} className="hover:bg-secondary/50">
                                        <td className="px-4 py-2.5">
                                            <Link
                                                href={route('guias.show', g.id)}
                                                className="font-mono font-medium tabular-nums text-primary hover:underline"
                                            >
                                                {g.numero_legible}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-2.5">{g.ruta}</td>
                                        <td className="px-4 py-2.5 text-right tabular-nums">{g.peso_total_kg} kg</td>
                                        {/* Vale por horas: va con la hora, no solo el día. */}
                                        <td className="px-4 py-2.5 text-muted-foreground">{fechaHora(g.fecha_vencimiento) || '—'}</td>
                                        <td className="px-4 py-2.5 text-right tabular-nums">
                                            {bs(g.monto, moneda)}
                                            {g.debe > 0 && (
                                                <p className="text-xs text-amber-700 dark:text-amber-300">faltan {bs(g.debe, moneda)}</p>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <Badge color={g.estado_color}>{g.estado_etiqueta}</Badge>
                                            {g.caducada && (
                                                <Badge color="amber" className="ml-1">
                                                    sin cerrar
                                                </Badge>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Seccion>

        </div>
    );
}

/** La gestión de la cédula: el año en que se emitió, o en que se pidió. */
function gestionDe(c: CarnetResumen): number | null {
    const base = c.fecha_emision ?? c.fecha_solicitud;
    return base ? Number(base.slice(0, 4)) : null;
}

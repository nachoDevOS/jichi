import { Link } from '@inertiajs/react';
import { ArrowRight, Eye, Fish, Printer, Route as IconoRuta, Scale, Truck, Wallet } from 'lucide-react';
import { useState } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import type { TooltipContentProps } from 'recharts';
import type { NameType, ValueType } from 'recharts/types/component/DefaultTooltipContent';
import { EnlacePermitido } from '@/components/panel/comunes/enlace-permitido';
import { abrirDirecto, Conteo, kg, Tabla, Td } from '@/components/panel/comunes/ficha';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { useMontado, useReducirMovimiento } from '@/hooks/use-movimiento';
import { usePermisos } from '@/hooks/use-permisos';
import { ESTILO_TOOLTIP } from '@/lib/graficos';
import { bs, cn, fecha, fechaHora } from '@/lib/utils';
import type { CarnetResumen, GuiaDelBeneficiario } from '@/types/beneficiarios';
import {
    AvisoRegistroAnterior,
    CarnetReplica,
    PieCarnet,
    Seccion,
    TarjetaPeriodo,
    TiraCifras,
    type Titular,
} from './partes-ficha';

// Igual que el gráfico de la autorización: aprobada en petróleo, esperando el pago en gris.
const COLOR_APROBADA = 'var(--primary)';
const COLOR_PENDIENTE = '#94a3b8';

/**
 * Todo lo del comercializador: su cédula, cuánto traslada, por dónde y a quién le compra.
 */
export function PestanaComercializador({
    titular,
    carnets,
    guias,
    moneda,
}: {
    titular: Titular;
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
    const delPeriodo = guias.filter((g) => g.carnet_id === actual?.id);

    if (actual === null) {
        return (
            <div className="rounded-lg border border-border bg-card">
                <EstadoVacio
                    icono={Truck}
                    titulo="Sin actividad de comercialización"
                    descripcion="Todavía no tiene cédula de comercializador."
                />
            </div>
        );
    }

    const kilos = delPeriodo.reduce((t, g) => t + g.peso_total_kg, 0);
    const pescadores = new Set(delPeriodo.map((g) => g.pescador).filter(Boolean)).size;
    const cobrado = delPeriodo.reduce((t, g) => t + g.monto, 0);
    const debe = delPeriodo.reduce((t, g) => t + g.debe, 0);
    const enCamino = delPeriodo.filter((g) => g.vigente).length;

    return (
        <div className="space-y-5">
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
                                    activo={c.id === actual.id}
                                    insignia={<Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>}
                                    titulo={`Cédula N° ${c.registro ?? '—'}`}
                                    detalle={`${suyas.length} ${suyas.length === 1 ? 'guía' : 'guías'} · ${kg(suyas.reduce((t, g) => t + g.peso_total_kg, 0))}`}
                                    onClick={() => setElegido(c.id)}
                                />
                            );
                        })}
                    </div>
                </Seccion>
            )}

            {!esVigente && (
                <AvisoRegistroAnterior gestion={gestionDe(actual)} onVolver={() => setElegido(vigente?.id ?? null)} />
            )}

            <TiraCifras
                cifras={[
                    {
                        icono: Truck,
                        etiqueta: 'Guías',
                        valor: <Conteo valor={delPeriodo.length} formato={(n) => String(n)} />,
                        pie: enCamino > 0 ? `${enCamino} en camino hoy` : 'ninguna en camino hoy',
                    },
                    {
                        icono: Scale,
                        etiqueta: 'Kilos trasladados',
                        valor: <Conteo valor={kilos} formato={kg} />,
                        pie: 'la suma de sus guías',
                    },
                    {
                        icono: Fish,
                        etiqueta: 'Pescadores',
                        valor: <Conteo valor={pescadores} formato={(n) => String(n)} />,
                        pie: 'a quienes les compró',
                    },
                    {
                        icono: Wallet,
                        etiqueta: 'Aranceles',
                        valor: bs(cobrado, moneda),
                        pie: debe > 0 ? `debe ${bs(debe, moneda)}` : 'todo pagado',
                        tono: debe > 0 ? 'aviso' : 'normal',
                    },
                ]}
            />

            <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                <TarjetaTraslados guias={delPeriodo} />

                <div className="min-w-0 space-y-3">
                    <CarnetReplica carnet={actual} titular={titular} />
                    <PieCarnet carnet={actual} moneda={moneda} />
                </div>
            </div>

            <Seccion titulo="Guías de transporte">
                {delPeriodo.length === 0 ? (
                    <div className="rounded-lg border border-border bg-card">
                        <EstadoVacio
                            icono={Truck}
                            titulo="Sin guías"
                            descripcion="Todavía no trasladó producto con guía."
                        />
                    </div>
                ) : (
                    <Card className="min-w-0 overflow-hidden p-0">
                        <Tabla columnas={['N°', 'Ruta', 'Pescado de', '>Peso', 'Vence', '>Arancel', 'Estado', '']}>
                            {delPeriodo.map((g) => (
                                <FilaGuia key={g.id} guia={g} moneda={moneda} />
                            ))}
                        </Tabla>
                    </Card>
                )}
            </Seccion>
        </div>
    );
}

/** Kilos por guía en el gráfico, y debajo por dónde traslada y a quién le compra. */
function TarjetaTraslados({ guias }: { guias: GuiaDelBeneficiario[] }) {
    return (
        <Card className="min-w-0 overflow-hidden p-0">
            <div className="flex items-center gap-2 border-b border-border px-5 py-3">
                <Truck className="size-4 text-primary" />
                <span className="text-sm font-semibold">Traslados</span>
            </div>

            {guias.length === 0 ? (
                <p className="m-5 rounded-lg border border-dashed border-border p-4 text-center text-sm text-muted-foreground">
                    Cuando registre guías, acá se ven sus kilos, sus rutas y a quién le compró.
                </p>
            ) : (
                <>
                    <div className="px-5 py-4">
                        <GraficoTraslados guias={guias} />
                    </div>
                    <div className="grid border-t border-border md:grid-cols-2">
                        <Ranking
                            titulo="Por dónde traslada"
                            icono={IconoRuta}
                            filas={agrupar(guias, (g) => `${g.origen ?? '—'} → ${g.destino ?? '—'}`)}
                        />
                        <Ranking
                            titulo="A quién le compró"
                            icono={Fish}
                            filas={agrupar(guias, (g) => g.pescador ?? 'Sin pescador declarado')}
                            className="border-t border-border md:border-t-0 md:border-l"
                        />
                    </div>
                </>
            )}
        </Card>
    );
}

type FilaRanking = { nombre: string; guias: number; kilos: number };

function agrupar(guias: GuiaDelBeneficiario[], clave: (g: GuiaDelBeneficiario) => string): FilaRanking[] {
    const mapa = new Map<string, FilaRanking>();
    for (const g of guias) {
        const nombre = clave(g);
        const fila = mapa.get(nombre) ?? { nombre, guias: 0, kilos: 0 };
        fila.guias += 1;
        fila.kilos += g.peso_total_kg;
        mapa.set(nombre, fila);
    }
    return [...mapa.values()].sort((a, b) => b.kilos - a.kilos).slice(0, 4);
}

/** Los primeros cuatro, con una barra por kilos: se ve el circuito de un vistazo. */
function Ranking({
    titulo,
    icono: Icono,
    filas,
    className,
}: {
    titulo: string;
    icono: typeof Fish;
    filas: FilaRanking[];
    className?: string;
}) {
    const montado = useMontado();
    const mayor = filas[0]?.kilos || 1;

    return (
        <div className={cn('space-y-2.5 px-5 py-4', className)}>
            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">{titulo}</p>
            {filas.map((f, i) => (
                <div key={f.nombre} className="space-y-1">
                    <div className="flex items-center gap-2 text-sm">
                        <Icono className="size-3.5 shrink-0 text-primary" />
                        <span className="min-w-0 flex-1 truncate">{f.nombre}</span>
                        <span className="font-medium tabular-nums">{kg(f.kilos)}</span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full bg-secondary">
                        <div
                            className="h-full rounded-full bg-gradient-to-r from-rio-claro to-rio transition-[width] duration-1000 ease-out motion-reduce:transition-none"
                            style={{
                                width: montado ? `${(f.kilos / mayor) * 100}%` : '0%',
                                transitionDelay: `${i * 120}ms`,
                            }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

/** Una barra por guía, de la más vieja a la más nueva: cuánto llevó cada traslado. */
function GraficoTraslados({ guias }: { guias: GuiaDelBeneficiario[] }) {
    const reducir = useReducirMovimiento();
    const datos = [...guias].sort((a, b) => a.numero_legible.localeCompare(b.numero_legible));

    return (
        <figure className="space-y-3">
            <figcaption className="flex flex-wrap items-baseline justify-between gap-2">
                <span className="text-sm font-medium">Kilos por guía</span>
                <span className="flex items-center gap-4 text-xs text-muted-foreground">
                    <span className="flex items-center gap-1.5">
                        <i className="size-2.5 rounded-full bg-primary" aria-hidden />
                        Aprobada
                    </span>
                    <span className="flex items-center gap-1.5">
                        <i className="size-2.5 rounded-full bg-slate-400" aria-hidden />
                        Esperando el pago
                    </span>
                </span>
            </figcaption>

            {/* El contenedor necesita altura propia: sin ella recharts dibuja en cero. */}
            <div className="h-44">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={datos} margin={{ top: 8, right: 12, bottom: 0, left: -8 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                        <XAxis
                            dataKey="numero_legible"
                            tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                            tickLine={false}
                            axisLine={false}
                        />
                        <YAxis
                            tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                            tickFormatter={(v: number) => v.toLocaleString('es-BO')}
                            tickLine={false}
                            axisLine={false}
                            width={56}
                        />
                        <Tooltip content={Globo} cursor={{ fill: 'var(--secondary)' }} />
                        <Bar
                            dataKey="peso_total_kg"
                            radius={[4, 4, 0, 0]}
                            maxBarSize={48}
                            isAnimationActive={!reducir}
                            animationDuration={1000}
                        >
                            {datos.map((g) => (
                                <Cell key={g.id} fill={g.estado === 'pendiente' ? COLOR_PENDIENTE : COLOR_APROBADA} />
                            ))}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </figure>
    );
}

/** El globo al pasar el mouse: la guía, su ruta, de quién era el pescado y cuánto llevó. */
function Globo({ active, payload }: TooltipContentProps<ValueType, NameType>) {
    const g = payload?.[0]?.payload as GuiaDelBeneficiario | undefined;

    if (!active || !g) {
        return null;
    }

    return (
        <div style={ESTILO_TOOLTIP} className="space-y-0.5 px-3 py-2 shadow-md">
            <p className="font-semibold">Guía {g.numero_legible}</p>
            {g.fecha_solicitud && <p className="text-muted-foreground">{fecha(g.fecha_solicitud)}</p>}
            <p>
                {g.origen ?? '—'} → {g.destino ?? '—'}
            </p>
            {g.pescador && <p>Pescado de: {g.pescador}</p>}
            <p className="font-medium">
                {kg(g.peso_total_kg)}
                {g.estado === 'pendiente' && (
                    <span className="font-normal text-muted-foreground"> (esperando el pago)</span>
                )}
            </p>
        </div>
    );
}

function FilaGuia({ guia: g, moneda }: { guia: GuiaDelBeneficiario; moneda: string }) {
    const { puede } = usePermisos();

    return (
        <tr className="transition-colors hover:bg-secondary/50">
            <Td>
                <EnlacePermitido
                    permiso="guias.ver"
                    href={route('guias.show', g.id)}
                    className="font-mono font-medium text-primary tabular-nums hover:underline"
                >
                    {g.numero_legible}
                </EnlacePermitido>
            </Td>
            <Td>
                <span className="inline-flex items-center gap-1.5 whitespace-nowrap">
                    {g.origen ?? '—'}
                    <ArrowRight className="size-3.5 text-muted-foreground" />
                    {g.destino ?? '—'}
                </span>
            </Td>
            <Td apagada={!g.pescador}>{g.pescador ?? '—'}</Td>
            <Td derecha>
                <span className="font-medium whitespace-nowrap">{kg(g.peso_total_kg)}</span>
            </Td>
            {/* Vale por horas: va con la hora, no solo el día. */}
            <Td>
                <span className="whitespace-nowrap">{fechaHora(g.fecha_vencimiento) || '—'}</span>
                {g.vigente && <p className="text-xs text-emerald-700 dark:text-emerald-400">en camino</p>}
            </Td>
            <Td derecha>
                <span className="whitespace-nowrap">{bs(g.monto, moneda)}</span>
                {g.debe > 0 ? (
                    <p className="text-xs text-amber-700 dark:text-amber-300">faltan {bs(g.debe, moneda)}</p>
                ) : (
                    <p className="text-xs text-emerald-700 dark:text-emerald-400">Pagado</p>
                )}
            </Td>
            <Td>
                <div className="flex flex-wrap gap-1">
                    <Badge color={g.estado_color}>{g.estado_etiqueta}</Badge>
                    {g.caducada && <Badge color="amber">vencida</Badge>}
                </div>
            </Td>
            <Td>
                <div className="flex justify-end gap-1">
                    {puede('guias.imprimir') && g.ya_fue_aprobada && (
                        <a
                            href={route('guias.imprimir', g.id)}
                            target="_blank"
                            rel="noreferrer"
                            onClick={abrirDirecto(g.pdf_enlace)}
                            title="Imprimir guía"
                            aria-label={`Imprimir la guía ${g.numero_legible}`}
                            className={cn(buttonVariants({ variant: 'outline', size: 'icon' }))}
                        >
                            <Printer className="size-4" />
                        </a>
                    )}
                    {puede('guias.ver') && (
                        <Link
                            href={route('guias.show', g.id)}
                            title="Ver guía"
                            aria-label={`Ver la guía ${g.numero_legible}`}
                            className={cn(buttonVariants({ variant: 'ver', size: 'icon' }))}
                        >
                            <Eye className="size-4" />
                        </Link>
                    )}
                </div>
            </Td>
        </tr>
    );
}

/** La gestión de la cédula: el año en que se emitió, o en que se pidió. */
function gestionDe(c: CarnetResumen): number | null {
    const base = c.fecha_emision ?? c.fecha_solicitud;
    return base ? Number(base.slice(0, 4)) : null;
}

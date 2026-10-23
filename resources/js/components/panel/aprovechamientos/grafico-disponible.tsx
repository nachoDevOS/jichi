import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import type { TooltipContentProps } from 'recharts';
import type { NameType, ValueType } from 'recharts/types/component/DefaultTooltipContent';
import { useReducirMovimiento } from '@/hooks/use-movimiento';
import { ESTILO_TOOLTIP } from '@/lib/graficos';
import { fecha } from '@/lib/utils';
import type { PuntoDisponible } from '@/types/aprovechamientos';

const kg = (n: number) => `${n.toLocaleString('es-BO', { maximumFractionDigits: 2 })} kg`;

// Mismos colores que la barra de Volumen: vendido en petróleo, reservado en gris (slate-400).
const COLOR_LINEA = 'var(--primary)';
const COLOR_RESERVADA = '#94a3b8';

/**
 * Cómo bajó el disponible de la autorización, guía por guía. En escalón: el
 * disponible no se desliza entre dos guías, cae de golpe al registrarse cada una.
 */
export function GraficoDisponible({
    puntos,
    total,
    alto = 'h-56',
}: {
    puntos: PuntoDisponible[];
    total: number;
    /** La altura del lienzo, como clase: la ficha del beneficiario lo usa más bajo. */
    alto?: string;
}) {
    const reducir = useReducirMovimiento();
    // La última guía late: es la que dejó el disponible como está hoy.
    const ultimaGuia = puntos.map((p) => p.kilos !== null).lastIndexOf(true);

    // Lo otorgado y «hoy» siempre vienen: sin una guía en el medio no hay nada que dibujar.
    if (puntos.length <= 2) {
        return (
            <p className="rounded-lg border border-dashed border-border p-4 text-center text-sm text-muted-foreground">
                Todavía ninguna guía le compró pescado: el disponible sigue entero.
            </p>
        );
    }

    return (
        <figure className="space-y-3">
            <figcaption className="flex flex-wrap items-baseline justify-between gap-2">
                <span className="text-sm font-medium">Cómo bajó el disponible</span>
                <span className="flex items-center gap-4 text-xs text-muted-foreground">
                    <span className="flex items-center gap-1.5">
                        <i className="size-2.5 rounded-full bg-primary" aria-hidden />
                        Guía aprobada
                    </span>
                    <span className="flex items-center gap-1.5">
                        <i className="size-2.5 rounded-full bg-slate-400" aria-hidden />
                        Esperando el pago
                    </span>
                </span>
            </figcaption>

            {/* El contenedor necesita altura propia: sin ella recharts dibuja en cero. */}
            <div className={alto}>
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={puntos} margin={{ top: 8, right: 12, bottom: 0, left: -8 }}>
                        <defs>
                            <linearGradient id="degradadoDisponible" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor={COLOR_LINEA} stopOpacity={0.35} />
                                <stop offset="100%" stopColor={COLOR_LINEA} stopOpacity={0.02} />
                            </linearGradient>
                        </defs>

                        <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />

                        <XAxis
                            dataKey="etiqueta"
                            tick={{
                                fontSize: 11,
                                fill: 'var(--muted-foreground)',
                            }}
                            tickLine={false}
                            axisLine={false}
                            interval="preserveStartEnd"
                        />
                        <YAxis
                            domain={[0, total]}
                            tick={{
                                fontSize: 11,
                                fill: 'var(--muted-foreground)',
                            }}
                            tickFormatter={(v: number) => `${v.toLocaleString('es-BO')}`}
                            tickLine={false}
                            axisLine={false}
                            width={56}
                        />

                        <Tooltip content={Globo} cursor={{ stroke: 'var(--border)' }} />

                        <Area
                            type="stepAfter"
                            dataKey="disponible"
                            stroke={COLOR_LINEA}
                            strokeWidth={2}
                            fill="url(#degradadoDisponible)"
                            isAnimationActive={!reducir}
                            animationDuration={1200}
                            animationEasing="ease-out"
                            dot={(props: { cx?: number; cy?: number; index?: number; payload?: PuntoDisponible }) =>
                                props.payload?.kilos === null ? (
                                    <g key={props.index} />
                                ) : (
                                    <g key={props.index}>
                                        {props.index === ultimaGuia && !reducir && (
                                            <circle
                                                cx={props.cx}
                                                cy={props.cy}
                                                r={5}
                                                fill={props.payload?.pendiente ? COLOR_RESERVADA : COLOR_LINEA}
                                                opacity={0.5}
                                            >
                                                <animate
                                                    attributeName="r"
                                                    values="5;14"
                                                    dur="1.8s"
                                                    repeatCount="indefinite"
                                                />
                                                <animate
                                                    attributeName="opacity"
                                                    values="0.5;0"
                                                    dur="1.8s"
                                                    repeatCount="indefinite"
                                                />
                                            </circle>
                                        )}
                                        <circle
                                            cx={props.cx}
                                            cy={props.cy}
                                            r={4.5}
                                            fill={props.payload?.pendiente ? COLOR_RESERVADA : COLOR_LINEA}
                                            stroke="var(--card)"
                                            strokeWidth={2}
                                        />
                                    </g>
                                )
                            }
                            activeDot={{
                                r: 6,
                                stroke: 'var(--card)',
                                strokeWidth: 2,
                            }}
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </div>
        </figure>
    );
}

/** El globo al pasar el mouse: qué guía, quién compró, cuánto bajó y cuánto quedó. */
function Globo({ active, payload }: TooltipContentProps<ValueType, NameType>) {
    const p = payload?.[0]?.payload as PuntoDisponible | undefined;

    if (!active || !p) {
        return null;
    }

    return (
        <div style={ESTILO_TOOLTIP} className="space-y-0.5 px-3 py-2 shadow-md">
            <p className="font-semibold">{p.etiqueta}</p>
            {p.fecha && <p className="text-muted-foreground">{fecha(p.fecha)}</p>}
            {p.comprador && <p>Compró: {p.comprador}</p>}
            {p.kilos !== null && (
                <p>
                    Bajó {kg(p.kilos)}
                    {p.pendiente && <span className="text-muted-foreground"> (esperando el pago)</span>}
                </p>
            )}
            <p className="font-medium">Quedó: {kg(p.disponible)}</p>
        </div>
    );
}

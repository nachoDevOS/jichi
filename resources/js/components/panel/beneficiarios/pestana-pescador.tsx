import { Link } from '@inertiajs/react';
import { ArrowRight, Eye, Fish, Printer, Scale, Ship, ShoppingBasket, Waves } from 'lucide-react';
import { useState } from 'react';
import { GraficoDisponible } from '@/components/panel/aprovechamientos/grafico-disponible';
import { EnlacePermitido } from '@/components/panel/comunes/enlace-permitido';
import {
    abrirDirecto,
    BarraDisponible,
    Conteo,
    kg,
    nivelDisponible,
    porcentaje,
    Tabla,
    Td,
} from '@/components/panel/comunes/ficha';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { usePermisos } from '@/hooks/use-permisos';
import { bs, cn, fecha, fechaInput } from '@/lib/utils';
import type { PuntoDisponible } from '@/types/aprovechamientos';
import type { CarnetResumen, CupoResumen, FaenaDelBeneficiario } from '@/types/beneficiarios';
import {
    AvisoRegistroAnterior,
    CarnetReplica,
    diasEntre,
    HistorialCarnets,
    PieCarnet,
    Seccion,
    TarjetaPeriodo,
    TiraCifras,
    type Titular,
} from './partes-ficha';

/**
 * Un PERÍODO del pescador: una autorización, la cédula que se emitió con ella y
 * las faenas de esa cédula. Cada autorización nueva exige cédula nueva, así que
 * agrupar por autorización es lo que separa lo de este año de lo anterior.
 */
interface Periodo {
    clave: string;
    cupo: CupoResumen | null;
    carnets: CarnetResumen[];
    faenas: FaenaDelBeneficiario[];
}

/**
 * Todo lo del pescador, período por período: autorización → cédula → faenas.
 */
export function PestanaPescador({
    titular,
    carnets,
    cupos,
    faenas,
    moneda,
}: {
    titular: Titular;
    carnets: CarnetResumen[];
    cupos: CupoResumen[];
    faenas: FaenaDelBeneficiario[];
    moneda: string;
}) {
    const periodos = armarPeriodos(carnets, cupos, faenas);
    // El actual: el de la cédula vigente; si no hay, el más nuevo.
    const actual = periodos.find((p) => p.carnets.some((c) => c.vigente)) ?? periodos[0] ?? null;
    const [elegido, setElegido] = useState<string | null>(actual?.clave ?? null);
    const periodo = periodos.find((p) => p.clave === elegido) ?? actual;

    if (periodo === null) {
        return (
            <div className="rounded-lg border border-border bg-card">
                <EstadoVacio
                    icono={Waves}
                    titulo="Sin actividad de pesca"
                    descripcion="No tiene ninguna Autorización de Pesca para Aprovechamiento Pesquero ni cédula de pescador."
                />
            </div>
        );
    }

    const esActual = periodo.clave === actual?.clave;
    // La cédula principal del período: la vigente o la última; el resto son reposiciones.
    const cedula = periodo.carnets.find((c) => c.vigente) ?? periodo.carnets[0] ?? null;
    const otras = periodo.carnets.filter((c) => c.id !== cedula?.id);
    const cupo = periodo.cupo;

    // Solo la guía descuenta: lo vendido es la suma de las guías que le compraron.
    const vendido = cupo ? cupo.ventas.reduce((t, v) => t + v.kilos, 0) : 0;
    const pct = cupo ? porcentaje(cupo.kilos_disponibles, cupo.volumen_total_kg) : 0;
    const nivel = nivelDisponible(pct, cupo?.ya_fue_aprobado ?? false);
    const vigentes = periodo.faenas.filter((f) => f.vigente).length;

    return (
        <div className="space-y-5">
            {/* Solo con historia: con un único período, elegir no tiene sentido. */}
            {periodos.length > 1 && (
                <Seccion titulo={`Períodos (${periodos.length})`}>
                    <div className="flex gap-3 overflow-x-auto pb-1 [scrollbar-width:thin]">
                        {periodos.map((p) => (
                            <BotonPeriodo
                                key={p.clave}
                                periodo={p}
                                activo={p.clave === periodo.clave}
                                esActual={p.clave === actual?.clave}
                                onClick={() => setElegido(p.clave)}
                            />
                        ))}
                    </div>
                </Seccion>
            )}

            {!esActual && (
                <AvisoRegistroAnterior
                    gestion={gestionDe(periodo)}
                    onVolver={() => setElegido(actual?.clave ?? null)}
                />
            )}

            {cupo && (
                <TiraCifras
                    cifras={[
                        {
                            icono: Scale,
                            etiqueta: cupo.ya_fue_aprobado ? 'Autorizado' : 'Solicitado',
                            valor: <Conteo valor={cupo.volumen_total_kg} formato={kg} />,
                            pie: cupo.descripcion ?? 'el volumen de la gestión',
                        },
                        {
                            icono: Fish,
                            etiqueta: 'Disponible',
                            valor: <Conteo valor={cupo.kilos_disponibles} formato={kg} />,
                            pie: nivel.texto ?? `${Math.round(pct)} % del autorizado`,
                            tono: nivel.tono,
                        },
                        {
                            icono: ShoppingBasket,
                            etiqueta: 'Vendido en guías',
                            valor: <Conteo valor={vendido} formato={kg} />,
                            pie: `${cupo.ventas.length} ${cupo.ventas.length === 1 ? 'guía' : 'guías'}`,
                        },
                        {
                            icono: Ship,
                            etiqueta: 'Salidas de pesca',
                            valor: <Conteo valor={periodo.faenas.length} formato={(n) => String(n)} />,
                            pie: vigentes > 0 ? `${vigentes} en el agua hoy` : 'ninguna vigente hoy',
                        },
                    ]}
                />
            )}

            {/* La autorización ocupa lo que sobre; la cédula, el ancho de una tarjeta de verdad. */}
            <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                {cupo ? (
                    <TarjetaCupo cupo={cupo} />
                ) : (
                    <Hueco>Esta cédula no está asociada a ninguna autorización</Hueco>
                )}

                <div className="min-w-0 space-y-3">
                    {cedula ? (
                        <>
                            <CarnetReplica carnet={cedula} titular={titular} />
                            <PieCarnet carnet={cedula} moneda={moneda} />
                        </>
                    ) : (
                        <Hueco>Todavía no se emitió la cédula de esta autorización</Hueco>
                    )}
                </div>
            </div>

            <Seccion titulo="Salidas de pesca">
                {periodo.faenas.length === 0 ? (
                    <div className="rounded-lg border border-border bg-card">
                        <EstadoVacio
                            icono={Ship}
                            titulo="Sin faenas"
                            descripcion={
                                esActual
                                    ? 'Todavía no salió a pescar con esta autorización.'
                                    : 'En este período no salió a pescar.'
                            }
                        />
                    </div>
                ) : (
                    <Card className="min-w-0 overflow-hidden p-0">
                        <Tabla
                            columnas={['N°', 'Embarcación', 'Región', 'Vigencia', '>Kilos', '>Arancel', 'Estado', '']}
                        >
                            {periodo.faenas.map((f) => (
                                <FilaFaena key={f.id} faena={f} moneda={moneda} />
                            ))}
                        </Tabla>
                    </Card>
                )}
            </Seccion>

            {otras.length > 0 && (
                <Seccion titulo="Otras cédulas de esta autorización">
                    <HistorialCarnets carnets={otras} moneda={moneda} />
                </Seccion>
            )}
        </div>
    );
}

function Hueco({ children }: { children: string }) {
    return (
        <div className="flex h-40 items-center justify-center rounded-xl border-2 border-dashed border-border px-4 text-center text-sm text-muted-foreground">
            {children}
        </div>
    );
}

/** Un período en la fila de arriba: gestión, capacidad, estado y qué tuvo. */
function BotonPeriodo({
    periodo: p,
    activo,
    esActual,
    onClick,
}: {
    periodo: Periodo;
    activo: boolean;
    esActual: boolean;
    onClick: () => void;
}) {
    const cedula = p.carnets.find((c) => c.vigente) ?? p.carnets[0] ?? null;
    const vendido = p.cupo ? p.cupo.ventas.reduce((t, v) => t + v.kilos, 0) : 0;

    return (
        <TarjetaPeriodo
            gestion={gestionDe(p)}
            esActual={esActual}
            activo={activo}
            insignia={p.cupo ? <Badge color={p.cupo.estado_color}>{p.cupo.estado_etiqueta}</Badge> : undefined}
            titulo={p.cupo ? (p.cupo.descripcion ?? kg(p.cupo.volumen_total_kg)) : 'Sin autorización'}
            detalle={`${cedula ? `Cédula N° ${cedula.registro ?? '—'}` : 'Sin cédula'} · ${p.faenas.length} ${p.faenas.length === 1 ? 'faena' : 'faenas'} · ${kg(vendido)} vendidos`}
            onClick={onClick}
        />
    );
}

/**
 * Agrupa por autorización. Las cédulas cuelgan por `aprovechamiento_id` y las
 * faenas por `carnet_id`; lo que no tenga autorización queda en un período aparte.
 */
function armarPeriodos(carnets: CarnetResumen[], cupos: CupoResumen[], faenas: FaenaDelBeneficiario[]): Periodo[] {
    const pescador = carnets.filter((c) => c.tipo_actor === 'pescador');
    const faenasDe = (lista: CarnetResumen[]) => faenas.filter((f) => lista.some((c) => c.id === f.carnet_id));

    const periodos: Periodo[] = cupos.map((cupo) => {
        const propios = pescador.filter((c) => c.aprovechamiento_id === cupo.id);
        return { clave: `cupo-${cupo.id}`, cupo, carnets: propios, faenas: faenasDe(propios) };
    });

    const sueltos = pescador.filter((c) => !cupos.some((cupo) => cupo.id === c.aprovechamiento_id));
    if (sueltos.length > 0) {
        periodos.push({ clave: 'sin-cupo', cupo: null, carnets: sueltos, faenas: faenasDe(sueltos) });
    }

    return periodos;
}

/** La gestión del período: el año en que se otorgó, o en que se pidió. */
function gestionDe(p: Periodo): number | null {
    const fechaBase =
        p.cupo?.fecha_emision ??
        p.cupo?.fecha_solicitud ??
        p.carnets[0]?.fecha_emision ??
        p.carnets[0]?.fecha_solicitud;
    return fechaBase ? Number(fechaBase.slice(0, 4)) : null;
}

/**
 * Los escalones del gráfico, armados con las guías que le compraron: lo
 * otorgado, una caída por guía y «hoy». Es la misma cuenta que la ficha de la autorización.
 */
function evolucionDe(cupo: CupoResumen): PuntoDisponible[] {
    let disponible = cupo.volumen_total_kg;
    const base = { kilos: null, comprador: null, pendiente: false };

    return [
        { ...base, etiqueta: 'Otorgada', fecha: cupo.fecha_emision ?? cupo.fecha_solicitud, disponible },
        ...cupo.ventas.map((v) => {
            disponible -= v.kilos;
            return {
                etiqueta: `Guía ${v.numero_legible}`,
                fecha: v.fecha,
                disponible: Math.round(disponible * 100) / 100,
                kilos: v.kilos,
                comprador: v.comprador,
                pendiente: v.pendiente,
            };
        }),
        { ...base, etiqueta: 'Hoy', fecha: fechaInput(new Date()), disponible: Math.round(disponible * 100) / 100 },
    ];
}

/** La autorización: cuánto queda, cómo bajó y sus fechas. */
function TarjetaCupo({ cupo }: { cupo: CupoResumen }) {
    const hoy = fechaInput(new Date());
    // La vigencia la dice la fecha, no el estado: aprobada sigue aprobada aunque pase su fecha.
    const vencido =
        cupo.ya_fue_aprobado && cupo.fecha_vencimiento !== null && cupo.fecha_vencimiento.slice(0, 10) < hoy;
    const revocado = cupo.estado === 'revocado';

    return (
        <Card className="min-w-0 overflow-hidden p-0">
            <div className="flex flex-wrap items-center gap-2 border-b border-border px-5 py-3">
                <Waves className="size-4 text-primary" />
                <span className="text-sm font-semibold">Autorización de Pesca</span>
                {/* La capacidad y no el N° de escala: ese número es del catálogo interno. */}
                <span className="text-sm text-muted-foreground">· {cupo.descripcion ?? kg(cupo.volumen_total_kg)}</span>
                <Badge color={cupo.estado_color}>{cupo.estado_etiqueta}</Badge>
                <EnlacePermitido
                    permiso="aprovechamientos.ver"
                    href={route('aprovechamientos.show', cupo.id)}
                    className="ml-auto inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                >
                    Ver autorización <ArrowRight className="size-3.5" />
                </EnlacePermitido>
            </div>

            <div className="space-y-4 px-5 py-4">
                <BarraDisponible
                    disponible={cupo.kilos_disponibles}
                    total={cupo.volumen_total_kg}
                    aprobada={cupo.ya_fue_aprobado}
                />

                {revocado ? (
                    <p className="text-sm text-rose-700 dark:text-rose-400">
                        Revocada: no autoriza faenas ni carnets nuevos.
                    </p>
                ) : vencido ? (
                    <p className="text-sm text-muted-foreground">Venció el {fecha(cupo.fecha_vencimiento)}.</p>
                ) : !cupo.ya_fue_aprobado ? (
                    <p className="text-sm text-muted-foreground">Todavía sin aprobar: falta el pago en SIREB.</p>
                ) : (
                    <GraficoDisponible puntos={evolucionDe(cupo)} total={cupo.volumen_total_kg} alto="h-44" />
                )}
            </div>

            <dl className="grid grid-cols-3 border-t border-border bg-secondary/30 text-xs">
                {[
                    ['Solicitada', cupo.fecha_solicitud],
                    ['Otorgada', cupo.fecha_emision],
                    ['Vence', cupo.fecha_vencimiento],
                ].map(([rotulo, valor], i) => (
                    <div key={rotulo} className={cn('px-5 py-2.5', i > 0 && 'border-l border-border')}>
                        <dt className="text-muted-foreground">{rotulo}</dt>
                        <dd className="font-medium tabular-nums">{fecha(valor) || '—'}</dd>
                    </div>
                ))}
            </dl>
        </Card>
    );
}

/** Una salida con lo que dice su talonario, sin tener que abrir la ficha. */
function FilaFaena({ faena: f, moneda }: { faena: FaenaDelBeneficiario; moneda: string }) {
    const { puede } = usePermisos();
    const vigencia = vigenciaDe(f);

    return (
        <tr className="transition-colors hover:bg-secondary/50">
            <Td>
                <EnlacePermitido
                    permiso="faenas.ver"
                    href={route('faenas.show', f.id)}
                    className="font-mono font-medium text-primary tabular-nums hover:underline"
                >
                    {f.numero_legible}
                </EnlacePermitido>
            </Td>
            <Td>
                <p className="font-medium">{f.embarcacion ?? '—'}</p>
                <p className="text-xs text-muted-foreground">
                    {[
                        f.comandante_barco && `cmte. ${f.comandante_barco}`,
                        f.matricula_naval && `mat. ${f.matricula_naval}`,
                    ]
                        .filter(Boolean)
                        .join(' · ') || '—'}
                </p>
            </Td>
            <Td>
                {f.region_desde || f.region_hasta ? (
                    <span className="inline-flex items-center gap-1.5">
                        {f.region_desde ?? '—'}
                        <ArrowRight className="size-3.5 text-muted-foreground" />
                        {f.region_hasta ?? '—'}
                    </span>
                ) : (
                    '—'
                )}
            </Td>
            {/* La vigencia junto a sus fechas, que son las que la deciden. Las fija la aprobación. */}
            <Td>
                <div className="flex items-center gap-2 whitespace-nowrap">
                    <Badge color={vigencia.color}>{vigencia.etiqueta}</Badge>
                    {vigencia.detalle && <span className="text-xs text-muted-foreground">{vigencia.detalle}</span>}
                </div>
                {vigencia.pct !== null && (
                    <div className="mt-1.5 h-1 w-32 overflow-hidden rounded-full bg-secondary">
                        <div className="h-full rounded-full bg-emerald-500" style={{ width: `${vigencia.pct}%` }} />
                    </div>
                )}
                {f.fecha_salida && (
                    <p className="mt-1 text-xs whitespace-nowrap text-muted-foreground tabular-nums">
                        {fecha(f.fecha_salida)} → {fecha(f.fecha_desembarque)}
                    </p>
                )}
            </Td>
            <Td derecha>
                <span className="font-medium whitespace-nowrap">{kg(f.kilos_extraidos)}</span>
            </Td>
            <Td derecha>
                <span className="whitespace-nowrap">{bs(f.monto, moneda)}</span>
                {f.debe > 0 ? (
                    <p className="text-xs text-amber-700 dark:text-amber-300">faltan {bs(f.debe, moneda)}</p>
                ) : (
                    <p className="text-xs text-emerald-700 dark:text-emerald-400">Pagado</p>
                )}
            </Td>
            <Td>
                <Badge color={f.estado_color}>{f.estado_etiqueta}</Badge>
            </Td>
            <Td>
                <div className="flex justify-end gap-1">
                    {puede('faenas.imprimir') && f.puede_imprimirse && (
                        <a
                            href={route('faenas.imprimir', f.id)}
                            target="_blank"
                            rel="noreferrer"
                            onClick={abrirDirecto(f.pdf_enlace)}
                            title="Imprimir faena"
                            aria-label={`Imprimir el permiso de la faena ${f.numero_legible}`}
                            className={cn(buttonVariants({ variant: 'outline', size: 'icon' }))}
                        >
                            <Printer className="size-4" />
                        </a>
                    )}
                    {puede('faenas.ver') && (
                        <Link
                            href={route('faenas.show', f.id)}
                            title="Ver faena"
                            aria-label={`Ver la faena ${f.numero_legible}`}
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

/**
 * Si la salida vale HOY, aparte del estado: «aprobado» dice que se firmó,
 * no que siga dentro de sus fechas. `vigente` y `caducada` llegan del servidor.
 */
function vigenciaDe(f: FaenaDelBeneficiario): {
    etiqueta: string;
    color: string;
    detalle?: string;
    pct: number | null;
} {
    if (!f.fecha_salida || !f.fecha_desembarque) {
        return { etiqueta: 'Sin fechas', color: 'slate', detalle: 'se fijan al aprobar', pct: null };
    }

    if (f.vigente) {
        const quedan = diasEntre(fechaInput(new Date()), f.fecha_desembarque) ?? 0;
        const total = diasEntre(f.fecha_salida, f.fecha_desembarque) ?? 0;
        return {
            etiqueta: 'Vigente',
            color: 'emerald',
            detalle: quedan === 0 ? 'desembarca hoy' : `${quedan} ${quedan === 1 ? 'día' : 'días'} restantes`,
            // Lo que le queda de la salida: la barra se vacía a medida que pasan los días.
            pct: total > 0 ? Math.max(4, Math.min(100, (quedan / total) * 100)) : null,
        };
    }

    if (f.estado === 'revocado') {
        return { etiqueta: 'Revocada', color: 'rose', detalle: 'por la Unidad', pct: null };
    }

    return { etiqueta: 'Vencida', color: 'rose', detalle: f.caducada ? 'sin cerrar' : undefined, pct: null };
}

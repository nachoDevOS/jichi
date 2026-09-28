import { Link } from '@inertiajs/react';
import { Eye, Printer, Ship, Waves } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { usePermisos } from '@/hooks/use-permisos';
import { bs, cn, fecha } from '@/lib/utils';
import type { CarnetResumen, CupoResumen, FaenaDelBeneficiario } from '@/types/beneficiarios';
import { AvisoRegistroAnterior, CredencialMini, HistorialCarnets, Seccion, TarjetaPeriodo } from './partes-ficha';

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
    carnets,
    cupos,
    faenas,
    moneda,
}: {
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

    return (
        <div className="space-y-6">
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

            {!esActual && <AvisoRegistroAnterior gestion={gestionDe(periodo)} onVolver={() => setElegido(actual?.clave ?? null)} />}

            {/* En el orden del trámite: primero la autorización, después la cédula. */}
            <div className="grid gap-6 lg:grid-cols-5">
                <div className="min-w-0 space-y-3 lg:col-span-3">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        Autorización de Pesca para Aprovechamiento Pesquero
                    </h3>
                    {periodo.cupo ? (
                        <TarjetaCupo cupo={periodo.cupo} />
                    ) : (
                        <div className="flex h-40 items-center justify-center rounded-xl border-2 border-dashed border-border px-4 text-center text-sm text-muted-foreground">
                            Esta cédula no está asociada a ninguna autorización
                        </div>
                    )}
                </div>

                <div className="space-y-3 lg:col-span-2">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Cédula</h3>
                    {cedula ? (
                        <CredencialMini carnet={cedula} moneda={moneda} />
                    ) : (
                        <div className="flex h-40 items-center justify-center rounded-xl border-2 border-dashed border-border px-4 text-center text-sm text-muted-foreground">
                            Todavía no se emitió la cédula de esta autorización
                        </div>
                    )}
                </div>
            </div>

            <Seccion titulo="Salidas de pesca">
                {periodo.faenas.length === 0 ? (
                    <div className="rounded-lg border border-border">
                        <EstadoVacio
                            icono={Ship}
                            titulo="Sin faenas"
                            descripcion={esActual ? 'Todavía no salió a pescar con esta autorización.' : 'En este período no salió a pescar.'}
                        />
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border bg-card">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border bg-secondary/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2.5 font-medium">N°</th>
                                    <th className="px-4 py-2.5 text-right font-medium">Kilos</th>
                                    <th className="px-4 py-2.5 font-medium">Embarcación</th>
                                    <th className="px-4 py-2.5 font-medium">Comandante</th>
                                    <th className="px-4 py-2.5 font-medium">Región</th>
                                    <th className="px-4 py-2.5 font-medium">Vigencia</th>
                                    <th className="px-4 py-2.5 text-right font-medium">Arancel</th>
                                    <th className="px-4 py-2.5 font-medium">Estado</th>
                                    <th className="px-4 py-2.5" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {periodo.faenas.map((f) => (
                                    <FilaFaena key={f.id} faena={f} moneda={moneda} />
                                ))}
                            </tbody>
                        </table>
                    </div>
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
    const kilos = p.faenas
        .filter((f) => f.estado === 'aprobado' || f.estado === 'completado')
        .reduce((total, f) => total + f.kilos_extraidos, 0);

    return (
        <TarjetaPeriodo
            gestion={gestionDe(p)}
            esActual={esActual}
            activo={activo}
            insignia={p.cupo ? <Badge color={p.cupo.estado_color}>{p.cupo.estado_etiqueta}</Badge> : undefined}
            titulo={p.cupo ? (p.cupo.descripcion ?? `${p.cupo.volumen_total_kg} kg`) : 'Sin autorización'}
            detalle={`${cedula ? `Cédula N° ${cedula.registro ?? '—'}` : 'Sin cédula'} · ${p.faenas.length} ${p.faenas.length === 1 ? 'faena' : 'faenas'} · ${kilos} kg pescados`}
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
    const fechaBase = p.cupo?.fecha_emision ?? p.cupo?.fecha_solicitud ?? p.carnets[0]?.fecha_emision ?? p.carnets[0]?.fecha_solicitud;
    return fechaBase ? Number(fechaBase.slice(0, 4)) : null;
}

/** Una salida con lo que dice su talonario, sin tener que abrir la ficha. */
function FilaFaena({ faena: f, moneda }: { faena: FaenaDelBeneficiario; moneda: string }) {
    const { puede } = usePermisos();
    const vigencia = vigenciaDe(f);

    return (
        <tr className="hover:bg-secondary/50">
            <td className="px-4 py-2.5">
                <Link
                    href={route('faenas.show', f.id)}
                    className="font-mono font-medium tabular-nums text-primary hover:underline"
                >
                    {f.numero_legible}
                </Link>
            </td>
            <td className="whitespace-nowrap px-4 py-2.5 text-right font-medium tabular-nums">{f.kilos_extraidos} kg</td>
            <td className="px-4 py-2.5">
                {f.embarcacion ?? '—'}
                {(f.propietario || f.matricula_naval) && (
                    <p className="text-xs text-muted-foreground">
                        {[f.propietario && `de ${f.propietario}`, f.matricula_naval && `mat. ${f.matricula_naval}`]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                )}
            </td>
            <td className="px-4 py-2.5">{f.comandante_barco ?? '—'}</td>
            <td className="px-4 py-2.5">
                {f.region_desde || f.region_hasta ? `${f.region_desde ?? '—'} → ${f.region_hasta ?? '—'}` : '—'}
            </td>
            {/* La vigencia junto a sus fechas, que son las que la deciden. Las fija la aprobación. */}
            <td className="whitespace-nowrap px-4 py-2.5">
                <div className="flex items-center gap-2">
                    <Badge color={vigencia.color}>{vigencia.etiqueta}</Badge>
                    {vigencia.detalle && <span className="text-xs text-muted-foreground">{vigencia.detalle}</span>}
                </div>
                {f.fecha_salida && (
                    <p className="mt-1 tabular-nums">
                        {fecha(f.fecha_salida)} → {fecha(f.fecha_desembarque)}
                    </p>
                )}
            </td>
            <td className="whitespace-nowrap px-4 py-2.5 text-right tabular-nums">
                {bs(f.monto, moneda)}
                {f.debe > 0 ? (
                    <p className="text-xs text-amber-700 dark:text-amber-300">faltan {bs(f.debe, moneda)}</p>
                ) : (
                    <p className="text-xs text-emerald-700 dark:text-emerald-400">Pagado</p>
                )}
            </td>
            <td className="px-4 py-2.5">
                <Badge color={f.estado_color}>{f.estado_etiqueta}</Badge>
            </td>
            <td className="px-4 py-2.5">
                <div className="flex justify-end gap-1">
                    {puede('faenas.imprimir') && f.puede_imprimirse && (
                        <a
                            href={route('faenas.imprimir', f.id)}
                            target="_blank"
                            rel="noreferrer"
                            title="Imprimir faena"
                            aria-label={`Imprimir el permiso de la faena ${f.numero_legible}`}
                            className={cn(buttonVariants({ variant: 'outline', size: 'icon' }))}
                        >
                            <Printer className="size-4" />
                        </a>
                    )}
                    <Link
                        href={route('faenas.show', f.id)}
                        title="Ver faena"
                        aria-label={`Ver la faena ${f.numero_legible}`}
                        className={cn(buttonVariants({ variant: 'ver', size: 'icon' }))}
                    >
                        <Eye className="size-4" />
                    </Link>
                </div>
            </td>
        </tr>
    );
}

/**
 * Si la salida vale HOY, aparte del estado: «aprobado» dice que se firmó,
 * no que siga dentro de sus fechas. `vigente` y `caducada` llegan del servidor.
 */
function vigenciaDe(f: FaenaDelBeneficiario): { etiqueta: string; color: string; detalle?: string } {
    if (!f.fecha_salida || !f.fecha_desembarque) {
        return { etiqueta: 'Sin fechas', color: 'slate', detalle: 'se fijan al aprobar' };
    }

    if (f.vigente) {
        const quedan = diasEntre(hoy(), f.fecha_desembarque) ?? 0;
        return {
            etiqueta: 'Vigente',
            color: 'emerald',
            detalle: quedan === 0 ? 'desembarca hoy' : `${quedan} ${quedan === 1 ? 'día' : 'días'} restantes`,
        };
    }

    if (f.estado === 'revocado') {
        return { etiqueta: 'Revocada', color: 'rose', detalle: 'con su autorización' };
    }

    if (f.caducada) {
        return { etiqueta: 'Vencida', color: 'rose', detalle: 'sin cerrar' };
    }

    return f.estado === 'completado'
        ? { etiqueta: 'Cerrada', color: 'slate', detalle: 'volvió y descargó' }
        : { etiqueta: 'Vencida', color: 'rose' };
}

/** Hoy como 'AAAA-MM-DD' local: para comparar días sin pasar por UTC. */
function hoy(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** Días entre dos fechas 'AAAA-MM-DD'. Se cuentan en UTC las dos, así el huso no mueve el resultado. */
function diasEntre(desde: string | null, hasta: string | null): number | null {
    if (!desde || !hasta) return null;
    const dias = Math.round((Date.parse(hasta.slice(0, 10)) - Date.parse(desde.slice(0, 10))) / 86400000);
    return Number.isNaN(dias) ? null : dias;
}

/** La bolsa madre con su saldo: lo que dice si puede salir mañana. */
function TarjetaCupo({ cupo }: { cupo: CupoResumen }) {
    const vencido = cupo.estado === 'vencido';
    const revocado = cupo.estado === 'revocado';
    // Ninguna de las dos autoriza: lo que sobró no se puede pescar.
    const cerrado = vencido || revocado;
    const escaso = !cerrado && cupo.volumen_total_kg > 0 && cupo.saldo_kg / cupo.volumen_total_kg < 0.2;
    const colorLibre = cerrado ? 'bg-slate-300 dark:bg-slate-600' : escaso ? 'bg-amber-500' : 'bg-sky-600';

    return (
        <Link
            href={route('aprovechamientos.show', cupo.id)}
            className="block rounded-xl border border-border bg-card p-5 transition-colors hover:bg-secondary/40"
        >
            <div className="flex flex-wrap items-center gap-2">
                <Waves className="size-4 text-sky-600 dark:text-sky-400" />
                {/* La capacidad y no el N° de escala: ese número es del catálogo interno. */}
                <span className="font-medium">{cupo.descripcion ?? `${cupo.volumen_total_kg} kg`}</span>
                <Badge color={cupo.estado_color}>{cupo.estado_etiqueta}</Badge>
            </div>

            {/* Antes de la firma no se consumió nada: solo hay un volumen pedido. */}
            {cupo.ya_fue_aprobado ? (
                <div className="mt-4 space-y-3">
                    {/* En palabras, para que se entienda sin hacer cuentas. */}
                    {revocado ? (
                        <p className="text-sm text-muted-foreground">
                            <span className="font-semibold text-rose-700 dark:text-rose-400">Revocada:</span> no autoriza
                            faenas ni carnets nuevos. Quedaron{' '}
                            <span className="text-3xl font-semibold tabular-nums text-foreground">{cupo.saldo_kg} kg</span> sin
                            pescar
                        </p>
                    ) : vencido ? (
                        // Vencida no autoriza nada: lo que sobró no se puede pescar.
                        <p className="text-sm text-muted-foreground">
                            Venció el {fecha(cupo.fecha_vencimiento)} con{' '}
                            <span className="text-3xl font-semibold tabular-nums text-foreground">{cupo.saldo_kg} kg</span> sin
                            pescar
                        </p>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {cupo.saldo_kg > 0 ? 'Le quedan' : 'No le quedan kilos:'}{' '}
                            <span className={cn('text-3xl font-semibold tabular-nums text-foreground', escaso && 'text-amber-700 dark:text-amber-400')}>
                                {cupo.saldo_kg} kg
                            </span>{' '}
                            {cupo.saldo_kg > 0 ? 'para pescar' : 'hay que tramitar otra autorización'}
                        </p>
                    )}

                    {/* Dos tramos: lo usado en faenas y lo que todavía puede pescar. */}
                    <div className="flex h-3 overflow-hidden rounded-full bg-secondary">
                        <div className="h-full bg-slate-400 dark:bg-slate-500" style={{ width: `${cupo.porcentaje_usado}%` }} />
                        <div
                            className={cn('h-full', colorLibre)}
                            style={{ width: `${Math.max(0, 100 - cupo.porcentaje_usado)}%` }}
                        />
                    </div>

                    <dl className="grid grid-cols-3 gap-3 text-xs">
                        <div>
                            <dt className="text-muted-foreground">Autorizado</dt>
                            <dd className="text-sm font-semibold tabular-nums">{cupo.volumen_total_kg} kg</dd>
                        </div>
                        <div>
                            <dt className="flex items-center gap-1.5 text-muted-foreground">
                                <span className="size-2 rounded-full bg-slate-400 dark:bg-slate-500" />
                                Usado en faenas
                            </dt>
                            <dd className="text-sm font-semibold tabular-nums">{cupo.kilos_consumidos} kg</dd>
                        </div>
                        <div>
                            <dt className="flex items-center gap-1.5 text-muted-foreground">
                                <span className={cn('size-2 rounded-full', colorLibre)} />
                                {cerrado ? 'Sin pescar' : 'Disponible'}
                            </dt>
                            <dd className="text-sm font-semibold tabular-nums">{cupo.saldo_kg} kg</dd>
                        </div>
                    </dl>
                </div>
            ) : (
                <p className="mt-4 tabular-nums">
                    <span className="text-3xl font-semibold">{cupo.volumen_total_kg}</span>
                    <span className="text-muted-foreground"> kg solicitados · todavía sin firmar</span>
                </p>
            )}

            <dl className="mt-4 grid grid-cols-3 gap-3 border-t border-border pt-3 text-xs">
                <div>
                    <dt className="text-muted-foreground">Solicitado</dt>
                    <dd className="font-medium tabular-nums">{fecha(cupo.fecha_solicitud)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Otorgado</dt>
                    <dd className="font-medium tabular-nums">{fecha(cupo.fecha_emision)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Vence</dt>
                    <dd className="font-medium tabular-nums">{fecha(cupo.fecha_vencimiento)}</dd>
                </div>
            </dl>
        </Link>
    );
}

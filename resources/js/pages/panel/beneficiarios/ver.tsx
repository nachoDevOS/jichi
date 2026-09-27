import { Head, router, usePage } from '@inertiajs/react';
import {
    Cake,
    CalendarDays,
    Fish,
    MapPin,
    Pencil,
    Phone,
    Receipt,
    Trash2,
    Truck,
    User,
    type LucideIcon,
} from 'lucide-react';
import { useState } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { PestanaComercializador } from '@/components/panel/beneficiarios/pestana-comercializador';
import { PestanaPagos } from '@/components/panel/beneficiarios/pestana-pagos';
import { PestanaPescador } from '@/components/panel/beneficiarios/pestana-pescador';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ConfirmarAccion } from '@/components/ui/confirmar-accion';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { cn, edadEnAnios, fecha, fechaHora, hace } from '@/lib/utils';
import type { PageProps, TipoActor } from '@/types';
import type {
    BeneficiarioFicha,
    CarnetResumen,
    CupoResumen,
    FaenaDelBeneficiario,
    GuiaDelBeneficiario,
    ReciboDelBeneficiario,
} from '@/types/beneficiarios';

/**
 * La ficha del beneficiario.
 */
export default function VerBeneficiario({
    beneficiario,
    gestion,
    deuda,
    carnets,
    cupos,
    faenas,
    guias,
    recibos,
}: {
    beneficiario: BeneficiarioFicha;
    gestion: number;
    deuda: number;
    /** Sus credenciales. Pueden ser DOS vigentes: pescador y comercializador. */
    carnets: CarnetResumen[];
    /** Sus bolsas madre, de todas las gestiones. */
    cupos: CupoResumen[];
    /** Sus salidas como pescador, de la más nueva a la más vieja. */
    faenas: FaenaDelBeneficiario[];
    /** Sus traslados como comercializador. */
    guias: GuiaDelBeneficiario[];
    recibos: ReciboDelBeneficiario[];
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [confirmarBaja, setConfirmarBaja] = useState(false);
    const [pestana, setPestana] = useState<ClavePestana>(() => pestanaInicial(carnets, cupos));

    // La pestaña va a la URL: al volver de una faena o recargar, se abre la misma.
    const elegir = (clave: ClavePestana) => {
        setPestana(clave);
        const url = new URL(window.location.href);
        url.searchParams.set('pestana', clave);
        window.history.replaceState(window.history.state, '', url);
    };

    return (
        <LayoutPanel
            titulo={beneficiario.nombreCompleto}
            descripcion={beneficiario.documento_identidad}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {puede('beneficiarios.editar') && (
                        <Button
                            variant="editar"
                            onClick={() => router.visit(route('beneficiarios.edit', beneficiario.id))}
                        >
                            <Pencil className="size-4" />
                            Editar
                        </Button>
                    )}

                    {puede('beneficiarios.eliminar') && (
                        <Button variant="eliminar" onClick={() => setConfirmarBaja(true)}>
                            <Trash2 className="size-4" />
                            Dar de baja
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={beneficiario.nombreCompleto} />

            <Cabecera beneficiario={beneficiario} carnets={carnets} gestion={gestion} />

            {/* LAS PESTAÑAS: una por actividad, porque cada una es un carnet propio. */}
            <div
                role="tablist"
                aria-label="Secciones de la ficha"
                className="mt-6 flex gap-1 overflow-x-auto border-b border-border [scrollbar-width:none]"
            >
                {PESTANAS.map((p) => {
                    const activa = pestana === p.clave;
                    const vigente = p.actor ? carnets.some((c) => c.tipo_actor === p.actor && c.vigente) : null;

                    return (
                        <button
                            key={p.clave}
                            type="button"
                            role="tab"
                            aria-selected={activa}
                            onClick={() => elegir(p.clave)}
                            className={cn(
                                'flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                                activa ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <p.icono className="size-4" />
                            {p.titulo}
                            {/* El punto dice de un vistazo si esa actividad está habilitada hoy. */}
                            {vigente !== null && (
                                <span
                                    className={cn('size-2 rounded-full', vigente ? 'bg-emerald-500' : 'bg-muted-foreground/30')}
                                    title={vigente ? 'Con carnet vigente' : 'Sin carnet vigente'}
                                />
                            )}
                        </button>
                    );
                })}
            </div>

            <div role="tabpanel" className="pt-6">
                {pestana === 'pescador' && (
                    <PestanaPescador carnets={carnets} cupos={cupos} faenas={faenas} moneda={institucion.moneda} />
                )}
                {pestana === 'comercializador' && (
                    <PestanaComercializador carnets={carnets} guias={guias} moneda={institucion.moneda} />
                )}
                {pestana === 'pagos' && (
                    <PestanaPagos beneficiarioId={beneficiario.id} recibos={recibos} deuda={deuda} moneda={institucion.moneda} />
                )}
                {pestana === 'datos' && <DatosPersonales beneficiario={beneficiario} />}
            </div>

            <ConfirmarAccion
                abierto={confirmarBaja}
                titulo="¿Dar de baja al beneficiario?"
                descripcion="Dejará de aparecer en el padrón y no se le podrá emitir nada nuevo. Sus carnets, cupos y pagos históricos se conservan."
                textoConfirmar="Dar de baja"
                confirmacion="Entiendo que la persona deja el padrón y no se le va a poder emitir nada nuevo."
                onCancelar={() => setConfirmarBaja(false)}
                onConfirmar={() => router.delete(route('beneficiarios.destroy', beneficiario.id))}
            />
        </LayoutPanel>
    );
}

type ClavePestana = 'pescador' | 'comercializador' | 'pagos' | 'datos';

const PESTANAS: { clave: ClavePestana; titulo: string; icono: LucideIcon; actor?: TipoActor }[] = [
    { clave: 'pescador', titulo: 'Pescador', icono: Fish, actor: 'pescador' },
    { clave: 'comercializador', titulo: 'Comercializador', icono: Truck, actor: 'comercializador' },
    { clave: 'pagos', titulo: 'Pagos', icono: Receipt },
    { clave: 'datos', titulo: 'Datos personales', icono: User },
];

/** La pestaña inicial: la que se pidió en la URL, o la de la actividad que ejerce. */
function pestanaInicial(carnets: CarnetResumen[], cupos: CupoResumen[]): ClavePestana {
    const pedida = new URLSearchParams(window.location.search).get('pestana');

    if (PESTANAS.some((p) => p.clave === pedida)) return pedida as ClavePestana;

    const esPescador = cupos.length > 0 || carnets.some((c) => c.tipo_actor === 'pescador');
    const esComercializador = carnets.some((c) => c.tipo_actor === 'comercializador');

    return !esPescador && esComercializador ? 'comercializador' : 'pescador';
}

/**
 * La portada: quién es y qué actividades tiene habilitadas hoy.
 */
function Cabecera({
    beneficiario,
    carnets,
    gestion,
}: {
    beneficiario: BeneficiarioFicha;
    carnets: CarnetResumen[];
    gestion: number;
}) {
    const vigentes = carnets.filter((c) => c.vigente);
    const edad = edadEnAnios(beneficiario.fechaNacimiento);
    const lugar = [beneficiario.ciudad, beneficiario.provincia].filter(Boolean).join(', ');

    return (
        <Card className="min-w-0 overflow-hidden">
            {/* La franja institucional: azul del GAD con el filete dorado. */}
            <div className="relative h-24 bg-gradient-to-r from-primary to-[var(--institucional-azul-claro)]">
                <span className="absolute inset-x-0 bottom-0 h-1 bg-accent" />
                <span className="pointer-events-none absolute -right-8 -top-12 size-44 rounded-full bg-white/10" />
                <span className="pointer-events-none absolute right-32 -bottom-16 size-32 rounded-full bg-white/5" />
            </div>

            {/* Solo la foto se monta sobre la franja: el texto queda siempre sobre el fondo de la tarjeta. */}
            <div className="relative px-5 pb-5">
                <div className="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start">
                    <Retrato
                        url={beneficiario.foto_url}
                        nombre={beneficiario.nombreCompleto}
                        className="-mt-14 size-28 shrink-0 border-4 border-card bg-muted shadow-md [&_svg]:size-10"
                    />

                    <div className="min-w-0 flex-1 sm:pt-3">
                        <h2 className="truncate text-xl font-semibold">{beneficiario.nombreCompleto}</h2>
                        <p className="font-mono text-sm tabular-nums text-muted-foreground">C.I. {beneficiario.documento_identidad}</p>

                        <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                            {edad !== null && (
                                <span className="flex items-center gap-1">
                                    <Cake className="size-3.5" /> {edad} años
                                </span>
                            )}
                            {beneficiario.telefono && (
                                <span className="flex items-center gap-1">
                                    <Phone className="size-3.5" /> {beneficiario.telefono}
                                </span>
                            )}
                            {lugar && (
                                <span className="flex items-center gap-1">
                                    <MapPin className="size-3.5" /> {lugar}
                                </span>
                            )}
                            {beneficiario.registrado && (
                                <span className="flex items-center gap-1" title={fechaHora(beneficiario.registrado)}>
                                    <CalendarDays className="size-3.5" /> En el padrón desde {fecha(beneficiario.registrado)}
                                </span>
                            )}
                        </div>

                        {/* Una insignia por actividad habilitada hoy. */}
                        <div className="mt-3 flex flex-wrap gap-1.5">
                            {vigentes.length === 0 ? (
                                <Badge color="slate">Sin carnet vigente en {gestion}</Badge>
                            ) : (
                                vigentes.map((c) => (
                                    <Badge key={c.id} color={c.tipo_actor_color}>
                                        {c.tipo_actor_etiqueta} {gestion} · {c.tipo_actor === 'pescador' ? 'emite faenas' : 'emite guías'}
                                    </Badge>
                                ))
                            )}
                        </div>
                    </div>
                </div>

            </div>
        </Card>
    );
}

function DatosPersonales({ beneficiario }: { beneficiario: BeneficiarioFicha }) {
    const edad = edadEnAnios(beneficiario.fechaNacimiento);

    return (
        <Card className="min-w-0">
            <CardContent className="grid gap-x-10 gap-y-3 pt-5 text-sm md:grid-cols-2">
                <Dato etiqueta="Nombre completo" valor={beneficiario.nombreCompleto} />
                <Dato etiqueta="Cédula de identidad" valor={beneficiario.documento_identidad} />
                <Dato
                    etiqueta="Nacimiento"
                    valor={beneficiario.fechaNacimiento ? `${fecha(beneficiario.fechaNacimiento)}${edad !== null ? ` · ${edad} años` : ''}` : null}
                />
                <Dato etiqueta="Género" valor={beneficiario.genero} capitalizar />
                <Dato etiqueta="Nacionalidad" valor={beneficiario.nacionalidad} />
                <Dato etiqueta="Teléfono" valor={beneficiario.telefono} />
                <Dato etiqueta="Correo" valor={beneficiario.email} />
                <Dato etiqueta="Ciudad" valor={beneficiario.ciudad} />
                <Dato etiqueta="Provincia" valor={beneficiario.provincia} />
                <Dato etiqueta="Dirección" valor={beneficiario.direccion} />
                <Dato etiqueta="Registrado" valor={beneficiario.registrado ? `${fechaHora(beneficiario.registrado)} · ${hace(beneficiario.registrado)}` : null} />
            </CardContent>
        </Card>
    );
}

function Dato({ etiqueta, valor, capitalizar = false }: { etiqueta: string; valor: string | null | undefined; capitalizar?: boolean }) {
    return (
        <div className="flex justify-between gap-3 border-b border-border/60 pb-2">
            <span className="text-muted-foreground">{etiqueta}</span>
            <span className={cn('text-right font-medium', capitalizar && 'capitalize')}>{valor || '—'}</span>
        </div>
    );
}

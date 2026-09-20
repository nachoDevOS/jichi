import { Head, Link, router, usePage } from '@inertiajs/react';
import { BadgeCheck, Pencil, Trash2, User, Wallet, Waves } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarAccion } from '@/components/ui/confirmar-accion';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { BeneficiarioFicha, CarnetResumen, CupoResumen } from '@/types/beneficiarios';

/**
 * La ficha del beneficiario.
 */
export default function VerBeneficiario({
    beneficiario,
    gestion,
    deuda,
    carnets,
    cupos,
}: {
    beneficiario: BeneficiarioFicha;
    gestion: number;
    deuda: number;
    /** Sus credenciales. Pueden ser DOS vigentes: pescador y comercializador. */
    carnets: CarnetResumen[];
    /** Sus bolsas madre, de todas las gestiones. */
    cupos: CupoResumen[];
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [confirmarBaja, setConfirmarBaja] = useState(false);

    return (
        <LayoutPanel
            titulo={beneficiario.nombreCompleto}
            descripcion={beneficiario.documento_identidad}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {/*
                        El atajo al paso 2 del flujo. Lleva el id en la URL para
                        que el formulario abra con la persona ya elegida: quien
                        viene de acá acaba de elegirla, y volver a pedírsela es
                        hacerle repetir el paso.
                    */}
                    {puede('aprovechamientos.crear') && (
                        <Button
                            onClick={() =>
                                router.visit(
                                    route('aprovechamientos.create', { beneficiario: beneficiario.id }),
                                )
                            }
                        >
                            <Waves className="size-4" />
                            Otorgar cupo
                        </Button>
                    )}

                    {puede('carnets.crear') && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.visit(route('carnets.create', { beneficiario: beneficiario.id }))
                            }
                        >
                            <BadgeCheck className="size-4" />
                            Emitir carnet
                        </Button>
                    )}

                    {puede('beneficiarios.editar') && (
                        <Button
                            variant="outline"
                            onClick={() => router.visit(route('beneficiarios.edit', beneficiario.id))}
                        >
                            <Pencil className="size-4" />
                            Editar
                        </Button>
                    )}

                    {puede('beneficiarios.eliminar') && (
                        <Button variant="ghost" onClick={() => setConfirmarBaja(true)}>
                            <Trash2 className="size-4" />
                            Dar de baja
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={beneficiario.nombreCompleto} />

            <div className="grid gap-6 lg:grid-cols-3">
                {/* ------------------------------------------------ Columna izquierda */}
                <div className="space-y-6">
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 pt-5 text-center">
                            <div className="flex size-28 items-center justify-center overflow-hidden rounded-full bg-muted">
                                {beneficiario.foto_url ? (
                                    <img
                                        src={beneficiario.foto_url}
                                        alt={beneficiario.nombreCompleto}
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <User className="size-10 text-muted-foreground" />
                                )}
                            </div>

                            <div>
                                <p className="font-semibold">{beneficiario.nombreCompleto}</p>
                                <p className="text-sm text-muted-foreground">
                                    {beneficiario.documento_identidad}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Datos personales</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <Dato etiqueta="Nacimiento" valor={fecha(beneficiario.fechaNacimiento)} />
                            <Dato etiqueta="Género" valor={beneficiario.genero} />
                            <Dato etiqueta="Nacionalidad" valor={beneficiario.nacionalidad} />
                            <Dato etiqueta="Teléfono" valor={beneficiario.telefono} />
                            <Dato etiqueta="Correo" valor={beneficiario.email} />
                            <Dato etiqueta="Ciudad" valor={beneficiario.ciudad} />
                            <Dato etiqueta="Provincia" valor={beneficiario.provincia} />
                            <Dato etiqueta="Dirección" valor={beneficiario.direccion} />
                        </CardContent>
                    </Card>
                </div>

                {/* ------------------------------------------------ Columna derecha */}
                <div className="space-y-6 lg:col-span-2">
                    <ResumenDeCredenciales carnets={carnets} gestion={gestion} />

                    {deuda > 0 && (
                        <Card className="border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
                            <CardContent className="pt-5 text-sm">
                                <p className="font-medium text-amber-900 dark:text-amber-200">
                                    Saldo pendiente: {bs(deuda, institucion.moneda)}
                                </p>
                                <p className="text-amber-800/80 dark:text-amber-200/80">
                                    Suma de lo que falta cobrar de sus carnets, cupos y guías. Se
                                    puede pagar en cuotas: cada abono baja este número.
                                </p>

                                {/*
                                    El atajo a caja. Solo aparece cuando hay algo
                                    que cobrar —el bloque entero se dibuja con
                                    `deuda > 0`— así que no hace falta volver a
                                    preguntarlo acá.
                                */}
                                {puede('caja.cobrar') && (
                                    <Button
                                        className="mt-3"
                                        onClick={() =>
                                            router.visit(
                                                route('caja.create', { beneficiario: beneficiario.id }),
                                            )
                                        }
                                    >
                                        <Wallet className="size-4" />
                                        Cobrar
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <Card className="min-w-0">
                        <CardHeader>
                            <CardTitle>Carnets</CardTitle>
                        </CardHeader>

                        <CardContent>
                            {carnets.length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    Todavía no tiene ningún carnet emitido.
                                </p>
                            ) : (
                                <ul className="divide-y divide-border">
                                    {carnets.map((c) => (
                                        <li key={c.id} className="flex flex-wrap items-center gap-3 py-3">
                                            {/*
                                                El código va en grupos de cuatro —lo arma el
                                                servidor— porque catorce caracteres seguidos no se
                                                pueden dictar por teléfono ni tipear de un plástico
                                                gastado.
                                            */}
                                            <Link
                                                href={route('carnets.show', c.id)}
                                                className="font-mono text-sm font-medium tabular-nums text-primary hover:underline"
                                            >
                                                {c.codigo}
                                            </Link>

                                            <Badge color={c.tipo_actor_color}>
                                                {c.tipo_actor_etiqueta}
                                            </Badge>

                                            <Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>

                                            <span className="text-sm text-muted-foreground">
                                                {c.asociacion ?? '—'} · vence{' '}
                                                {fecha(c.fecha_vencimiento)}
                                            </span>

                                            <span className="ml-auto flex flex-wrap items-center gap-2 text-sm">
                                                {/* Solo el pescador lleva cupo impreso. */}
                                                {c.cupo_kg !== null && (
                                                    <span className="text-muted-foreground">
                                                        {c.cupo_kg} kg
                                                    </span>
                                                )}

                                                {c.saldo_pendiente > 0 ? (
                                                    <span className="tabular-nums text-amber-700 dark:text-amber-400">
                                                        debe {bs(c.saldo_pendiente, institucion.moneda)}
                                                    </span>
                                                ) : (
                                                    <span className="text-emerald-700 dark:text-emerald-400">
                                                        Pagado
                                                    </span>
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="min-w-0">
                        <CardHeader>
                            <CardTitle>Cupos de pesca</CardTitle>
                        </CardHeader>

                        <CardContent>
                            {cupos.length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    No tiene ningún aprovechamiento otorgado.
                                </p>
                            ) : (
                                <ul className="divide-y divide-border">
                                    {cupos.map((c) => (
                                        <li key={c.id} className="space-y-1.5 py-3">
                                            <div className="flex flex-wrap items-center gap-3">
                                                <Waves className="size-4 shrink-0 text-muted-foreground" />

                                                <Link
                                                    href={route('aprovechamientos.show', c.id)}
                                                    className="text-sm font-medium text-primary hover:underline"
                                                >
                                                    Escala {c.escala ?? '—'}
                                                </Link>

                                                <Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>

                                                <span className="text-sm text-muted-foreground">
                                                    {c.descripcion ?? '—'} · vence{' '}
                                                    {fecha(c.fecha_vencimiento)}
                                                </span>

                                                {/*
                                                    EL SALDO ES LO ÚNICO ACCIONABLE. «Tiene 500 kg»
                                                    no dice si puede salir a pescar mañana; «le
                                                    quedan 20» sí.
                                                */}
                                                <span className="ml-auto text-sm tabular-nums">
                                                    <strong>{c.saldo_kg}</strong>
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        / {c.volumen_total_kg} kg
                                                    </span>
                                                </span>
                                            </div>

                                            {/*
                                                La barra va con un div de ancho porcentual y no con
                                                una librería: para un solo valor, traer recharts
                                                sería cargar 100 KB para dibujar un rectángulo.
                                            */}
                                            <div className="h-1.5 overflow-hidden rounded-full bg-secondary">
                                                <div
                                                    className="h-full rounded-full bg-primary"
                                                    style={{ width: `${c.porcentaje_usado}%` }}
                                                />
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
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

/**
 * QUÉ PUEDE HACER ESTA PERSONA HOY.
 */
function ResumenDeCredenciales({ carnets, gestion }: { carnets: CarnetResumen[]; gestion: number }) {
    const vigentes = carnets.filter((c) => c.vigente);

    if (vigentes.length === 0) {
        return (
            <Card className="border-sky-300 bg-sky-50 dark:border-sky-500/40 dark:bg-sky-500/10">
                <CardContent className="flex items-start gap-3 pt-5">
                    <BadgeCheck className="mt-0.5 size-5 shrink-0 text-sky-700 dark:text-sky-300" />
                    <div className="text-sm">
                        <p className="font-medium text-sky-900 dark:text-sky-200">
                            Sin carnet vigente en la gestión {gestion}
                        </p>
                        <p className="text-sky-800/80 dark:text-sky-200/80">
                            Hasta que se le emita uno no puede sacar faenas ni guías: el carnet es la
                            autorización anual, y los permisos operativos cuelgan de él.
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="border-violet-300 bg-violet-50 dark:border-violet-500/40 dark:bg-violet-500/10">
            <CardContent className="flex items-start gap-3 pt-5">
                <BadgeCheck className="mt-0.5 size-5 shrink-0 text-violet-700 dark:text-violet-300" />
                <div className="min-w-0 text-sm">
                    <p className="font-medium text-violet-900 dark:text-violet-200">
                        {vigentes.length} carnet(s) vigente(s) en la gestión {gestion}
                    </p>

                    <p className="text-violet-800/80 dark:text-violet-200/80">
                        Cada actividad es un carnet propio. Quien pesca y además comercializa
                        necesita los dos.
                    </p>

                    <ul className="mt-2 flex flex-wrap gap-1">
                        {vigentes.map((c) => (
                            <li key={c.id}>
                                <Badge color={c.tipo_actor_color}>
                                    {c.tipo_actor_etiqueta}
                                    <span className="ml-1 opacity-70">
                                        · {c.tipo_actor === 'pescador' ? 'emite faenas' : 'emite guías'}
                                    </span>
                                </Badge>
                            </li>
                        ))}
                    </ul>
                </div>
            </CardContent>
        </Card>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string | null | undefined }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted-foreground">{etiqueta}</span>
            <span className="text-right font-medium">{valor || '—'}</span>
        </div>
    );
}

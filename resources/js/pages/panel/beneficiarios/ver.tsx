import { Head, Link, router, usePage } from '@inertiajs/react';
import { BadgeCheck, FilePlus2, Pencil, Trash2, User } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarAccion } from '@/components/ui/confirmar-accion';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { BeneficiarioFicha, CarnetResumen } from '@/types/beneficiarios';

/**
 * La ficha del beneficiario.
 *
 * Lo más útil de esta pantalla es el cartel de arriba a la derecha: dice, antes
 * de que el operador apriete nada, qué tipo de trámite le va a salir a esta
 * persona. Ver `AvisoProximoTramite`.
 */
export default function VerBeneficiario({
    beneficiario,
    gestion,
    deuda,
    carnetGestion,
    carnets,
}: {
    beneficiario: BeneficiarioFicha;
    gestion: number;
    deuda: number;
    /** El carnet de la gestión en curso, o null si no tiene. */
    carnetGestion: CarnetResumen | null;
    carnets: CarnetResumen[];
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
                    {puede('tramites.crear') && (
                        <Button
                            onClick={() =>
                                router.visit(route('tramites.create', { beneficiario: beneficiario.id }))
                            }
                        >
                            <FilePlus2 className="size-4" />
                            Nuevo trámite
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
                    <AvisoProximoTramite carnet={carnetGestion} gestion={gestion} />

                    {deuda > 0 && (
                        <Card className="border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
                            <CardContent className="pt-5 text-sm">
                                <p className="font-medium text-amber-900 dark:text-amber-200">
                                    Saldo pendiente: {bs(deuda, institucion.moneda)}
                                </p>
                                <p className="text-amber-800/80 dark:text-amber-200/80">
                                    Suma de todos sus trámites no rechazados. Un trámite no se aprueba
                                    hasta cubrir su costo.
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
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
                                            <Link
                                                href={route('carnets.show', c.id)}
                                                className="font-mono text-sm font-medium tabular-nums text-primary hover:underline"
                                            >
                                                {c.registro}
                                            </Link>

                                            <Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>

                                            <span className="text-sm text-muted-foreground">
                                                Gestión {c.gestion} · vence {fecha(c.fecha_vencimiento)}
                                            </span>

                                            <span className="ml-auto flex flex-wrap gap-1">
                                                {(c.rubros ?? []).map((r) => (
                                                    <Badge key={r} color="slate">
                                                        {r}
                                                    </Badge>
                                                ))}
                                            </span>
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
                descripcion="Dejará de aparecer en el padrón y no podrá iniciar trámites nuevos. Sus carnets y pagos históricos se conservan."
                textoConfirmar="Dar de baja"
                onCancelar={() => setConfirmarBaja(false)}
                onConfirmar={() => router.delete(route('beneficiarios.destroy', beneficiario.id))}
            />
        </LayoutPanel>
    );
}

/**
 * EL CARTEL QUE RESUME LA REGLA A.
 *
 * Le dice al operador, antes de que empiece a cargar nada, si a esta persona le
 * corresponde una emisión inicial o una adición de rubro. No es la decisión
 * final —esa la toma el servidor, con la fila bloqueada, al registrar— pero
 * evita la sorpresa de cargar un trámite pensando que es otra cosa.
 */
function AvisoProximoTramite({ carnet, gestion }: { carnet: CarnetResumen | null; gestion: number }) {
    if (carnet === null) {
        return (
            <Card className="border-sky-300 bg-sky-50 dark:border-sky-500/40 dark:bg-sky-500/10">
                <CardContent className="flex items-start gap-3 pt-5">
                    <BadgeCheck className="mt-0.5 size-5 shrink-0 text-sky-700 dark:text-sky-300" />
                    <div className="text-sm">
                        <p className="font-medium text-sky-900 dark:text-sky-200">
                            Sin carnet de la gestión {gestion}
                        </p>
                        <p className="text-sky-800/80 dark:text-sky-200/80">
                            El próximo trámite será una <strong>emisión inicial</strong>: se creará el
                            carnet del año y se habilitará el rubro solicitado.
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
                <div className="text-sm">
                    <p className="font-medium text-violet-900 dark:text-violet-200">
                        Ya tiene carnet de la gestión {gestion}
                    </p>
                    <p className="text-violet-800/80 dark:text-violet-200/80">
                        El próximo trámite será una <strong>adición de rubro</strong> sobre ese mismo
                        carnet. No se emite uno nuevo: la regla es un carnet por persona y gestión.
                    </p>
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

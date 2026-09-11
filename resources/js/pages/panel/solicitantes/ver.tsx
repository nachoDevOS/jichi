import { Head, Link, router } from '@inertiajs/react';
import { FileText, Pencil, Trash2, User } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarAccion } from '@/components/ui/confirmar-accion';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha, fechaHora } from '@/lib/utils';
import type { SolicitanteFicha, TramiteDelSolicitante } from '@/types/solicitantes';

/**
 * ============================================================================
 *  FICHA DEL SOLICITANTE
 * ============================================================================
 *
 * Todo lo que ventanilla necesita ver de una persona: sus datos, cuánto debe y
 * el historial de trámites que hizo en la institución.
 */

interface Props {
    solicitante: SolicitanteFicha;
    /** Suma de lo que falta pagar en todos sus trámites. */
    deuda: number;
    tramites: TramiteDelSolicitante[];
}

export default function VerSolicitante({ solicitante, deuda, tramites }: Props) {
    const { puede } = usePermisos();

    // Controla si la ventana de confirmación de baja está abierta.
    const [confirmandoBaja, setConfirmandoBaja] = useState(false);
    const [procesando, setProcesando] = useState(false);

    function darDeBaja() {
        setProcesando(true);

        /*
         * router.delete() manda una petición DELETE a la ruta de baja. A
         * diferencia de useForm, se usa cuando no hay un formulario de por
         * medio: solo se dispara una acción.
         *
         * onFinish se ejecuta termine bien o mal, así el botón se desbloquea
         * igual si el servidor devuelve un error.
         */
        router.delete(route('solicitantes.destroy', solicitante.id), {
            onFinish: () => {
                setProcesando(false);
                setConfirmandoBaja(false);
            },
        });
    }

    return (
        <LayoutPanel
            titulo={solicitante.nombreCompleto}
            descripcion={solicitante.documento_identidad}
            acciones={
                <>
                    {puede('solicitantes.editar') && (
                        <Link href={route('solicitantes.edit', solicitante.id)}>
                            <Button variant="outline">
                                <Pencil className="size-4" />
                                Editar
                            </Button>
                        </Link>
                    )}

                    {puede('solicitantes.eliminar') && (
                        <Button variant="destructive" onClick={() => setConfirmandoBaja(true)}>
                            <Trash2 className="size-4" />
                            Dar de baja
                        </Button>
                    )}
                </>
            }
        >
            <Head title={solicitante.nombreCompleto} />

            <div className="grid gap-4 lg:grid-cols-3">
                {/* ---------- Columna izquierda: identidad ---------- */}
                <div className="space-y-4">
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 p-6 text-center">
                            <div className="flex size-24 items-center justify-center overflow-hidden rounded-full border border-border bg-muted">
                                {solicitante.foto_url ? (
                                    <img
                                        src={solicitante.foto_url}
                                        alt={`Fotografía de ${solicitante.nombreCompleto}`}
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <User className="size-10 text-muted-foreground" />
                                )}
                            </div>

                            <div>
                                <p className="font-semibold">{solicitante.nombreCompleto}</p>
                                <p className="font-mono text-sm text-muted-foreground">
                                    {solicitante.documento_identidad}
                                </p>
                            </div>

                            {/* La deuda solo se muestra si existe: un cero grande
                                en rojo asustaría sin motivo. */}
                            {deuda > 0 && (
                                <div className="w-full rounded-lg border border-destructive/30 bg-destructive/5 p-3">
                                    <p className="text-xs text-muted-foreground">Saldo pendiente</p>
                                    <p className="text-lg font-semibold text-destructive tabular-nums">
                                        {bs(deuda)}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Datos de contacto</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-3 text-sm">
                            <Dato etiqueta="Cédula de Identidad">
                                <span className="font-mono">{solicitante.documento_identidad}</span>
                            </Dato>

                            <Dato etiqueta="Teléfono">{solicitante.telefono}</Dato>
                            <Dato etiqueta="Correo">{solicitante.email}</Dato>
                            <Dato etiqueta="Ciudad">{solicitante.ciudad}</Dato>
                            <Dato etiqueta="Provincia">{solicitante.provincia}</Dato>
                            <Dato etiqueta="Dirección">{solicitante.direccion}</Dato>
                            <Dato etiqueta="Nacionalidad">{solicitante.nacionalidad}</Dato>

                            {solicitante.fechaNacimiento && (
                                <Dato etiqueta="Nacimiento">
                                    {fecha(solicitante.fechaNacimiento)}
                                </Dato>
                            )}

                            <Dato etiqueta="Registrado">{fechaHora(solicitante.registrado)}</Dato>
                        </CardContent>
                    </Card>

                </div>

                {/* ---------- Columna derecha: historial ---------- */}
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Historial de trámites</CardTitle>
                    </CardHeader>

                    <CardContent className="px-0">
                        {tramites.length === 0 ? (
                            <EstadoVacio
                                icono={FileText}
                                titulo="Sin trámites registrados"
                                descripcion="Este solicitante todavía no inició ningún trámite."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2 font-medium">Código</th>
                                            <th className="px-5 py-2 font-medium">Trámite</th>
                                            <th className="px-5 py-2 font-medium">Estado</th>
                                            <th className="px-5 py-2 text-right font-medium">Monto</th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {tramites.map((t) => (
                                            <tr key={t.id} className="hover:bg-muted/30">
                                                <td className="whitespace-nowrap px-5 py-2.5 font-mono text-xs">
                                                    {t.codigo}
                                                    <span className="block text-[11px] text-muted-foreground">
                                                        {fechaHora(t.creado)}
                                                    </span>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <span className="mr-1">{t.icono}</span>
                                                    {t.tipo}
                                                    <span className="block text-xs text-muted-foreground">
                                                        {t.area}
                                                    </span>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <Badge color={t.estado_color}>{t.estado_etiqueta}</Badge>
                                                </td>

                                                <td className="whitespace-nowrap px-5 py-2.5 text-right tabular-nums">
                                                    {bs(t.monto_total)}
                                                    {t.saldo_pendiente > 0 && (
                                                        <span className="block text-xs text-destructive">
                                                            debe {bs(t.saldo_pendiente)}
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <ConfirmarAccion
                abierto={confirmandoBaja}
                titulo="¿Dar de baja a este solicitante?"
                descripcion={
                    <>
                        Dejará de aparecer en los listados y no podrá iniciar trámites nuevos.
                        <br />
                        La ficha no se borra: sus trámites, pagos y documentos anteriores
                        siguen figurando a su nombre.
                    </>
                }
                textoConfirmar="Dar de baja"
                procesando={procesando}
                onConfirmar={darDeBaja}
                onCancelar={() => setConfirmandoBaja(false)}
            />
        </LayoutPanel>
    );
}

/**
 * Un renglón "etiqueta / valor". Si el valor está vacío muestra un guion, para
 * que la ficha mantenga siempre la misma forma y se lea de un vistazo.
 */
function Dato({ etiqueta, children }: { etiqueta: string; children: ReactNode }) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <span className="shrink-0 text-muted-foreground">{etiqueta}</span>
            <span className="min-w-0 truncate text-right">{children || '—'}</span>
        </div>
    );
}

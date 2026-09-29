import { router, usePage } from '@inertiajs/react';
import { KeyRound, Printer, ShieldOff, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarAccion } from '@/components/ui/confirmar-accion';
import { usePermisos } from '@/hooks/use-permisos';
import { fechaHora } from '@/lib/utils';
import type { CredencialPortal, PageProps } from '@/types';
import type { AccesoPortal as Acceso } from '@/types/beneficiarios';

/**
 * La cuenta del portal /mi-cuenta: darla, resetear la clave o cortarla.
 *
 * La clave temporal llega UNA vez por flash y no queda guardada legible: si se
 * pierde antes de entregarla, se resetea.
 */
export function AccesoPortal({ beneficiarioId, acceso }: { beneficiarioId: number; acceso: Acceso | null }) {
    const { puede } = usePermisos();
    const { flash } = usePage<PageProps>().props;
    const [confirmar, setConfirmar] = useState<'resetear' | 'desactivar' | null>(null);

    const opciones = { preserveScroll: true, onFinish: () => setConfirmar(null) };

    return (
        <Card className="mt-6">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    Acceso al portal «Mi cuenta»
                    {acceso &&
                        (acceso.activo ? (
                            <Badge color="emerald">Habilitado</Badge>
                        ) : (
                            <Badge color="rose">Desactivado</Badge>
                        ))}
                </CardTitle>
                <CardDescription>
                    Con su cédula y una contraseña, la persona consulta desde el celular sus papeles vigentes y sus
                    pagos. Solo puede mirar: no hace trámites.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                {flash.cuenta_portal && <Credencial credencial={flash.cuenta_portal} />}

                {acceso ? (
                    <dl className="grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt className="text-xs text-muted-foreground">Creada</dt>
                            <dd>{fechaHora(acceso.creada)}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">Último ingreso</dt>
                            <dd>{acceso.ultimo_acceso ? fechaHora(acceso.ultimo_acceso) : 'Nunca entró'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">Contraseña</dt>
                            <dd>{acceso.debe_cambiar_password ? 'Temporal, sin cambiar' : 'Propia'}</dd>
                        </div>
                    </dl>
                ) : (
                    <p className="text-sm text-muted-foreground">Todavía no tiene acceso al portal.</p>
                )}

                {puede('beneficiarios.portal') && (
                    <div className="flex flex-wrap gap-2">
                        {!acceso && (
                            <Button
                                onClick={() =>
                                    router.post(route('beneficiarios.portal.store', beneficiarioId), {}, opciones)
                                }
                            >
                                <UserPlus className="size-4" />
                                Dar acceso al portal
                            </Button>
                        )}
                        {acceso && (
                            <Button variant="outline" onClick={() => setConfirmar('resetear')}>
                                <KeyRound className="size-4" />
                                {acceso.activo ? 'Resetear contraseña' : 'Reactivar con contraseña nueva'}
                            </Button>
                        )}
                        {acceso?.activo && (
                            <Button variant="eliminar" onClick={() => setConfirmar('desactivar')}>
                                <ShieldOff className="size-4" />
                                Desactivar acceso
                            </Button>
                        )}
                    </div>
                )}
            </CardContent>

            <ConfirmarAccion
                abierto={confirmar === 'resetear'}
                titulo="¿Generar una contraseña nueva?"
                descripcion="La contraseña actual deja de funcionar. La nueva es temporal: la persona la cambia al entrar."
                textoConfirmar="Generar contraseña"
                onCancelar={() => setConfirmar(null)}
                onConfirmar={() => router.patch(route('beneficiarios.portal.resetear', beneficiarioId), {}, opciones)}
            />
            <ConfirmarAccion
                abierto={confirmar === 'desactivar'}
                titulo="¿Desactivar el acceso al portal?"
                descripcion="La persona deja de poder entrar desde ya. Se vuelve a habilitar reseteando la contraseña."
                textoConfirmar="Desactivar"
                onCancelar={() => setConfirmar(null)}
                onConfirmar={() => router.patch(route('beneficiarios.portal.desactivar', beneficiarioId), {}, opciones)}
            />
        </Card>
    );
}

/** La clave recién generada, para dictarla o imprimirla. Se ve una sola vez. */
function Credencial({ credencial }: { credencial: CredencialPortal }) {
    return (
        <div className="rounded-lg border-2 border-dashed border-emerald-400 bg-emerald-50 p-4 dark:bg-emerald-500/10">
            <p className="text-sm font-semibold text-emerald-900 dark:text-emerald-200">
                Entregue estos datos ahora: la contraseña no se vuelve a mostrar.
            </p>
            <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <div>
                    <dt className="text-xs text-muted-foreground">Usuario (cédula)</dt>
                    <dd className="font-mono text-lg font-bold">{credencial.usuario}</dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">Contraseña temporal</dt>
                    <dd className="font-mono text-lg font-bold tracking-widest">{credencial.clave}</dd>
                </div>
            </dl>
            <Button variant="outline" className="mt-3" onClick={() => imprimir(credencial)}>
                <Printer className="size-4" />
                Imprimir comprobante
            </Button>
        </div>
    );
}

// Una ventana aparte con solo el comprobante: imprimir la ficha entera gastaría hojas.
function imprimir(c: CredencialPortal) {
    const ventana = window.open('', '_blank', 'width=480,height=640');
    if (!ventana) return;

    const texto = (s: string) =>
        s.replace(/[&<>"]/g, (x) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[x]!);

    ventana.document.write(`<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Acceso a Mi cuenta</title>
<style>body{font-family:system-ui,sans-serif;padding:24px;color:#0f2a3a}h1{font-size:18px;margin:0 0 4px}
p{font-size:13px;line-height:1.5}.dato{border:1px solid #9ab;border-radius:8px;padding:10px 12px;margin:8px 0}
.dato b{display:block;font-size:11px;color:#567;text-transform:uppercase;letter-spacing:.05em}
.dato span{font:700 20px monospace;letter-spacing:.1em}</style></head><body>
<h1>SEDAG Beni · Mi cuenta</h1><p>${texto(c.nombre)}</p>
<div class="dato"><b>Dirección</b><span style="font-size:14px;letter-spacing:0">${texto(c.url)}</span></div>
<div class="dato"><b>Usuario (cédula)</b><span>${texto(c.usuario)}</span></div>
<div class="dato"><b>Contraseña temporal</b><span>${texto(c.clave)}</span></div>
<p>Al entrar por primera vez el sistema le pedirá elegir una contraseña propia. No comparta estos datos.
Si olvida su contraseña, acérquese a ventanilla con su cédula.</p>
<script>window.onload=()=>window.print()</script></body></html>`);
    ventana.document.close();
}

import { Link } from '@inertiajs/react';
import { CalendarClock, Printer, Truck } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { fecha } from '@/lib/utils';
import type { AvisoCierreGestion } from '@/types/dashboard';

/**
 * El aviso de fin de gestión y el trabajo pendiente de ventanilla.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ESTE BLOQUE NO ES UNA LISTA DE VENCIMIENTOS
 * ----------------------------------------------------------------------------
 *
 * En un sistema de credenciales que vencen a los N días de emitidas, lo útil es
 * una lista: «estos cinco vencen esta semana». Acá no sirve, porque TODOS los
 * carnets de la gestión vencen el mismo día, el 31 de diciembre. La lista
 * tendría miles de filas idénticas.
 *
 * Lo que sí importa es el número: cuánta gente va a tener que renovar de golpe y
 * con cuánto tiempo. Y, al lado, lo que se puede hacer hoy: los carnets ya
 * aprobados que faltan imprimir y los impresos que esperan en el cajón.
 */
export function PanelCierreGestion({ aviso, gestion }: { aviso: AvisoCierreGestion; gestion: number }) {
    const urgente = aviso.dias_restantes <= 45;

    return (
        <Card className="lg:col-span-2">
            <CardHeader>
                <CardTitle>Cierre de gestión {gestion}</CardTitle>
                <CardDescription>
                    Todos los carnets vencen el mismo día, no a los 365 de emitidos.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                <div
                    className={
                        urgente
                            ? 'flex items-start gap-3 rounded-md bg-amber-50 p-4 dark:bg-amber-500/10'
                            : 'flex items-start gap-3 rounded-md bg-secondary/50 p-4'
                    }
                >
                    <CalendarClock
                        className={
                            urgente
                                ? 'mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300'
                                : 'mt-0.5 size-5 shrink-0 text-muted-foreground'
                        }
                    />

                    <div className="text-sm">
                        <p className="font-medium">
                            {aviso.cantidad} carnet(s) vigente(s) vencen el {fecha(aviso.fecha_vencimiento)}
                        </p>
                        <p className="text-muted-foreground">
                            Faltan {aviso.dias_restantes} día(s). A partir de ahí, cada beneficiario
                            necesita un carnet nuevo de la gestión siguiente.
                        </p>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <PendienteVentanilla
                        icono={Printer}
                        cantidad={aviso.sin_imprimir}
                        titulo="Aprobados sin imprimir"
                        descripcion="El supervisor ya firmó: falta generar el documento."
                    />

                    <PendienteVentanilla
                        icono={Truck}
                        cantidad={aviso.sin_entregar}
                        titulo="Impresos sin entregar"
                        descripcion="Están en el cajón esperando que el titular los retire."
                    />
                </div>

                <Link
                    href={route('tramites.index', { estado: 'aprobado' })}
                    className="inline-block text-sm text-primary hover:underline"
                >
                    Ver los trámites aprobados
                </Link>
            </CardContent>
        </Card>
    );
}

function PendienteVentanilla({
    icono: Icono,
    cantidad,
    titulo,
    descripcion,
}: {
    icono: typeof Printer;
    cantidad: number;
    titulo: string;
    descripcion: string;
}) {
    return (
        <div className="rounded-md border border-border p-3">
            <div className="flex items-center gap-2">
                <Icono className="size-4 text-muted-foreground" />
                <span className="text-2xl font-semibold tabular-nums">{cantidad}</span>
            </div>

            <p className="mt-1 text-sm font-medium">{titulo}</p>
            <p className="text-xs text-muted-foreground">{descripcion}</p>
        </div>
    );
}

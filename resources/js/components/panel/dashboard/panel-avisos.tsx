import { Link } from '@inertiajs/react';
import { BadgeCheck, CalendarClock, Fish, PackageX, Truck } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { Avisos } from '@/types/dashboard';

/**
 * Lo que está por caducar y lo que ya caducó sin cerrarse.
 *
 * ----------------------------------------------------------------------------
 *  SON DOS COSAS DISTINTAS Y VAN SEPARADAS A PROPÓSITO
 * ----------------------------------------------------------------------------
 *
 * Arriba, lo que VA A VENCER: carnets y cupos dentro del plazo de aviso. Es
 * trabajo que se puede anticipar —avisarle a la gente que renueve— y no hay
 * nada mal todavía.
 *
 * Abajo, lo que YA SE PASÓ DE FECHA y sigue abierto: una faena o una guía
 * vencida sin cerrar es un papel que alguien se llevó y del que nadie registró
 * la vuelta. Eso no es una previsión, es algo que hay que ir a buscar.
 *
 * Mezclados en una sola lista, lo segundo se pierde entre lo primero, que
 * siempre es más numeroso.
 */
export function PanelAvisos({ avisos }: { avisos: Avisos }) {
    const sinCerrar = avisos.faenas_vencidas + avisos.guias_vencidas;

    return (
        <Card className="lg:col-span-2">
            <CardHeader>
                <CardTitle>Avisos</CardTitle>
                <CardDescription>
                    Lo que vence dentro de {avisos.dias_aviso} días y lo que quedó sin cerrar.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                {/*
                    El recuadro de arriba se pinta ámbar solo si hay algo sin
                    cerrar. Pintado siempre, el color deja de significar nada y
                    quien mira el tablero aprende a ignorarlo — que es lo
                    contrario de lo que un aviso viene a hacer.
                */}
                <div
                    className={
                        sinCerrar > 0
                            ? 'flex items-start gap-3 rounded-md bg-amber-50 p-4 dark:bg-amber-500/10'
                            : 'flex items-start gap-3 rounded-md bg-secondary/50 p-4'
                    }
                >
                    <CalendarClock
                        className={
                            sinCerrar > 0
                                ? 'mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300'
                                : 'mt-0.5 size-5 shrink-0 text-muted-foreground'
                        }
                    />

                    <div className="text-sm">
                        <p className="font-medium">
                            {sinCerrar > 0
                                ? `${sinCerrar} permiso(s) vencido(s) sin cerrar`
                                : 'Sin permisos vencidos pendientes'}
                        </p>
                        <p className="text-muted-foreground">
                            {sinCerrar > 0
                                ? 'Son papeles que salieron y de los que nadie registró la vuelta. Hay que cerrarlos o anularlos.'
                                : 'Todas las faenas y guías emitidas están dentro de fecha o ya se cerraron.'}
                        </p>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <Aviso
                        icono={BadgeCheck}
                        cantidad={avisos.carnets_por_vencer}
                        titulo="Carnets por vencer"
                        descripcion={`Vencen dentro de ${avisos.dias_aviso} días: hay que avisar para que renueven.`}
                    />

                    <Aviso
                        icono={CalendarClock}
                        cantidad={avisos.cupos_por_vencer}
                        titulo="Cupos por vencer"
                        descripcion="El volumen que quede sin usar se pierde: no se arrastra a la gestión siguiente."
                    />

                    <Aviso
                        icono={PackageX}
                        cantidad={avisos.cupos_agotados}
                        titulo="Cupos agotados"
                        descripcion="Se acabaron los kilos, no el tiempo. Lo que corresponde es una ampliación."
                    />

                    <Aviso
                        icono={Fish}
                        cantidad={avisos.faenas_vencidas}
                        titulo="Faenas vencidas"
                        descripcion="Pasaron su fecha límite y siguen activas. Su volumen se libera al vencerlas."
                    />

                    <Aviso
                        icono={Truck}
                        cantidad={avisos.guias_vencidas}
                        titulo="Guías vencidas"
                        descripcion="Pasaron los 5 días de validez y siguen activas."
                    />
                </div>

                <Link
                    href={route('carnets.index')}
                    className="inline-block text-sm text-primary hover:underline"
                >
                    Ver todos los carnets
                </Link>
            </CardContent>
        </Card>
    );
}

function Aviso({
    icono: Icono,
    cantidad,
    titulo,
    descripcion,
}: {
    icono: typeof Truck;
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

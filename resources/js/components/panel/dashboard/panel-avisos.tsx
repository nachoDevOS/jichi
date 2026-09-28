import { Link } from '@inertiajs/react';
import { BadgeCheck, BellRing, CalendarClock, CheckCircle2, PackageX } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { Avisos } from '@/types/dashboard';

/**
 * Lo que vence pronto o ya venció y todavía pide una acción. Solo se listan los que tienen algo:
 * una lista de ceros enseña a no mirarla.
 */
export function PanelAvisos({ avisos }: { avisos: Avisos }) {
    const lista: { icono: LucideIcon; cantidad: number; texto: string; accion: string; href: string }[] = [
        {
            icono: BadgeCheck,
            cantidad: avisos.carnets_por_vencer,
            texto: `carnet(s) vencen en ${avisos.dias_aviso} días`,
            accion: 'Avisar para que renueven',
            href: avisos.urls.carnets,
        },
        {
            icono: CalendarClock,
            cantidad: avisos.autorizaciones_por_vencer,
            texto: `autorización(es) de pesca vencen en ${avisos.dias_aviso} días`,
            accion: 'Los kilos sin usar no pasan a la gestión siguiente',
            href: avisos.urls.autorizaciones,
        },
        {
            icono: PackageX,
            cantidad: avisos.autorizaciones_agotadas,
            texto: 'autorización(es) de pesca sin kilos',
            accion: 'El pescador necesita una nueva para seguir pescando',
            href: avisos.urls.agotadas,
        },
    ].filter((a) => a.cantidad > 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <BellRing className="size-4.5 text-muted-foreground" />
                    Avisos
                </CardTitle>
                <CardDescription>Lo que vence pronto o necesita atención.</CardDescription>
            </CardHeader>

            <CardContent>
                {lista.length === 0 ? (
                    <p className="flex items-start gap-2 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
                        <CheckCircle2 className="mt-0.5 size-4.5 shrink-0" />
                        Nada por vencer.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {lista.map((a) => (
                            <li key={a.texto}>
                                <Link
                                    href={a.href}
                                    className="flex items-start gap-3 rounded-md border border-amber-600/20 bg-amber-50 p-3 transition-colors hover:bg-amber-100 dark:bg-amber-500/10 dark:hover:bg-amber-500/15"
                                >
                                    <a.icono className="mt-0.5 size-4.5 shrink-0 text-amber-700 dark:text-amber-300" />
                                    <span className="text-sm">
                                        <span className="font-semibold">
                                            {a.cantidad} {a.texto}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">{a.accion}</span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

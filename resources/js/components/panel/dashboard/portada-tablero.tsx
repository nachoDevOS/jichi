import { Link } from '@inertiajs/react';
import { CalendarDays, Hourglass, Wallet } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Conteo } from '@/components/panel/comunes/ficha';
import { bs } from '@/lib/utils';

// Un período de 720 repetido cuatro veces: correr la mitad (1440) cierra el bucle sin salto.
const TRAZO_OLA =
    'M0,40 C180,0 540,80 720,40 C900,0 1260,80 1440,40 C1620,0 1980,80 2160,40 C2340,0 2700,80 2880,40 L2880,100 L0,100 Z';

/** El saludo según la hora de Bolivia, que es la de la aplicación. */
function saludo(): string {
    const hora = new Date().getHours();
    return hora < 12 ? 'Buenos días' : hora < 19 ? 'Buenas tardes' : 'Buenas noches';
}

/**
 * La portada del tablero: quién entra, qué día es y lo que más se pregunta en
 * ventanilla —cuánto se cobró hoy y cuánto espera el pago—.
 */
export function PortadaTablero({
    nombre,
    gestion,
    cobradoHoy,
    cobradoMes,
    esperando,
    moneda,
}: {
    nombre: string;
    gestion: number;
    cobradoHoy: number;
    cobradoMes: number;
    esperando: number;
    moneda: string;
}) {
    const hoy = new Intl.DateTimeFormat('es-BO', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date());

    return (
        <section
            className="relative overflow-hidden rounded-2xl px-6 pt-6 pb-14 text-white shadow-md sm:px-8"
            style={{
                background:
                    'radial-gradient(700px 220px at 90% -10%, color-mix(in oklch, var(--rio-claro) 45%, transparent), transparent 70%),' +
                    'linear-gradient(120deg, var(--rio-profundo) 0%, var(--rio) 60%, color-mix(in oklch, var(--rio) 70%, var(--rio-claro)) 100%)',
            }}
        >
            <div className="relative z-10 flex flex-wrap items-end justify-between gap-6">
                <div className="min-w-0">
                    <p className="flex items-center gap-2 text-sm text-white/70 first-letter:uppercase">
                        <CalendarDays className="size-4 text-institucional-dorado" />
                        <span className="first-letter:uppercase">{hoy}</span>
                    </p>
                    <h2 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                        {saludo()}, {nombre.split(' ')[0]}
                    </h2>
                    <p className="mt-1 text-sm text-white/75">Así va la pesca en el Beni · Gestión {gestion}</p>
                </div>

                <div className="grid w-full grid-cols-3 gap-2 sm:w-auto sm:gap-3">
                    <Vidrio icono={Wallet} rotulo="Cobrado hoy">
                        <Conteo valor={cobradoHoy} formato={(n) => bs(n, moneda)} />
                    </Vidrio>
                    <Vidrio icono={Wallet} rotulo="Este mes">
                        <Conteo valor={cobradoMes} formato={(n) => bs(n, moneda)} />
                    </Vidrio>
                    <Vidrio icono={Hourglass} rotulo="Por pagar" acento={esperando > 0}>
                        {esperando > 0 ? (
                            <Link href="#pendientes" className="hover:underline">
                                <Conteo valor={esperando} formato={(n) => String(n)} />
                            </Link>
                        ) : (
                            '0'
                        )}
                    </Vidrio>
                </div>
            </div>

            {/* Agua que corre: dos capas a distinta velocidad dan profundidad. Se apagan con movimiento reducido. */}
            <div className="pointer-events-none absolute inset-x-0 bottom-0 h-14 overflow-hidden" aria-hidden>
                <svg
                    viewBox="0 0 2880 100"
                    preserveAspectRatio="none"
                    className="animar-agua-lenta absolute bottom-2 left-0 h-full w-[200%] text-white/10"
                >
                    <path d={TRAZO_OLA} fill="currentColor" />
                </svg>
                <svg
                    viewBox="0 0 2880 100"
                    preserveAspectRatio="none"
                    className="animar-agua absolute bottom-0 left-0 h-2/3 w-[200%] text-white/15"
                >
                    <path d={TRAZO_OLA} fill="currentColor" />
                </svg>
            </div>
        </section>
    );
}

function Vidrio({
    icono: Icono,
    rotulo,
    acento = false,
    children,
}: {
    icono: LucideIcon;
    rotulo: string;
    acento?: boolean;
    children: ReactNode;
}) {
    return (
        <div
            className={
                acento
                    ? 'min-w-0 rounded-xl bg-amber-400/20 px-3 py-2.5 ring-1 ring-amber-300/50 backdrop-blur-sm sm:px-4'
                    : 'min-w-0 rounded-xl bg-white/10 px-3 py-2.5 ring-1 ring-white/15 backdrop-blur-sm sm:px-4'
            }
        >
            <p className="flex items-center gap-1.5 truncate text-[10px] font-medium tracking-wide text-white/70 uppercase sm:text-[11px]">
                <Icono className="hidden size-3.5 shrink-0 text-institucional-dorado sm:block" />
                {rotulo}
            </p>
            <p className="mt-0.5 truncate text-base font-semibold tabular-nums sm:text-xl">{children}</p>
        </div>
    );
}

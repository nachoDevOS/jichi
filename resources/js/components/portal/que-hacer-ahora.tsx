import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDown,
    CalendarClock,
    CheckCircle2,
    ChevronRight,
    Hourglass,
    QrCode,
    Wallet,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { TextoCopiable } from '@/components/comunes/texto-copiable';
import { detalleDocumento, ICONO_DOCUMENTO } from '@/components/portal/documento';
import { VentanaPagoQr } from '@/components/portal/ventana-pago-qr';
import { bs, cn } from '@/lib/utils';
import type { PapelPortal } from '@/types/portal';

/**
 * Lo PRIMERO que tiene que hacer hoy, en una sola tarjeta. El orden es de urgencia:
 * código vencido, pagar, esperar la validación, renovar lo que vence y, si no hay
 * nada, todo en orden. Cada caso sale de lo que ya calculó ResumenPortal.
 */
export function QueHacerAhora({
    abiertos,
    vigentes,
    deuda,
    moneda,
}: {
    abiertos: PapelPortal[];
    vigentes: PapelPortal[];
    deuda: number;
    moneda: string;
}) {
    const [pagando, setPagando] = useState<PapelPortal | null>(null);
    const caidos = abiertos.filter((t) => t.pago === 'caida');
    const porPagar = abiertos.filter((t) => t.pago === 'sin_pago' || (t.pago === null && t.debe > 0));
    const enRevision = abiertos.filter((t) => t.pago === 'revision' || t.pago === 'validado');
    const porVencer = vigentes.filter((p) => p.dias_restantes !== null && p.dias_restantes <= 30);

    let tarjeta: ReactNode;

    if (caidos.length > 0) {
        tarjeta = (
            <Tarjeta
                tono="rojo"
                icono={<AlertTriangle className="size-6" />}
                rotulo="Atención"
                titulo={caidos.length === 1 ? 'Su código de pago venció' : `${caidos.length} códigos de pago vencieron`}
                texto="El trámite sigue abierto. Acérquese a ventanilla con su cédula y le dan un código nuevo para pagar."
            >
                <ListaTramites tramites={caidos} />
            </Tarjeta>
        );
    } else if (porPagar.length > 0) {
        tarjeta = (
            <Tarjeta
                tono="dorado"
                icono={<Wallet className="size-6" />}
                rotulo="Lo que tiene que hacer"
                titulo={
                    <>
                        Pagar <span className="whitespace-nowrap tabular-nums">{bs(deuda, moneda)}</span>
                    </>
                }
                texto="Pague en el banco con el código de cada trámite, o con QR. Cuando Recaudaciones lo valide, su papel queda aprobado solo."
            >
                <ul className="space-y-2">
                    {porPagar.map((t, i) => (
                        <li
                            key={`${t.clase}-${t.codigo ?? i}`}
                            className="flex flex-col gap-3 rounded-xl bg-white p-3 ring-1 ring-amber-200 sm:flex-row sm:items-center"
                        >
                            <div className="flex min-w-0 flex-1 items-start gap-3">
                                <IconoTramite tramite={t} />
                                <div className="min-w-0 flex-1">
                                    <p className="leading-snug font-bold text-rio-profundo">{t.tipo}</p>
                                    <p className="text-xs text-slate-500">{detalleDocumento(t)}</p>
                                </div>
                            </div>
                            {/* En el celular, monto y código en una franja propia; en ancho, a la derecha. */}
                            <div className="flex items-end justify-between gap-3 border-t border-slate-100 pt-3 sm:block sm:border-0 sm:pt-0 sm:text-right">
                                {t.debe > 0 && (
                                    <p className="text-lg leading-tight font-extrabold text-rio-profundo tabular-nums">
                                        {bs(t.debe, moneda)}
                                    </p>
                                )}
                                {t.codigo_pago && (
                                    <p className="flex items-center gap-1 text-xs text-slate-500 sm:justify-end">
                                        Código
                                        <TextoCopiable texto={t.codigo_pago} className="text-slate-800" />
                                    </p>
                                )}
                            </div>
                            {t.puede_pagar_qr && t.codigo && (
                                <button
                                    type="button"
                                    onClick={() => setPagando(t)}
                                    className="flex w-full items-center justify-center gap-2 rounded-lg bg-rio-profundo px-3 py-2 text-sm font-bold text-white transition-colors hover:bg-rio sm:w-auto"
                                >
                                    <QrCode className="size-4" />
                                    Pagar con QR
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            </Tarjeta>
        );
    } else if (enRevision.length > 0) {
        tarjeta = (
            <Tarjeta
                tono="cielo"
                icono={<Hourglass className="size-6 motion-safe:animate-pulse" />}
                rotulo="Ya pagó"
                titulo="Estamos revisando su pago"
                texto="Recaudaciones lo está validando. Apenas lo apruebe, su papel aparece aquí como vigente. No hace falta que haga nada más."
            >
                <ListaTramites tramites={enRevision} />
            </Tarjeta>
        );
    } else if (porVencer.length > 0) {
        const p = porVencer[0];
        tarjeta = (
            <Tarjeta
                tono="dorado"
                icono={<CalendarClock className="size-6" />}
                rotulo="Para tener en cuenta"
                titulo={
                    p.dias_restantes === 0
                        ? `Su ${p.tipo} vence hoy`
                        : `Su ${p.tipo} vence en ${p.dias_restantes} ${p.dias_restantes === 1 ? 'día' : 'días'}`
                }
                texto="Para seguir trabajando sin cortes, renuévelo en ventanilla antes de que venza."
            />
        );
    } else if (vigentes.length > 0) {
        tarjeta = (
            <Tarjeta
                tono="verde"
                icono={<CheckCircle2 className="size-6" />}
                rotulo="Todo en orden"
                titulo={
                    vigentes.length === 1 ? 'Tiene 1 documento vigente' : `Tiene ${vigentes.length} documentos vigentes`
                }
                texto="No debe nada y no tiene trámites pendientes. Sus papeles están más abajo."
            />
        );
    } else {
        tarjeta = (
            <Tarjeta
                tono="cielo"
                icono={<ArrowDown className="size-6 motion-safe:animate-bounce" />}
                rotulo="Para empezar"
                titulo="Todavía no tiene documentos"
                texto="Elija abajo si es pescador o comercializador y vea qué papel le toca primero y qué tiene que llevar."
            >
                <a
                    href="#tramites"
                    className="inline-flex items-center gap-1.5 rounded-lg bg-rio-profundo px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-rio"
                >
                    Ver qué puedo tramitar
                    <ArrowDown className="size-4" />
                </a>
            </Tarjeta>
        );
    }

    return (
        <>
            {tarjeta}
            {pagando?.codigo && (
                <VentanaPagoQr
                    codigo={pagando.codigo}
                    titulo={pagando.tipo}
                    detalle={detalleDocumento(pagando)}
                    onCerrar={() => setPagando(null)}
                />
            )}
        </>
    );
}

/** Clases completas por tono: Tailwind solo incluye lo que ve escrito. */
const TONOS = {
    rojo: { caja: 'bg-rose-50 ring-rose-200', icono: 'bg-rose-600 text-white', rotulo: 'text-rose-700' },
    dorado: {
        caja: 'bg-amber-50 ring-amber-200',
        icono: 'bg-institucional-dorado text-rio-profundo',
        rotulo: 'text-amber-800',
    },
    cielo: { caja: 'bg-sky-50 ring-sky-200', icono: 'bg-sky-600 text-white', rotulo: 'text-sky-800' },
    verde: { caja: 'bg-emerald-50 ring-emerald-200', icono: 'bg-emerald-600 text-white', rotulo: 'text-emerald-800' },
} as const;

function Tarjeta({
    tono,
    icono,
    rotulo,
    titulo,
    texto,
    children,
}: {
    tono: keyof typeof TONOS;
    icono: ReactNode;
    rotulo: string;
    titulo: ReactNode;
    texto: string;
    children?: ReactNode;
}) {
    const t = TONOS[tono];

    return (
        <section
            aria-live="polite"
            className={cn(
                'rounded-2xl p-4 shadow-sm ring-1 motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-2 motion-safe:duration-500 sm:p-5',
                t.caja,
            )}
        >
            <div className="flex items-start gap-3.5">
                <span
                    className={cn('flex size-12 shrink-0 items-center justify-center rounded-2xl shadow-sm', t.icono)}
                >
                    {icono}
                </span>
                <div className="min-w-0 flex-1">
                    <p className={cn('text-xs font-bold tracking-wider uppercase', t.rotulo)}>{rotulo}</p>
                    <h2 className="text-xl leading-tight font-extrabold text-rio-profundo sm:text-2xl">{titulo}</h2>
                    <p className="mt-1 text-sm text-slate-700">{texto}</p>
                </div>
            </div>
            {children && <div className="mt-4">{children}</div>}
        </section>
    );
}

function IconoTramite({ tramite }: { tramite: PapelPortal }) {
    const { icono: Icono, tono } = ICONO_DOCUMENTO[tramite.clase];

    return (
        <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-xl', tono)}>
            <Icono className="size-5" />
        </span>
    );
}

/** Los trámites del caso, cada uno lleva a «En curso», donde está el detalle completo. */
function ListaTramites({ tramites }: { tramites: PapelPortal[] }) {
    return (
        <ul className="space-y-2">
            {tramites.map((t, i) => (
                <li key={`${t.clase}-${t.codigo ?? i}`}>
                    <Link
                        href={route('portal.en-curso')}
                        className="flex items-center gap-3 rounded-xl bg-white p-3 ring-1 ring-slate-200 transition-colors hover:bg-rio-espuma"
                    >
                        <IconoTramite tramite={t} />
                        <span className="min-w-0 flex-1">
                            <span className="block font-bold text-rio-profundo">{t.tipo}</span>
                            {t.siguiente_paso && (
                                <span className="block text-xs text-slate-600">{t.siguiente_paso}</span>
                            )}
                        </span>
                        <ChevronRight className="size-5 shrink-0 text-slate-400" />
                    </Link>
                </li>
            ))}
        </ul>
    );
}

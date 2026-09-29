import {
    ChevronRight,
    Eye,
    EyeOff,
    MapPin,
    Download,
    QrCode,
} from 'lucide-react';
import { useState, type InputHTMLAttributes, type PropsWithChildren, type ReactNode } from 'react';
import { useModalPago, type Pago } from '@/components/portal/modal-pago';
import { cn } from '@/lib/utils';

/**
 * Piezas chicas del portal. Colores claros escritos a mano, sin `dark:`: el
 * portal se ve igual con el teléfono en modo oscuro (ver layout-portal).
 */

const CHIP: Record<string, string> = {
    emerald: 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
    sky: 'bg-sky-100 text-sky-800 ring-sky-600/20',
    indigo: 'bg-indigo-100 text-indigo-800 ring-indigo-600/20',
    amber: 'bg-amber-100 text-amber-800 ring-amber-600/20',
    rose: 'bg-rose-100 text-rose-800 ring-rose-600/20',
    violet: 'bg-violet-100 text-violet-800 ring-violet-600/20',
    teal: 'bg-teal-100 text-teal-800 ring-teal-600/20',
    slate: 'bg-slate-100 text-slate-700 ring-slate-600/20',
};

/** El estado como lo dice el sistema; el color llega del enum de PHP. */
export function EstadoChip({ color, children }: PropsWithChildren<{ color: string }>) {
    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
                CHIP[color] ?? CHIP.slate,
            )}
        >
            {children}
        </span>
    );
}

/** El botón «Pagar con QR»: abre el modal global. Lo usan la tarjeta y la lista de «En curso». */
export function BotonPagarQr({ pago, className = '' }: { pago: Pago; className?: string }) {
    const abrirPago = useModalPago();

    // El monto no va en el botón: lo dicen el «Qué falta» y el modal.
    return (
        <button
            type="button"
            onClick={() => abrirPago(pago)}
            className={cn(
                'group flex w-full items-center gap-2 rounded-full bg-linear-to-r from-rio to-rio-profundo py-2 pr-3 pl-3.5 text-left text-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md active:translate-y-0',
                className,
            )}
        >
            <QrCode className="size-4 shrink-0" />
            <span className="min-w-0 flex-1 truncate text-sm leading-none font-bold">
                Pagar con QR
                <span className="ml-1.5 text-[11px] font-medium text-white/70 sm:hidden">desde su banco</span>
                <span className="ml-1.5 hidden text-[11px] font-medium text-white/70 sm:inline">
                    desde la app de su banco
                </span>
            </span>
            <ChevronRight className="size-4 shrink-0 text-white/70 transition-transform group-hover:translate-x-0.5" />
        </button>
    );
}

/** El texto de «Qué falta» con el monto («15,00 Bs») resaltado: es lo que más se busca. */
export function ConMontoResaltado({ texto }: { texto: string }) {
    return (
        <>
            {texto.split(/(\d{1,3}(?:\.\d{3})*,\d{2} Bs)/).map((parte, i) =>
                i % 2 === 1 ? (
                    <strong key={i} className="text-sm font-extrabold whitespace-nowrap text-rio-profundo">
                        {parte}
                    </strong>
                ) : (
                    parte
                ),
            )}
        </>
    );
}

/**
 * El carnet no se descarga (perdido, se repone en ventanilla): se retira en el
 * SEDAG. Discreto, en el lugar donde los otros papeles tienen «Descargar PDF».
 */
export function AvisoRetiroCarnet({ className = '' }: { className?: string }) {
    return (
        <span
            title="El carnet no se descarga. Si todavía no lo recogió, retírelo en ventanilla de la Unidad de Pesca del SEDAG con su cédula."
            className={cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600',
                className,
            )}
        >
            <MapPin className="size-3.5 text-rio" />
            Se retira en el SEDAG
        </span>
    );
}

/**
 * Descargar, solo con el ícono: el nombre va en `aria-label` y en `title` para
 * quien lo necesita. El mismo en «Mis papeles», el inicio y los recibos.
 */
export function BotonDescarga({ url, etiqueta }: { url: string; etiqueta: string }) {
    return (
        <a
            href={url}
            download
            aria-label={etiqueta}
            title={etiqueta}
            className="flex size-8 shrink-0 items-center justify-center rounded-full border border-rio/30 bg-white text-rio transition-colors hover:bg-rio hover:text-white"
        >
            <Download className="size-4" />
        </a>
    );
}

/** Un par rótulo/valor, en «Mis datos». */
export function Dato({ rotulo, children, ancho = false }: PropsWithChildren<{ rotulo: string; ancho?: boolean }>) {
    return (
        <div className={cn('min-w-0', ancho && 'col-span-2')}>
            <dt className="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">{rotulo}</dt>
            <dd className="font-medium break-words text-slate-800">{children || '—'}</dd>
        </div>
    );
}

/**
 * Un campo de formulario claro, con su error. Si es de contraseña lleva el ojo
 * para mostrarla: en el celular es fácil tipear mal y no darse cuenta.
 */
export function CampoPortal({
    etiqueta,
    error,
    ayuda,
    type,
    ...input
}: InputHTMLAttributes<HTMLInputElement> & { etiqueta: string; error?: string; ayuda?: ReactNode }) {
    const [visible, setVisible] = useState(false);
    const esClave = type === 'password';

    return (
        <label className="block">
            <span className="text-sm font-semibold text-slate-700">{etiqueta}</span>
            <span className="relative mt-1.5 block">
                <input
                    {...input}
                    type={esClave && visible ? 'text' : type}
                    aria-invalid={Boolean(error)}
                    className={cn(
                        'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-base text-slate-900 shadow-sm outline-none focus:ring-2',
                        esClave && 'pr-12',
                        error
                            ? 'border-rose-400 focus:ring-rose-200'
                            : 'border-slate-300 focus:border-rio focus:ring-rio-claro/40',
                    )}
                />
                {esClave && (
                    <button
                        type="button"
                        onClick={() => setVisible((v) => !v)}
                        aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                        aria-pressed={visible}
                        className="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-slate-500 hover:text-rio"
                    >
                        {visible ? <EyeOff className="size-5" /> : <Eye className="size-5" />}
                    </button>
                )}
            </span>
            {error ? (
                <span className="mt-1 block text-sm text-rose-700">{error}</span>
            ) : (
                ayuda && <span className="mt-1 block text-xs text-slate-500">{ayuda}</span>
            )}
        </label>
    );
}

/** Aviso de lista vacía. */
export function Vacio({ children }: PropsWithChildren) {
    return (
        <p className="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-6 text-center text-sm text-slate-500">
            {children}
        </p>
    );
}

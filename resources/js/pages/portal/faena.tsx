import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Fish, LoaderCircle, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import { CampoPortal } from '@/components/portal/piezas';
import LayoutPortal from '@/layouts/layout-portal';
import { bs } from '@/lib/utils';
import type { PedidoFaena } from '@/types/portal';

/** Los renglones del talonario «PERMISO POR FAENA», en el orden del papel. */
const RENGLONES = [
    { clave: 'embarcacion', etiqueta: 'La embarcación', ayuda: 'Nombre o tipo, como figura en el papel.' },
    { clave: 'propietario', etiqueta: 'De propiedad de', ayuda: 'Si la embarcación es suya, va su nombre.' },
    { clave: 'comandante_barco', etiqueta: 'Comandante de barco', ayuda: null },
    { clave: 'matricula_naval', etiqueta: 'Matrícula naval N°', ayuda: null },
    { clave: 'nro_kardex', etiqueta: 'N° de Kardex', ayuda: null },
    { clave: 'region_desde', etiqueta: 'Va a pescar desde', ayuda: 'Lugar o región donde empieza.' },
    { clave: 'region_hasta', etiqueta: 'Hasta', ayuda: 'Lugar o región donde termina.' },
] as const;

type Clave = (typeof RENGLONES)[number]['clave'];

/**
 * «Pedir permiso de faena» (19/11/2026): el pescador lo pide desde el portal, con
 * las mismas reglas que ventanilla (PedirFaenaService). Queda pendiente con su
 * código de pago y se aprueba solo al validarse el pago.
 */
export default function Faena({ puede, motivo, carnet, kilos, arancel, vigencia, propietario_sugerido }: PedidoFaena) {
    const form = useForm<Record<Clave, string>>({
        embarcacion: '',
        propietario: propietario_sugerido,
        comandante_barco: '',
        matricula_naval: '',
        nro_kardex: '',
        region_desde: '',
        region_hasta: '',
    });
    const errorGeneral = (form.errors as Record<string, string | undefined>).faena;

    const enviar = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('portal.faenas.store'));
    };

    return (
        <LayoutPortal titulo="Pedir permiso de faena" bajada="Uno por cada salida a pescar">
            <Head title="Pedir permiso de faena" />

            <Link href={route('portal.inicio')} className="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-rio">
                <ArrowLeft className="size-4" /> Volver
            </Link>

            {!puede ? (
                <div className="mx-auto max-w-xl rounded-3xl bg-white p-5 shadow-sm ring-1 ring-amber-200">
                    <p className="flex items-center gap-2 font-bold text-amber-800">
                        <AlertTriangle className="size-5" /> Hoy no puede pedirlo en línea
                    </p>
                    <p className="mt-2 text-slate-700">{motivo}</p>
                </div>
            ) : (
                <form onSubmit={enviar} className="mx-auto max-w-xl space-y-4">
                    <section className="rounded-3xl bg-rio-profundo p-5 text-white shadow-sm">
                        <p className="flex items-center gap-2 text-xs font-extrabold tracking-wide text-institucional-dorado uppercase">
                            <Fish className="size-4" /> Lo que va a pedir
                        </p>
                        <dl className="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt className="text-white/70">Kilos autorizados</dt>
                                <dd className="text-lg font-extrabold">{kilos === null ? '—' : `${kilos.toLocaleString('es-BO')} kg`}</dd>
                            </div>
                            <div>
                                <dt className="text-white/70">A pagar</dt>
                                <dd className="text-lg font-extrabold">{arancel ? bs(arancel.monto) : '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-white/70">Carnet de pescador</dt>
                                <dd className="font-semibold">N° {carnet?.numero ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-white/70">Vale</dt>
                                <dd className="font-semibold">{vigencia}</dd>
                            </div>
                        </dl>
                    </section>

                    <section className="space-y-3 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
                        <p className="font-bold text-rio-profundo">Datos del talonario</p>
                        {RENGLONES.map((r) => (
                            <CampoPortal
                                key={r.clave}
                                etiqueta={r.etiqueta}
                                ayuda={r.ayuda ?? undefined}
                                value={form.data[r.clave]}
                                onChange={(e) => form.setData(r.clave, e.target.value)}
                                error={form.errors[r.clave]}
                                required
                            />
                        ))}
                    </section>

                    {errorGeneral && (
                        <p className="rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-800 ring-1 ring-rose-200">{errorGeneral}</p>
                    )}

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="flex w-full items-center justify-center gap-2 rounded-xl bg-rio px-4 py-3 text-base font-bold text-white shadow-lg shadow-rio/25 transition-colors hover:bg-rio-profundo disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none"
                    >
                        {form.processing ? <LoaderCircle className="size-5 animate-spin" /> : <Send className="size-5" />}
                        {form.processing ? 'Enviando…' : 'Pedir permiso de faena'}
                    </button>
                    <p className="text-center text-sm text-slate-500">
                        Queda pendiente con su código de pago. Al validarse el pago en Recaudaciones, queda aprobado solo.
                    </p>
                </form>
            )}
        </LayoutPortal>
    );
}

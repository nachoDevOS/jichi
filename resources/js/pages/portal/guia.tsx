import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, LoaderCircle, Plus, Search, Send, Trash2, Truck } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { CampoPortal } from '@/components/portal/piezas';
import LayoutPortal from '@/layouts/layout-portal';
import { bs, cn } from '@/lib/utils';
import type { AutorizacionVendedor, OpcionPortal, PedidoGuia } from '@/types/portal';

interface Renglon {
    tarifa_sireb: string;
    condicion: string;
    cantidad_kg: string;
}

const RUTA = [
    { clave: 'origen', etiqueta: 'Lugar' },
    { clave: 'departamento', etiqueta: 'Departamento' },
    { clave: 'provincia', etiqueta: 'Provincia' },
    { clave: 'distrito', etiqueta: 'Distrito o cuenca' },
] as const;

/**
 * «Pedir guía de transporte» (19/11/2026): el comercializador la pide desde el portal,
 * con las mismas reglas que ventanilla (PedirGuiaService). Los kilos se descuentan
 * del pescador al registrar; queda pendiente con su código de pago.
 */
export default function Guia(props: PedidoGuia) {
    const { puede, motivo, carnet, productos, condiciones, medios, tipos_transporte: tipos, vigencia } = props;
    const form = useForm({
        origen: '',
        origen_departamento: 'Beni',
        origen_provincia: '',
        origen_distrito: '',
        destino: '',
        destino_departamento: '',
        destino_provincia: '',
        destino_distrito: '',
        medio_transporte: '',
        tipo_transporte: '',
        transporte_nombre: '',
        transporte_placa: '',
        transporte_capacidad_kg: '',
        autorizacion_codigo: '',
        observaciones: '',
        detalles: [{ tarifa_sireb: '', condicion: 'fresco_entero', cantidad_kg: '' }] as Renglon[],
    });
    const errores = form.errors as Record<string, string | undefined>;
    const [vendedor, setVendedor] = useState<AutorizacionVendedor | null>(null);
    const [buscando, setBuscando] = useState(false);
    const [errorBusqueda, setErrorBusqueda] = useState<string | null>(null);

    const precio = (id: string) => productos.find((p) => p.id === id)?.precio_kg ?? 0;
    const kilos = form.data.detalles.reduce((a, d) => a + (Number.parseFloat(d.cantidad_kg.replace(',', '.')) || 0), 0);
    const monto = form.data.detalles.reduce(
        (a, d) => a + precio(d.tarifa_sireb) * (Number.parseFloat(d.cantidad_kg.replace(',', '.')) || 0),
        0,
    );
    const excede = vendedor !== null && kilos > vendedor.kilos_disponibles;

    const buscar = () => {
        const codigo = form.data.autorizacion_codigo.trim();
        if (codigo.length < 16) {
            setErrorBusqueda('El código tiene 16 caracteres: está debajo del QR del papel del pescador.');
            return;
        }
        setBuscando(true);
        setErrorBusqueda(null);
        fetch(route('portal.guias.autorizacion', { codigo }), { headers: { Accept: 'application/json' } })
            .then(async (r) => {
                const cuerpo = await r.json();
                if (!r.ok) throw new Error(cuerpo.message ?? 'No pudimos buscar la autorización.');
                setVendedor(cuerpo.autorizacion);
            })
            .catch((e: Error) => {
                setVendedor(null);
                setErrorBusqueda(e.message);
            })
            .finally(() => setBuscando(false));
    };

    const cambiarRenglon = (i: number, campo: keyof Renglon, valor: string) =>
        form.setData(
            'detalles',
            form.data.detalles.map((d, n) => (n === i ? { ...d, [campo]: valor } : d)),
        );

    const enviar = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('portal.guias.store'));
    };

    return (
        <LayoutPortal titulo="Pedir guía de transporte" bajada="Una por cada traslado">
            <Head title="Pedir guía de transporte" />

            <Link href={route('portal.inicio')} className="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-rio">
                <ArrowLeft className="size-4" /> Volver
            </Link>

            {!puede ? (
                <div className="mx-auto max-w-xl rounded-3xl bg-white p-5 shadow-sm ring-1 ring-amber-200">
                    <p className="flex items-center gap-2 font-bold text-amber-800">
                        <AlertTriangle className="size-5" /> Hoy no puede pedirla en línea
                    </p>
                    <p className="mt-2 text-slate-700">{motivo}</p>
                </div>
            ) : (
                <form onSubmit={enviar} className="mx-auto max-w-2xl space-y-4">
                    <section className="rounded-3xl bg-rio-profundo p-5 text-white shadow-sm">
                        <p className="flex items-center gap-2 text-xs font-extrabold tracking-wide text-institucional-dorado uppercase">
                            <Truck className="size-4" /> Lo que va a pedir
                        </p>
                        <dl className="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                            <Resumen rotulo="Kilos" valor={`${kilos.toLocaleString('es-BO')} kg`} />
                            <Resumen rotulo="A pagar" valor={bs(monto)} />
                            <Resumen rotulo="Carnet" valor={`N° ${carnet?.numero ?? '—'}`} />
                            <Resumen rotulo="Vale" valor={vigencia} />
                        </dl>
                    </section>

                    <Bloque titulo="1. ¿A qué pescador le compró?">
                        <div className="flex gap-2">
                            <div className="flex-1">
                                <CampoPortal
                                    etiqueta="Código de su Autorización de Pesca"
                                    ayuda="Los 16 caracteres debajo del QR del papel del pescador."
                                    value={form.data.autorizacion_codigo}
                                    onChange={(e) => {
                                        form.setData('autorizacion_codigo', e.target.value.toUpperCase());
                                        setVendedor(null);
                                    }}
                                    error={errores.autorizacion_codigo ?? errorBusqueda ?? undefined}
                                />
                            </div>
                            <button
                                type="button"
                                onClick={buscar}
                                disabled={buscando}
                                className="mt-7 flex h-11 items-center gap-1 rounded-xl bg-rio px-4 text-sm font-bold text-white disabled:bg-slate-300"
                            >
                                {buscando ? <LoaderCircle className="size-4 animate-spin" /> : <Search className="size-4" />}
                                Buscar
                            </button>
                        </div>
                        {vendedor && (
                            <div
                                className={cn(
                                    'rounded-xl p-3 text-sm ring-1',
                                    vendedor.vendible ? 'bg-emerald-50 ring-emerald-200' : 'bg-amber-50 ring-amber-200',
                                )}
                            >
                                <p className="font-bold text-slate-900">
                                    {vendedor.pescador} · Autorización N° {vendedor.numero}
                                </p>
                                <p className="text-slate-700">
                                    {vendedor.vendible
                                        ? `Le quedan ${vendedor.kilos_disponibles.toLocaleString('es-BO')} kg disponibles.`
                                        : vendedor.motivo}
                                </p>
                            </div>
                        )}
                    </Bloque>

                    <Bloque titulo="2. ¿Qué lleva?">
                        {form.data.detalles.map((d, i) => (
                            <div key={i} className="grid gap-2 rounded-xl p-3 ring-1 ring-slate-200 sm:grid-cols-[1fr_1fr_7rem_auto]">
                                <Selector
                                    etiqueta="Producto"
                                    valor={d.tarifa_sireb}
                                    opciones={productos.map((p) => ({ value: p.id, label: `${p.nombre} · ${bs(p.precio_kg)}/kg` }))}
                                    onCambio={(v) => cambiarRenglon(i, 'tarifa_sireb', v)}
                                    error={errores[`detalles.${i}.tarifa_sireb`]}
                                />
                                <Selector
                                    etiqueta="Condición"
                                    valor={d.condicion}
                                    opciones={condiciones}
                                    onCambio={(v) => cambiarRenglon(i, 'condicion', v)}
                                    error={errores[`detalles.${i}.condicion`]}
                                />
                                <CampoPortal
                                    etiqueta="Kilos"
                                    inputMode="decimal"
                                    value={d.cantidad_kg}
                                    onChange={(e) => cambiarRenglon(i, 'cantidad_kg', e.target.value)}
                                    error={errores[`detalles.${i}.cantidad_kg`]}
                                />
                                <button
                                    type="button"
                                    aria-label="Quitar este producto"
                                    disabled={form.data.detalles.length === 1}
                                    onClick={() => form.setData('detalles', form.data.detalles.filter((_, n) => n !== i))}
                                    className="mt-7 flex h-11 items-center justify-center rounded-xl px-3 text-slate-500 hover:text-rose-700 disabled:opacity-30"
                                >
                                    <Trash2 className="size-5" />
                                </button>
                            </div>
                        ))}
                        {errores.detalles && <p className="text-sm font-semibold text-rose-700">{errores.detalles}</p>}
                        {form.data.detalles.length < 20 && (
                            <button
                                type="button"
                                onClick={() =>
                                    form.setData('detalles', [...form.data.detalles, { tarifa_sireb: '', condicion: 'fresco_entero', cantidad_kg: '' }])
                                }
                                className="flex items-center gap-1 text-sm font-bold text-rio"
                            >
                                <Plus className="size-4" /> Agregar otro producto
                            </button>
                        )}
                        {excede && (
                            <p className="text-sm font-semibold text-amber-800">
                                Son {kilos.toLocaleString('es-BO')} kg y al pescador le quedan{' '}
                                {vendedor?.kilos_disponibles.toLocaleString('es-BO')} kg.
                            </p>
                        )}
                    </Bloque>

                    <Bloque titulo="3. ¿De dónde a dónde?">
                        {(['origen', 'destino'] as const).map((lado) => (
                            <fieldset key={lado} className="grid gap-2 sm:grid-cols-2">
                                <legend className="mb-1 text-xs font-extrabold tracking-wide text-slate-500 uppercase">
                                    {lado === 'origen' ? 'Sale de' : 'Va a'}
                                </legend>
                                {RUTA.map((c) => {
                                    const clave = (c.clave === 'origen' ? lado : `${lado}_${c.clave}`) as keyof typeof form.data;
                                    return (
                                        <CampoPortal
                                            key={clave}
                                            etiqueta={c.etiqueta}
                                            value={form.data[clave] as string}
                                            onChange={(e) => form.setData(clave, e.target.value)}
                                            error={errores[clave]}
                                        />
                                    );
                                })}
                            </fieldset>
                        ))}
                    </Bloque>

                    <Bloque titulo="4. ¿En qué lo lleva?">
                        <div className="grid gap-2 sm:grid-cols-2">
                            <Selector
                                etiqueta="Por dónde viaja"
                                valor={form.data.medio_transporte}
                                opciones={medios}
                                onCambio={(v) => form.setData('medio_transporte', v)}
                                error={errores.medio_transporte}
                            />
                            <Selector
                                etiqueta="En qué vehículo"
                                valor={form.data.tipo_transporte}
                                opciones={tipos}
                                onCambio={(v) => form.setData('tipo_transporte', v)}
                                error={errores.tipo_transporte}
                            />
                            <CampoPortal
                                etiqueta="Nombre o tipo"
                                value={form.data.transporte_nombre}
                                onChange={(e) => form.setData('transporte_nombre', e.target.value)}
                                error={errores.transporte_nombre}
                            />
                            <CampoPortal
                                etiqueta="Placa o matrícula"
                                value={form.data.transporte_placa}
                                onChange={(e) => form.setData('transporte_placa', e.target.value.toUpperCase())}
                                error={errores.transporte_placa}
                            />
                            <CampoPortal
                                etiqueta="Capacidad máxima (kg)"
                                inputMode="decimal"
                                value={form.data.transporte_capacidad_kg}
                                onChange={(e) => form.setData('transporte_capacidad_kg', e.target.value)}
                                error={errores.transporte_capacidad_kg}
                            />
                        </div>
                        <CampoPortal
                            etiqueta="Observaciones (opcional)"
                            ayuda="Se imprimen en el recuadro del papel."
                            value={form.data.observaciones}
                            onChange={(e) => form.setData('observaciones', e.target.value)}
                            error={errores.observaciones}
                        />
                    </Bloque>

                    {errores.guia && (
                        <p className="rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-800 ring-1 ring-rose-200">{errores.guia}</p>
                    )}

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="flex w-full items-center justify-center gap-2 rounded-xl bg-rio px-4 py-3 text-base font-bold text-white shadow-lg shadow-rio/25 transition-colors hover:bg-rio-profundo disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none"
                    >
                        {form.processing ? <LoaderCircle className="size-5 animate-spin" /> : <Send className="size-5" />}
                        {form.processing ? 'Enviando…' : 'Pedir guía de transporte'}
                    </button>
                    <p className="text-center text-sm text-slate-500">
                        Los kilos se descuentan del pescador al pedirla. Queda pendiente con su código de pago y, al validarse el pago,
                        queda aprobada sola.
                    </p>
                </form>
            )}
        </LayoutPortal>
    );
}

function Resumen({ rotulo, valor }: { rotulo: string; valor: string }) {
    return (
        <div>
            <dt className="text-white/70">{rotulo}</dt>
            <dd className="font-extrabold">{valor}</dd>
        </div>
    );
}

function Bloque({ titulo, children }: { titulo: string; children: ReactNode }) {
    return (
        <section className="space-y-3 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
            <p className="font-bold text-rio-profundo">{titulo}</p>
            {children}
        </section>
    );
}

function Selector({
    etiqueta,
    valor,
    opciones,
    onCambio,
    error,
}: {
    etiqueta: string;
    valor: string;
    opciones: OpcionPortal[];
    onCambio: (valor: string) => void;
    error?: string;
}) {
    return (
        <label className="block">
            <span className="text-sm font-semibold text-slate-700">{etiqueta}</span>
            <select
                value={valor}
                onChange={(e) => onCambio(e.target.value)}
                aria-invalid={Boolean(error)}
                className={cn(
                    'mt-1.5 block h-11 w-full rounded-xl border bg-white px-3 text-base text-slate-900 shadow-sm outline-none focus:ring-2',
                    error ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-rio focus:ring-rio-claro/40',
                )}
            >
                <option value="">Elija…</option>
                {opciones.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </select>
            {error && <span className="mt-1 block text-sm text-rose-700">{error}</span>}
        </label>
    );
}

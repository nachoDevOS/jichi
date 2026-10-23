import { Head, useForm, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeft,
    ArrowRight,
    CalendarClock,
    Check,
    Fish,
    IdCard,
    Info,
    MapPin,
    Package,
    ShieldAlert,
    TriangleAlert,
    Truck,
    Wallet,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Aviso, BandaFormulario, DatosDelCarnet, kg } from '@/components/panel/comunes/ficha';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { BloqueProcedencia } from '@/components/panel/guias/bloque-procedencia';
import { CamposGuia, rutaCompleta } from '@/components/panel/guias/campos-guia';
import { filaVacia, TablaDetalle } from '@/components/panel/guias/tabla-detalle';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { AutorizacionVendible, BeneficiarioSugerido, CarnetVigenteSugerido } from '@/types/beneficiarios';
import type { CatalogosGuia, FormularioGuia } from '@/types/guias';

type Paso = { titulo: string; corto: string; ayuda: string; icono: LucideIcon; hecho: boolean };

/** A qué paso pertenece cada error del servidor: se salta ahí para que no quede escondido. */
function pasoDelError(campo: string): number {
    if (campo === 'carnet_id') return 0;
    if (campo === 'aprovechamiento_id') return 1;
    if (campo.startsWith('detalles')) return 2;

    return 3;
}

/**
 *  Registrar una guía de transporte, por pasos: queda pendiente hasta que se pague en SIREB
 */
export default function CrearGuia({
    beneficiario,
    carnetElegido,
    medios,
    tiposTransporte,
    productos,
    sirebDisponible,
    servicioConfigurado,
    condiciones,
    vigencia,
    descuentoPiscicultura,
}: CatalogosGuia & {
    beneficiario: (BeneficiarioSugerido & { carnets_vigentes: CarnetVigenteSugerido[] }) | null;
    /** `?carnet=`: llega desde la ficha del carnet con el carnet ya elegido. */
    carnetElegido: number | null;
}) {
    const { institucion } = usePage<PageProps>().props;
    const [persona, setPersona] = useState<BeneficiarioSugerido | null>(beneficiario);
    // Solo si está entre los vigentes y puede emitir guías: si no, se elige a mano.
    const inicial = beneficiario?.carnets_vigentes.find((c) => c.id === carnetElegido && c.puede_emitir_guias) ?? null;
    const [carnet, setCarnet] = useState<CarnetVigenteSugerido | null>(inicial);
    // A quién se le compró el pescado: su autorización descuenta los kilos.
    const [pescador, setPescador] = useState<BeneficiarioSugerido | null>(null);
    const [autorizacion, setAutorizacion] = useState<AutorizacionVendible | null>(null);
    const [paso, setPaso] = useState(inicial ? 1 : 0);

    const form = useForm<FormularioGuia & { carnet_id: number | null }>({
        carnet_id: inicial?.id ?? null,
        origen: '',
        origen_departamento: '',
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
        es_piscicultura: false,
        observaciones: '',
        aprovechamiento_id: null,
        detalles: [filaVacia()],
    });

    // Se cobra el total del cuadro D: kilos × precio del catálogo. El servidor lo recalcula al guardar.
    const importe = form.data.detalles.reduce(
        (suma, f) =>
            suma +
            Number(f.cantidad_kg || 0) * (productos.find((p) => String(p.id) === f.tarifa_sireb)?.precio_kg ?? 0),
        0,
    );
    const monto = form.data.es_piscicultura ? importe * (1 - descuentoPiscicultura) : importe;
    const kilos = form.data.detalles.reduce((suma, f) => suma + Number(f.cantidad_kg || 0), 0);
    const renglones = form.data.detalles.filter(
        (f) => f.tarifa_sireb !== '' && f.condicion !== '' && Number(f.cantidad_kg || 0) > 0,
    );
    // De referencia: el servidor vuelve a medir con la autorización bloqueada.
    const quedan = autorizacion !== null ? Math.round((autorizacion.kilos_disponibles - kilos) * 100) / 100 : null;
    const excede = quedan !== null && quedan < 0;
    const conRuta = rutaCompleta(form.data);

    const pasos: Paso[] = [
        {
            titulo: 'Comercializador',
            corto: 'Comercializador',
            ayuda: 'Quién traslada: tiene que tener carnet de comercializador vigente.',
            icono: IdCard,
            hecho: carnet !== null,
        },
        {
            titulo: 'Pescado de',
            corto: 'Pescado de',
            ayuda: 'A qué pescador le compró: sus kilos se descuentan de esa autorización al registrar.',
            icono: Fish,
            hecho: form.data.es_piscicultura || autorizacion !== null,
        },
        {
            titulo: 'Productos',
            corto: 'Productos',
            ayuda: 'El cuadro D: qué lleva y cuántos kilos. Se cobra por kilo según el producto.',
            icono: Package,
            hecho: renglones.length > 0 && !excede,
        },
        {
            titulo: 'Ruta y transporte',
            corto: 'Ruta',
            ayuda: 'De dónde a dónde y en qué. Todos los datos son obligatorios menos las observaciones.',
            icono: MapPin,
            hecho: conRuta,
        },
    ];
    const completo = pasos.every((p) => p.hecho);
    const actual = pasos[paso];

    function elegirPersona(elegida: BeneficiarioSugerido | null) {
        setPersona(elegida);
        setCarnet(null);
        form.setData('carnet_id', null);
        form.clearErrors('carnet_id');
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.post(route('guias.store'), {
            onError: (errores) => {
                const primero = Object.keys(errores)[0];
                if (primero) setPaso(pasoDelError(primero));
            },
        });
    }

    return (
        <LayoutPanel titulo="Registrar guía">
            <Head title="Registrar guía" />

            <BandaFormulario
                icono={Truck}
                titulo="Guía única de transporte"
                bajada={`Un traslado; vale ${vigencia}. Queda pendiente hasta que se pague.`}
                pasos={pasos.map((p) => ({ texto: p.corto, hecho: p.hecho }))}
            />

            <form onSubmit={enviar} className="mt-6 grid gap-6 lg:grid-cols-3">
                <Card className="min-w-0 overflow-hidden lg:col-span-2">
                    {/* Los pasos: uno a la vez para que la página no sea larga. Se puede saltar a cualquiera. */}
                    <nav
                        aria-label="Pasos de la guía"
                        className="grid grid-cols-2 border-b border-border sm:grid-cols-4"
                    >
                        {pasos.map((p, i) => (
                            <button
                                key={p.titulo}
                                type="button"
                                onClick={() => setPaso(i)}
                                aria-current={i === paso ? 'step' : undefined}
                                className={cn(
                                    'flex items-center gap-2 border-b-2 px-3 py-3 text-left text-sm transition-colors',
                                    i === paso
                                        ? 'border-primary bg-primary/5 font-semibold text-foreground'
                                        : 'border-transparent text-muted-foreground hover:bg-secondary/60 hover:text-foreground',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                        p.hecho
                                            ? 'bg-primary text-primary-foreground'
                                            : i === paso
                                              ? 'bg-primary/15 text-primary'
                                              : 'bg-secondary',
                                    )}
                                >
                                    {p.hecho ? <Check className="size-3.5" /> : i + 1}
                                </span>
                                <span className="truncate">{p.corto}</span>
                            </button>
                        ))}
                    </nav>

                    <CardContent className="space-y-4 p-5 sm:p-6">
                        <div className="flex items-start gap-3">
                            <actual.icono className="mt-0.5 size-5 shrink-0 text-primary" />
                            <div>
                                <p className="font-semibold">
                                    {actual.titulo}
                                    <span className="ml-0.5 text-destructive" aria-hidden>
                                        *
                                    </span>
                                </p>
                                <p className="text-sm text-muted-foreground">{actual.ayuda}</p>
                            </div>
                        </div>

                        {paso === 0 && (
                            <div className="space-y-4">
                                <BuscadorBeneficiario seleccionado={persona} onSeleccionar={elegirPersona} />
                                {persona && (
                                    <ListaDeCarnets
                                        carnets={persona.carnets_vigentes}
                                        elegido={carnet}
                                        onElegir={(c) => {
                                            setCarnet(c);
                                            form.setData('carnet_id', c.id);
                                            form.clearErrors('carnet_id');
                                        }}
                                    />
                                )}
                                {form.errors.carnet_id && (
                                    <p className="text-sm text-destructive">{form.errors.carnet_id}</p>
                                )}
                            </div>
                        )}

                        {paso === 1 &&
                            (form.data.es_piscicultura ? (
                                <Aviso tono="info" icono={Info}>
                                    Producto de piscicultura: no lleva pescador ni descuenta kilos.
                                </Aviso>
                            ) : (
                                <BloqueProcedencia
                                    pescador={pescador}
                                    autorizacionId={form.data.aprovechamiento_id}
                                    error={form.errors.aprovechamiento_id}
                                    onPescador={setPescador}
                                    onAutorizacion={(a) => {
                                        setAutorizacion(a);
                                        form.setData('aprovechamiento_id', a?.id ?? null);
                                        form.clearErrors('aprovechamiento_id');
                                    }}
                                />
                            ))}

                        {paso === 2 && (
                            <div className="space-y-3">
                                <TablaDetalle
                                    filas={form.data.detalles}
                                    productos={productos}
                                    sirebDisponible={sirebDisponible}
                                    servicioConfigurado={servicioConfigurado}
                                    condiciones={condiciones}
                                    errores={form.errors}
                                    onCambiar={(filas) => form.setData('detalles', filas)}
                                />
                                {excede && pescador && autorizacion && (
                                    <Aviso tono="peligro" icono={TriangleAlert}>
                                        Se pasa por {kg(Math.abs(quedan!))}: {pescador.nombreCompleto} tiene{' '}
                                        {kg(autorizacion.kilos_disponibles)} disponibles.
                                    </Aviso>
                                )}
                            </div>
                        )}

                        {paso === 3 && (
                            <CamposGuia
                                datos={form.data}
                                errores={form.errors}
                                medios={medios}
                                tiposTransporte={tiposTransporte}
                                descuentoPiscicultura={descuentoPiscicultura}
                                onCambio={(campo, valor) => form.setData((datos) => ({ ...datos, [campo]: valor }))}
                            />
                        )}

                        {/* Navegación: type="button" y keys propias, o React reusa el botón y envía el formulario. */}
                        <div className="flex items-center justify-between gap-3 border-t border-border pt-4">
                            <Button
                                key="anterior"
                                type="button"
                                variant="outline"
                                disabled={paso === 0}
                                onClick={() => setPaso(paso - 1)}
                            >
                                <ArrowLeft className="size-4" />
                                Anterior
                            </Button>
                            <span className="text-xs text-muted-foreground">
                                Paso {paso + 1} de {pasos.length}
                            </span>
                            {paso < pasos.length - 1 ? (
                                <Button
                                    key="siguiente"
                                    type="button"
                                    disabled={!actual.hecho}
                                    onClick={() => setPaso(paso + 1)}
                                >
                                    Siguiente
                                    <ArrowRight className="size-4" />
                                </Button>
                            ) : (
                                <span key="fin" className="text-xs font-medium text-primary">
                                    {completo ? 'Listo para registrar →' : 'Complete los pasos marcados'}
                                </span>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* El resumen acompaña en todos los pasos: es lo que se confirma antes de registrar. */}
                <Card className="h-fit overflow-hidden lg:sticky lg:top-40">
                    <CardHeader className="border-b border-border">
                        <CardTitle>Lo que se va a registrar</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4 pt-5 text-sm">
                        <div className="rounded-lg bg-secondary/60 px-4 py-3">
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                A cobrar
                            </p>
                            <p
                                className={cn(
                                    'text-2xl font-semibold tabular-nums',
                                    importe > 0 ? 'text-primary' : 'text-muted-foreground',
                                )}
                            >
                                {bs(monto, institucion.moneda)}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {kg(kilos)} en {renglones.length} {renglones.length === 1 ? 'producto' : 'productos'}
                            </p>
                        </div>

                        <Renglon
                            icono={IdCard}
                            etiqueta="Comercializador"
                            valor={carnet ? (persona?.nombreCompleto ?? null) : null}
                        />
                        <Renglon
                            icono={Fish}
                            etiqueta="Pescado de"
                            valor={pescador && autorizacion ? pescador.nombreCompleto : null}
                        />
                        <Renglon
                            icono={MapPin}
                            etiqueta="Ruta"
                            valor={
                                form.data.origen.trim() && form.data.destino.trim()
                                    ? `${form.data.origen} → ${form.data.destino}`
                                    : null
                            }
                        />
                        <Renglon icono={CalendarClock} etiqueta="Vale" valor={vigencia} />

                        {/* El saldo del pescador antes y después: lo que esta guía le descuenta. */}
                        {autorizacion && (
                            <div
                                className={cn(
                                    'rounded-lg border p-3',
                                    excede ? 'border-destructive/40 bg-destructive/5' : 'border-border',
                                )}
                            >
                                <p className="text-xs text-muted-foreground">Autorización N° {autorizacion.numero}</p>
                                <p className="tabular-nums">
                                    Le quedan {kg(autorizacion.kilos_disponibles)} →{' '}
                                    <strong className={excede ? 'text-destructive' : 'text-primary'}>
                                        {kg(quedan ?? 0)}
                                    </strong>
                                </p>
                            </div>
                        )}

                        <div className="flex items-start gap-2 rounded-lg bg-sky-50 p-3 text-xs text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">
                            <Wallet className="mt-0.5 size-4 shrink-0" />
                            Queda pendiente: se paga en Recaudaciones y, cuando lo validan, ampara el traslado.
                        </div>

                        <Button type="submit" disabled={form.processing || !completo} className="w-full">
                            Registrar guía
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}

/** Los carnets vigentes, con el que no sirve deshabilitado. */
function ListaDeCarnets({
    carnets,
    elegido,
    onElegir,
}: {
    carnets: CarnetVigenteSugerido[];
    elegido: CarnetVigenteSugerido | null;
    onElegir: (c: CarnetVigenteSugerido) => void;
}) {
    if (carnets.length === 0) {
        return (
            <Aviso tono="aviso" icono={ShieldAlert} titulo="Sin carnet vigente">
                Hay que emitirle uno de comercializador antes: la guía cuelga de él.
            </Aviso>
        );
    }

    return (
        <div role="radiogroup" aria-label="Carnet" className="grid gap-2 sm:grid-cols-2">
            {carnets.map((c) => {
                const activo = elegido?.id === c.id;

                return (
                    <button
                        key={c.id}
                        type="button"
                        role="radio"
                        aria-checked={activo}
                        disabled={!c.puede_emitir_guias}
                        onClick={() => onElegir(c)}
                        className={cn(
                            'relative rounded-lg border px-3 py-2 text-left transition-all',
                            activo
                                ? 'border-primary bg-primary/5 ring-2 ring-primary/30'
                                : 'border-border hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-md',
                            !c.puede_emitir_guias &&
                                'cursor-not-allowed opacity-50 hover:translate-y-0 hover:shadow-none',
                        )}
                    >
                        {activo && (
                            <Check className="absolute top-2 right-2 size-3.5 rounded-full bg-primary p-0.5 text-primary-foreground" />
                        )}
                        <DatosDelCarnet carnet={c}>
                            {c.puede_emitir_guias ? (
                                <span className="font-medium text-primary">Habilitado para guías</span>
                            ) : (
                                <span className="text-muted-foreground">No emite guías</span>
                            )}
                        </DatosDelCarnet>
                    </button>
                );
            })}
        </div>
    );
}

function Renglon({ icono: Icono, etiqueta, valor }: { icono: LucideIcon; etiqueta: string; valor: string | null }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="flex shrink-0 items-center gap-2 text-muted-foreground">
                <Icono className="size-4" />
                {etiqueta}
            </span>
            <span className={cn('truncate text-right', valor ? 'font-medium' : 'text-muted-foreground')}>
                {valor ?? '—'}
            </span>
        </div>
    );
}

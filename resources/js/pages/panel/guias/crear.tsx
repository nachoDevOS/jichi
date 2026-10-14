import { Head, useForm, usePage } from '@inertiajs/react';
import { Info, ShieldAlert, TriangleAlert, Truck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { BloqueProcedencia } from '@/components/panel/guias/bloque-procedencia';
import { CamposGuia } from '@/components/panel/guias/campos-guia';
import { filaVacia, TablaDetalle } from '@/components/panel/guias/tabla-detalle';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { AutorizacionVendible, BeneficiarioSugerido, CarnetVigenteSugerido } from '@/types/beneficiarios';
import type { CatalogosGuia, FormularioGuia } from '@/types/guias';

/**
 *  Registrar una guía de movimiento — paso 4, rama comercializador
 *
 * Nace pendiente: acá se arma el papel y nada más. Se paga en SIREB, y recién
 * aprobada la guía ampara el traslado.
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
    const inicial =
        beneficiario?.carnets_vigentes.find((c) => c.id === carnetElegido && c.puede_emitir_guias) ?? null;
    const [carnet, setCarnet] = useState<CarnetVigenteSugerido | null>(inicial);
    // A quién se le compró el pescado: su autorización descuenta los kilos.
    const [pescador, setPescador] = useState<BeneficiarioSugerido | null>(null);
    const [autorizacion, setAutorizacion] = useState<AutorizacionVendible | null>(null);

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

    function elegirPersona(elegida: BeneficiarioSugerido | null) {
        setPersona(elegida);
        setCarnet(null);
        form.setData('carnet_id', null);
        form.clearErrors('carnet_id');
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.post(route('guias.store'));
    }

    // Se cobra el total del cuadro D: kilos × precio del catálogo. El servidor lo recalcula al guardar.
    const importe = form.data.detalles.reduce(
        (suma, f) =>
            suma +
            Number(f.cantidad_kg || 0) * (productos.find((p) => String(p.id) === f.tarifa_sireb)?.precio_kg ?? 0),
        0,
    );
    const monto = form.data.es_piscicultura ? importe * (1 - descuentoPiscicultura) : importe;
    const kilos = form.data.detalles.reduce((suma, f) => suma + Number(f.cantidad_kg || 0), 0);
    // De referencia: el servidor vuelve a medir con la autorización bloqueada.
    const quedan = autorizacion !== null ? Math.round((autorizacion.kilos_disponibles - kilos) * 100) / 100 : null;
    const excede = quedan !== null && quedan < 0;

    const completo =
        carnet !== null &&
        (form.data.es_piscicultura || (autorizacion !== null && !excede)) &&
        form.data.origen.trim() !== '' &&
        form.data.destino.trim() !== '' &&
        form.data.detalles.some(
            (f) => f.tarifa_sireb !== '' && f.condicion !== '' && Number(f.cantidad_kg || 0) > 0,
        );

    return (
        <LayoutPanel
            titulo="Registrar guía"
            descripcion={`Ampara UN traslado. Vale ${vigencia}.`}
        >
            <Head title="Registrar guía" />

            <form onSubmit={enviar} className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>A. Interesado</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-5">
                            <Campo etiqueta="Comercializador" obligatorio error={form.errors.carnet_id}>
                                <BuscadorBeneficiario
                                    seleccionado={persona}
                                    onSeleccionar={elegirPersona}
                                    ayuda="Tiene que tener carnet de comercializador vigente."
                                />
                            </Campo>

                            {persona && (
                                <Campo etiqueta="Carnet" obligatorio>
                                    <ListaDeCarnets
                                        carnets={persona.carnets_vigentes}
                                        elegido={carnet}
                                        onElegir={(c) => {
                                            setCarnet(c);
                                            form.setData('carnet_id', c.id);
                                            form.clearErrors('carnet_id');
                                        }}
                                    />
                                </Campo>
                            )}

                        </CardContent>
                    </Card>

                    {carnet && (
                        <>
                            {!form.data.es_piscicultura && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Procedencia del pescado</CardTitle>
                                    </CardHeader>

                                    <CardContent>
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
                                    </CardContent>
                                </Card>
                            )}

                            <Card>
                                <CardHeader>
                                    <CardTitle>Ubicación y transporte</CardTitle>
                                </CardHeader>

                                <CardContent>
                                    <CamposGuia
                                        datos={form.data}
                                        errores={form.errors}
                                        medios={medios}
                                        tiposTransporte={tiposTransporte}
                                        descuentoPiscicultura={descuentoPiscicultura}
                                        onCambio={(campo, valor) =>
                                            form.setData((datos) => ({ ...datos, [campo]: valor }))
                                        }
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>D. Productos hidrobiológicos</CardTitle>
                                </CardHeader>

                                <CardContent>
                                    <TablaDetalle
                                        filas={form.data.detalles}
                                        productos={productos}
                                        sirebDisponible={sirebDisponible}
                                        servicioConfigurado={servicioConfigurado}
                                        condiciones={condiciones}
                                        errores={form.errors}
                                        onCambiar={(filas) => form.setData('detalles', filas)}
                                    />
                                </CardContent>
                            </Card>
                        </>
                    )}
                </div>

                {/* ------------------------------------------------ Consecuencias */}
                <Card className="h-fit lg:sticky lg:top-6">
                    <CardHeader>
                        <CardTitle>Lo que se va a registrar</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {/* Solo se muestra: la fecha la pone el servidor al registrar. */}
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                Fecha de solicitud
                            </p>
                            <p className="font-medium">{fecha(new Date())}</p>
                        </div>

                        {carnet === null ? (
                            <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                <Info className="mt-0.5 size-4 shrink-0" />
                                Elija a la persona y su carnet de comercializador.
                            </p>
                        ) : (
                            <>
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        A cobrar
                                    </p>
                                    <p className="text-3xl font-semibold tabular-nums">
                                        {bs(monto, institucion.moneda)}
                                    </p>

                                    {form.data.es_piscicultura && (
                                        <p className="text-xs text-muted-foreground">
                                            Con el descuento de piscicultura. Sin él serían{' '}
                                            {bs(importe, institucion.moneda)}.
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        Carga declarada
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {kilos.toFixed(2)} kg
                                    </p>
                                </div>

                                {pescador && autorizacion && (
                                    <div>
                                        <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                            Pescado de
                                        </p>
                                        <p className="font-medium">{pescador.nombreCompleto}</p>
                                        <p className="text-xs text-muted-foreground">Autorización N° {autorizacion.numero}</p>
                                        <p className="mt-1 text-sm tabular-nums">
                                            Le quedan {autorizacion.kilos_disponibles} → <strong>{quedan}</strong> kg
                                        </p>
                                    </div>
                                )}

                                {excede && pescador && autorizacion && (
                                    <p className="flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
                                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                        Se pasa por {Math.abs(quedan!)} kg: {pescador.nombreCompleto} tiene{' '}
                                        {autorizacion.kilos_disponibles} kg disponibles.
                                    </p>
                                )}

                                <p className="text-xs text-muted-foreground">
                                    La guía queda <strong>PENDIENTE</strong>: todavía no ampara
                                    nada. Se paga en Recaudaciones; cuando el pago se valida allá
                                    queda aprobada, vale {vigencia} y se puede imprimir.
                                </p>
                            </>
                        )}

                        <Button type="submit" disabled={form.processing || !completo} className="w-full">
                            Registrar guía
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}

/**
 * Los carnets vigentes, con el que no sirve deshabilitado.
 */
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
            <p className="flex items-start gap-2 rounded-md border border-dashed border-border p-3 text-sm text-muted-foreground">
                <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                Esta persona no tiene ningún carnet vigente. Hay que emitirle uno de comercializador
                antes: la guía cuelga de él.
            </p>
        );
    }

    return (
        <ul className="space-y-2">
            {carnets.map((c) => {
                const activo = elegido?.id === c.id;

                return (
                    <li key={c.id}>
                        <button
                            type="button"
                            disabled={!c.puede_emitir_guias}
                            onClick={() => onElegir(c)}
                            className={[
                                'flex w-full items-center gap-3 rounded-md border p-3 text-left transition-colors',
                                activo ? 'border-primary bg-secondary' : 'border-border',
                                c.puede_emitir_guias ? 'hover:bg-secondary' : 'cursor-not-allowed opacity-50',
                            ].join(' ')}
                        >
                            <Truck className="size-4 shrink-0 text-muted-foreground" />

                            <span className="min-w-0 flex-1">
                                <span className="block font-mono text-sm font-medium">{c.codigo}</span>
                                <span className="block text-xs text-muted-foreground">
                                    {c.tipo ?? c.tipo_actor_etiqueta}
                                </span>
                            </span>

                            {!c.puede_emitir_guias && (
                                <span className="text-xs text-muted-foreground">no emite guías</span>
                            )}
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}

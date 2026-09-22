import { Head, useForm, usePage } from '@inertiajs/react';
import { Info, ShieldAlert, Truck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { CamposGuia } from '@/components/panel/guias/campos-guia';
import { filaVacia, TablaDetalle } from '@/components/panel/guias/tabla-detalle';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { BeneficiarioSugerido, CarnetVigenteSugerido } from '@/types/beneficiarios';
import type { CatalogosGuia, FormularioGuia } from '@/types/guias';

/**
 *  REGISTRAR UNA GUÍA DE MOVIMIENTO — paso 4, rama comercializador
 *
 * NACE PENDIENTE: acá se arma el papel y nada más. Los depósitos se cargan
 * desde la ficha, y recién con la firma la guía ampara el traslado.
 */
export default function CrearGuia({
    beneficiario,
    medios,
    tiposTransporte,
    condiciones,
    diasVigencia,
    tarifaBase,
    descuentoPiscicultura,
}: CatalogosGuia & {
    beneficiario: (BeneficiarioSugerido & { carnets_vigentes: CarnetVigenteSugerido[] }) | null;
}) {
    const { institucion } = usePage<PageProps>().props;
    const [persona, setPersona] = useState<BeneficiarioSugerido | null>(beneficiario);
    const [carnet, setCarnet] = useState<CarnetVigenteSugerido | null>(null);

    const form = useForm<FormularioGuia & { carnet_id: number | null }>({
        carnet_id: null,

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

        /*
         * LA SOLICITUD ES UN DÍA, no un instante: es la fecha en que la persona
         * vino al mostrador. La emisión —y con ella los cinco días de validez—
         * la escribe la APROBACIÓN.
         */
        fecha_solicitud: new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0, 10),

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

    const monto = form.data.es_piscicultura ? tarifaBase * (1 - descuentoPiscicultura) : tarifaBase;
    const kilos = form.data.detalles.reduce((suma, f) => suma + Number(f.cantidad_kg || 0), 0);

    const completo =
        carnet !== null &&
        form.data.origen.trim() !== '' &&
        form.data.destino.trim() !== '' &&
        form.data.detalles.some(
            (f) => f.especie.trim() !== '' && f.condicion !== '' && Number(f.cantidad_kg || 0) > 0,
        );

    return (
        <LayoutPanel
            titulo="Registrar guía"
            descripcion={`Ampara UN traslado. Vale ${diasVigencia} días desde que la aprueban.`}
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

                            {carnet && (
                                <Campo
                                    etiqueta="Fecha de solicitud"
                                    htmlFor="fecha_solicitud"
                                    error={form.errors.fecha_solicitud}
                                    ayuda="El día que la persona vino al mostrador. La emisión la escribe la aprobación."
                                    obligatorio
                                    className="max-w-xs"
                                >
                                    <Input
                                        id="fecha_solicitud"
                                        type="date"
                                        value={form.data.fecha_solicitud}
                                        onChange={(e) => form.setData('fecha_solicitud', e.target.value)}
                                        aria-invalid={Boolean(form.errors.fecha_solicitud)}
                                    />
                                </Campo>
                            )}
                        </CardContent>
                    </Card>

                    {carnet && (
                        <>
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
                                            {bs(tarifaBase, institucion.moneda)}.
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

                                <p className="text-xs text-muted-foreground">
                                    La guía queda <strong>PENDIENTE</strong>: todavía no ampara
                                    nada. Desde su ficha se cargan los depósitos y se envía a
                                    revisión; recién con la firma vale {diasVigencia} días y se
                                    puede imprimir.
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

import { Head, useForm, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { BloqueProcedencia } from '@/components/panel/guias/bloque-procedencia';
import { CamposGuia } from '@/components/panel/guias/campos-guia';
import { filaVacia, TablaDetalle } from '@/components/panel/guias/tabla-detalle';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { AutorizacionVendible, BeneficiarioSugerido } from '@/types/beneficiarios';
import type { CatalogosGuia, FilaDetalle, FormularioGuia, GuiaEditable } from '@/types/guias';

/**
 *  Corregir el borrador de una guía
 *
 * La persona y el carnet llegan FIJOS, para mostrar: cambiar de titular no es
 * corregir un traslado, es emitir otro. Solo se abre en PENDIENTE —lo comprueba
 * el servidor con la fila bloqueada—; con un pago cargado en SIREB no se guarda.
 */
export default function EditarGuia({
    guia,
    medios,
    tiposTransporte,
    productos,
    sirebDisponible,
    servicioConfigurado,
    condiciones,
    vigencia,
    descuentoPiscicultura,
}: CatalogosGuia & { guia: GuiaEditable }) {
    // `general` no es un campo del formulario, así que no está en form.errors:
    // sale del bolso que Inertia comparte en cada respuesta.
    const { institucion, errors } = usePage<PageProps & { errors: Record<string, string> }>().props;

    // El pescador ya elegido; sus kilos de esta guía cuentan como disponibles.
    const [pescador, setPescador] = useState<BeneficiarioSugerido | null>(guia.pescador);
    const [autorizacion, setAutorizacion] = useState<AutorizacionVendible | null>(
        guia.pescador?.autorizaciones_vendibles.find((a) => a.id === guia.aprovechamiento_id) ?? null,
    );

    const form = useForm<FormularioGuia>({
        origen: guia.origen ?? '',
        origen_departamento: guia.origen_departamento ?? '',
        origen_provincia: guia.origen_provincia ?? '',
        origen_distrito: guia.origen_distrito ?? '',

        destino: guia.destino ?? '',
        destino_departamento: guia.destino_departamento ?? '',
        destino_provincia: guia.destino_provincia ?? '',
        destino_distrito: guia.destino_distrito ?? '',

        medio_transporte: guia.medio_transporte ?? '',
        tipo_transporte: guia.tipo_transporte ?? '',
        transporte_nombre: guia.transporte_nombre ?? '',
        transporte_placa: guia.transporte_placa ?? '',
        transporte_capacidad_kg:
            guia.transporte_capacidad_kg !== null ? String(guia.transporte_capacidad_kg) : '',

        es_piscicultura: guia.es_piscicultura,
        observaciones: guia.observaciones ?? '',
        aprovechamiento_id: autorizacion?.id ?? null,

        // Se rellena con una fila vacía si la guía quedó sin detalle: la tabla
        // necesita al menos un renglón sobre el que escribir.
        detalles:
            guia.detalles.length > 0
                ? guia.detalles.map(
                      (d): FilaDetalle => ({
                          key: `d-${d.id}`,
                          tarifa_sireb: d.tarifa_sireb,
                          condicion: d.condicion,
                          cantidad_kg: String(d.cantidad_kg),
                      }),
                  )
                : [filaVacia()],
    });

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.patch(route('guias.update', guia.id));
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
    const quedan = autorizacion !== null ? Math.round((autorizacion.kilos_disponibles - kilos) * 100) / 100 : null;
    const excede = quedan !== null && quedan < 0;

    const completo =
        (form.data.es_piscicultura || (autorizacion !== null && !excede)) &&
        form.data.origen.trim() !== '' &&
        form.data.destino.trim() !== '' &&
        form.data.detalles.some(
            (f) => f.tarifa_sireb !== '' && f.condicion !== '' && Number(f.cantidad_kg || 0) > 0,
        );

    return (
        <LayoutPanel
            titulo={`Corregir guía N° ${guia.numero_legible}`}
            descripcion={`${guia.beneficiario ?? '—'} · Carnet N° ${guia.carnet_registro ?? '—'}`}
        >
            <Head title={`Corregir guía N° ${guia.numero_legible}`} />

            {errors.general && (
                <p
                    className="mb-6 flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive"
                    role="alert"
                >
                    <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                    {errors.general}
                </p>
            )}

            <form onSubmit={enviar} className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
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

                        <CardContent className="space-y-5">
                            {/* No se corrige: la puso el servidor al registrar. */}
                            <Campo etiqueta="Fecha de solicitud" htmlFor="fecha_solicitud" className="max-w-xs">
                                <Input id="fecha_solicitud" value={guia.fecha_solicitud ? fecha(guia.fecha_solicitud) : '—'} readOnly disabled />
                            </Campo>

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
                </div>

                <Card className="h-fit lg:sticky lg:top-6">
                    <CardHeader>
                        <CardTitle>Lo que queda corregido</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
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
                            <p className="text-xl font-semibold tabular-nums">{kilos.toFixed(2)} kg</p>
                        </div>

                        {pescador && autorizacion && (
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">Pescado de</p>
                                <p className="font-medium">{pescador.nombreCompleto}</p>
                                <p className="text-xs text-muted-foreground">Autorización N° {autorizacion.numero}</p>
                                <p className="mt-1 text-sm tabular-nums">
                                    Le quedarían <strong>{quedan}</strong> kg
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
                            Sigue <strong>PENDIENTE</strong>. Vale {vigencia}, no desde hoy.
                        </p>

                        <Button type="submit" disabled={form.processing || !completo} className="w-full">
                            Guardar correcciones
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}

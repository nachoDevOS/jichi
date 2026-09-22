import { Head, useForm, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { type FormEvent } from 'react';
import { CamposGuia } from '@/components/panel/guias/campos-guia';
import { filaVacia, TablaDetalle } from '@/components/panel/guias/tabla-detalle';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { CatalogosGuia, FilaDetalle, FormularioGuia, GuiaEditable } from '@/types/guias';

/**
 *  CORREGIR EL BORRADOR DE UNA GUÍA
 *
 * La persona y el carnet llegan FIJOS, para mostrar: cambiar de titular no es
 * corregir un traslado, es emitir otro. Solo se abre en PENDIENTE y sin un
 * depósito cargado — lo comprueba el servidor con la fila bloqueada.
 */
export default function EditarGuia({
    guia,
    medios,
    tiposTransporte,
    condiciones,
    diasVigencia,
    tarifaBase,
    descuentoPiscicultura,
}: CatalogosGuia & { guia: GuiaEditable }) {
    // `general` no es un campo del formulario, así que no está en form.errors:
    // sale del bolso que Inertia comparte en cada respuesta.
    const { institucion, errors } = usePage<PageProps & { errors: Record<string, string> }>().props;

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
        fecha_solicitud: guia.fecha_solicitud ?? '',

        // Se rellena con una fila vacía si la guía quedó sin detalle: la tabla
        // necesita al menos un renglón sobre el que escribir.
        detalles:
            guia.detalles.length > 0
                ? guia.detalles.map(
                      (d): FilaDetalle => ({
                          key: `d-${d.id}`,
                          especie: d.especie,
                          condicion: d.condicion,
                          cantidad_kg: String(d.cantidad_kg),
                          precio_kg: String(d.precio_kg),
                      }),
                  )
                : [filaVacia()],
    });

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.patch(route('guias.update', guia.id));
    }

    const monto = form.data.es_piscicultura ? tarifaBase * (1 - descuentoPiscicultura) : tarifaBase;
    const kilos = form.data.detalles.reduce((suma, f) => suma + Number(f.cantidad_kg || 0), 0);

    const completo =
        form.data.origen.trim() !== '' &&
        form.data.destino.trim() !== '' &&
        form.data.detalles.some(
            (f) => f.especie.trim() !== '' && f.condicion !== '' && Number(f.cantidad_kg || 0) > 0,
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
                    <Card>
                        <CardHeader>
                            <CardTitle>Ubicación y transporte</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-5">
                            <Campo
                                etiqueta="Fecha de solicitud"
                                htmlFor="fecha_solicitud"
                                error={form.errors.fecha_solicitud}
                                ayuda="El día que la persona vino al mostrador."
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
                                    {bs(tarifaBase, institucion.moneda)}.
                                </p>
                            )}
                        </div>

                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                Carga declarada
                            </p>
                            <p className="text-xl font-semibold tabular-nums">{kilos.toFixed(2)} kg</p>
                        </div>

                        <p className="text-xs text-muted-foreground">
                            Sigue <strong>PENDIENTE</strong>. Vale {diasVigencia} días desde que la
                            aprueben, no desde hoy.
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

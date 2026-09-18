import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { BuscadorCarnet } from '@/components/panel/carnets/buscador-carnet';
import { filaVacia, GrillaDetalle } from '@/components/panel/guias/grilla-detalle';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import LayoutPanel from '@/layouts/layout-panel';
import type { OpcionEnum } from '@/types';
import type { CarnetElegible } from '@/types/faenas';
import type { FormularioGuia, TipoTransporte } from '@/types/guias';

/**
 * Emisión de una guía única de transporte.
 *
 * ----------------------------------------------------------------------------
 *  CABECERA Y CARGA SE MANDAN JUNTAS
 * ----------------------------------------------------------------------------
 *
 * La guía son dos tablas —`guias` y `guia_detalles`— pero UN solo envío: una
 * guía sin líneas no ampara nada y no se distingue de una cargada a medias. El
 * servidor las escribe en la misma transacción. Ver `GuiaService::emitir()`.
 *
 * Por eso tampoco hay un botón de «guardar borrador»: no existe la guía a medio
 * emitir.
 */
export default function CrearGuia({
    carnetElegido,
    transportes,
    condiciones,
}: {
    /** Viene cargado cuando se llegó desde la ficha de un carnet. */
    carnetElegido: CarnetElegible | null;
    transportes: OpcionEnum[];
    condiciones: OpcionEnum[];
}) {
    const [carnet, setCarnet] = useState<CarnetElegible | null>(carnetElegido);

    const form = useForm<FormularioGuia>({
        carnet_id: carnetElegido?.id ?? null,
        nro_guia: '',
        nro_recibo: '',
        origen_lugar: '',
        origen_depto: 'Beni',
        origen_provincia: '',
        origen_distrito: '',
        destino_lugar: '',
        destino_depto: '',
        destino_provincia: '',
        destino_distrito: '',
        // El río es el medio más frecuente en el Beni: arranca ahí y el operador
        // lo cambia cuando no.
        tipo_transporte: 'fluvial',
        transporte_nombre: '',
        transporte_placa: '',
        capacidad_maxima: '',
        observaciones: '',
        // Una fila en blanco para que haya dónde escribir desde el principio.
        detalles: [filaVacia()],
    });

    function elegirCarnet(elegido: CarnetElegible | null) {
        setCarnet(elegido);
        form.setData('carnet_id', elegido?.id ?? null);
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.post(route('guias.store'));
    }

    // El rótulo del identificador del vehículo cambia con el medio: a una canoa
    // no se le pide la «placa». Espeja TipoTransporte::rotuloIdentificacion().
    const rotuloPlaca = form.data.tipo_transporte === 'terrestre' ? 'Placa' : 'Matrícula';

    return (
        <LayoutPanel
            titulo="Nueva guía de transporte"
            descripcion="Guía única de transporte de productos ictícolas. Copie los datos del talonario."
        >
            <Head title="Nueva guía" />

            {/*
                EL ANCHO ES EL MISMO EN TODOS LOS FORMULARIOS DEL PANEL: max-w-7xl.
                Ver el comentario de pages/panel/beneficiarios/crear.tsx.

                Estaba más angosto y se notaba: en una pantalla de 1440 px
                quedaban casi 400 px vacíos a cada lado y los campos —que son
                cortos, números y nombres— ocupaban media pantalla.

                No se quita del todo el límite: en un monitor muy ancho, un
                formulario sin tope estira los renglones hasta que leerlos obliga
                a barrer la cabeza de lado a lado.
            */}
            <form onSubmit={enviar} className="mx-auto max-w-7xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Carnet de comercializador</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {/* Solo carnets VIGENTES de rubros que emiten guías. El
                            filtro está en el servidor: CarnetController::buscar(). */}
                        <BuscadorCarnet
                            permiso="guias"
                            seleccionado={carnet}
                            onSeleccionar={elegirCarnet}
                            error={form.errors.carnet_id}
                        />

                        {form.errors.carnet_id && (
                            <p className="text-sm text-destructive" role="alert">
                                {form.errors.carnet_id}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Datos de la guía</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Campo
                            etiqueta="Nº de guía"
                            htmlFor="nro_guia"
                            obligatorio
                            error={form.errors.nro_guia}
                            ayuda="El número que ya trae impreso el formulario del talonario."
                        >
                            <Input
                                id="nro_guia"
                                maxLength={50}
                                value={form.data.nro_guia}
                                onChange={(e) => form.setData('nro_guia', e.target.value)}
                                aria-invalid={Boolean(form.errors.nro_guia)}
                            />
                        </Campo>

                        <Campo etiqueta="Nº de recibo" htmlFor="nro_recibo" error={form.errors.nro_recibo}>
                            <Input
                                id="nro_recibo"
                                maxLength={50}
                                value={form.data.nro_recibo}
                                onChange={(e) => form.setData('nro_recibo', e.target.value)}
                            />
                        </Campo>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Origen</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta="Lugar"
                                htmlFor="origen_lugar"
                                obligatorio
                                className="sm:col-span-2"
                                error={form.errors.origen_lugar}
                            >
                                <Input
                                    id="origen_lugar"
                                    maxLength={150}
                                    value={form.data.origen_lugar}
                                    onChange={(e) => form.setData('origen_lugar', e.target.value)}
                                    aria-invalid={Boolean(form.errors.origen_lugar)}
                                />
                            </Campo>

                            <Campo etiqueta="Departamento" htmlFor="origen_depto" error={form.errors.origen_depto}>
                                <Input
                                    id="origen_depto"
                                    maxLength={100}
                                    value={form.data.origen_depto}
                                    onChange={(e) => form.setData('origen_depto', e.target.value)}
                                />
                            </Campo>

                            <Campo etiqueta="Provincia" htmlFor="origen_provincia" error={form.errors.origen_provincia}>
                                <Input
                                    id="origen_provincia"
                                    maxLength={100}
                                    value={form.data.origen_provincia}
                                    onChange={(e) => form.setData('origen_provincia', e.target.value)}
                                />
                            </Campo>

                            <Campo
                                etiqueta="Distrito"
                                htmlFor="origen_distrito"
                                className="sm:col-span-2"
                                error={form.errors.origen_distrito}
                            >
                                <Input
                                    id="origen_distrito"
                                    maxLength={100}
                                    value={form.data.origen_distrito}
                                    onChange={(e) => form.setData('origen_distrito', e.target.value)}
                                />
                            </Campo>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Destino</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta="Lugar"
                                htmlFor="destino_lugar"
                                obligatorio
                                className="sm:col-span-2"
                                error={form.errors.destino_lugar}
                            >
                                <Input
                                    id="destino_lugar"
                                    maxLength={150}
                                    value={form.data.destino_lugar}
                                    onChange={(e) => form.setData('destino_lugar', e.target.value)}
                                    aria-invalid={Boolean(form.errors.destino_lugar)}
                                />
                            </Campo>

                            <Campo etiqueta="Departamento" htmlFor="destino_depto" error={form.errors.destino_depto}>
                                <Input
                                    id="destino_depto"
                                    maxLength={100}
                                    value={form.data.destino_depto}
                                    onChange={(e) => form.setData('destino_depto', e.target.value)}
                                />
                            </Campo>

                            <Campo
                                etiqueta="Provincia"
                                htmlFor="destino_provincia"
                                error={form.errors.destino_provincia}
                            >
                                <Input
                                    id="destino_provincia"
                                    maxLength={100}
                                    value={form.data.destino_provincia}
                                    onChange={(e) => form.setData('destino_provincia', e.target.value)}
                                />
                            </Campo>

                            <Campo
                                etiqueta="Distrito"
                                htmlFor="destino_distrito"
                                className="sm:col-span-2"
                                error={form.errors.destino_distrito}
                            >
                                <Input
                                    id="destino_distrito"
                                    maxLength={100}
                                    value={form.data.destino_distrito}
                                    onChange={(e) => form.setData('destino_distrito', e.target.value)}
                                />
                            </Campo>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Transporte</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Campo
                            etiqueta="Medio"
                            htmlFor="tipo_transporte"
                            obligatorio
                            error={form.errors.tipo_transporte}
                            // Se aclara el porqué: es el único campo del bloque
                            // que es obligatorio, y no se entiende solo.
                            ayuda="Define dónde se controla la carga: el río, la carretera o la pista."
                        >
                            <Select
                                id="tipo_transporte"
                                value={form.data.tipo_transporte}
                                onChange={(e) =>
                                    form.setData('tipo_transporte', e.target.value as TipoTransporte)
                                }
                                aria-invalid={Boolean(form.errors.tipo_transporte)}
                            >
                                {transportes.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </Select>
                        </Campo>

                        <Campo
                            etiqueta="Empresa o transportista"
                            htmlFor="transporte_nombre"
                            obligatorio
                            error={form.errors.transporte_nombre}
                        >
                            <Input
                                id="transporte_nombre"
                                maxLength={150}
                                value={form.data.transporte_nombre}
                                onChange={(e) => form.setData('transporte_nombre', e.target.value)}
                                aria-invalid={Boolean(form.errors.transporte_nombre)}
                            />
                        </Campo>

                        <Campo
                            etiqueta={rotuloPlaca}
                            htmlFor="transporte_placa"
                            error={form.errors.transporte_placa}
                        >
                            <Input
                                id="transporte_placa"
                                maxLength={50}
                                value={form.data.transporte_placa}
                                onChange={(e) => form.setData('transporte_placa', e.target.value)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Capacidad del transporte (kg)"
                            htmlFor="capacidad_maxima"
                            error={form.errors.capacidad_maxima}
                            // Si se carga, el servidor rechaza la guía cuando la
                            // carga declarada la supera. Por eso se avisa que es
                            // opcional: de una canoa nadie la conoce.
                            ayuda="Opcional. Si la carga y la carga declarada la supera, la guía no se emite."
                        >
                            <Input
                                id="capacidad_maxima"
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.data.capacidad_maxima}
                                onChange={(e) => form.setData('capacidad_maxima', e.target.value)}
                            />
                        </Campo>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Carga</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <GrillaDetalle
                            filas={form.data.detalles}
                            condiciones={condiciones}
                            onCambiar={(filas) => form.setData('detalles', filas)}
                            errores={form.errors as unknown as Record<string, string>}
                        />

                        <Campo
                            etiqueta="Observaciones"
                            htmlFor="observaciones"
                            error={form.errors.observaciones}
                        >
                            <Textarea
                                id="observaciones"
                                rows={2}
                                value={form.data.observaciones}
                                onChange={(e) => form.setData('observaciones', e.target.value)}
                            />
                        </Campo>
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-3">
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => window.history.back()}
                        disabled={form.processing}
                    >
                        Cancelar
                    </Button>

                    <Button type="submit" disabled={form.processing || !form.data.carnet_id}>
                        {form.processing ? 'Emitiendo…' : 'Emitir guía'}
                    </Button>
                </div>
            </form>
        </LayoutPanel>
    );
}

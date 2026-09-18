import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { BuscadorCarnet } from '@/components/panel/carnets/buscador-carnet';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import LayoutPanel from '@/layouts/layout-panel';
import type { PageProps } from '@/types';
import type { CarnetElegible, FormularioFaena } from '@/types/faenas';

/**
 * Emisión de una faena: el permiso de UNA salida de pesca.
 *
 * ----------------------------------------------------------------------------
 *  EL FORMULARIO CALCA EL PAPEL
 * ----------------------------------------------------------------------------
 *
 * El operador tiene el talonario delante y va copiando. Por eso el orden de los
 * campos es el del formulario físico y no el que quedaría más prolijo en
 * pantalla: si no coinciden, hay que ir saltando de un lado al otro y ahí es
 * donde se transponen los números.
 *
 * ----------------------------------------------------------------------------
 *  UN SOLO PASO, NO UN ASISTENTE
 * ----------------------------------------------------------------------------
 *
 * A diferencia del trámite, que va en tres pasos porque sube archivos y decide
 * cosas en el medio. Acá no hay nada que decidir: se elige el carnet y se copia
 * el papel. Partirlo en pasos solo agregaría clics a algo que se hace con la
 * persona esperando en el mostrador.
 */
export default function CrearFaena({
    carnetElegido,
    montoSugerido,
}: {
    /** Viene cargado cuando se llegó desde la ficha de un carnet. */
    carnetElegido: CarnetElegible | null;
    montoSugerido: number;
}) {
    const { institucion } = usePage<PageProps>().props;

    /*
     * El carnet se guarda en dos lugares a propósito: `form.data.carnet_id` es
     * lo que viaja al servidor, y este estado es el objeto entero que el
     * buscador necesita para poder dibujar la tarjeta del elegido. Mandar el
     * objeto completo al servidor sería mandar datos que ya tiene.
     */
    const [carnet, setCarnet] = useState<CarnetElegible | null>(carnetElegido);

    const form = useForm<FormularioFaena>({
        carnet_id: carnetElegido?.id ?? null,
        nro_permiso: '',
        nro_recibo: '',
        monto: String(montoSugerido),
        embarcacion: '',
        propietario: '',
        comandante_barco: '',
        matricula_naval: '',
        nro_kardex: '',
        region_desde: '',
        region_hasta: '',
        // Hoy: es la salida más común, y el operador la corrige si no.
        fecha_salida: new Date().toISOString().slice(0, 10),
        fecha_desembarque: '',
        cantidad_autorizada_kg: '',
        observaciones: '',
    });

    function elegirCarnet(elegido: CarnetElegible | null) {
        setCarnet(elegido);
        form.setData('carnet_id', elegido?.id ?? null);
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.post(route('faenas.store'));
    }

    return (
        <LayoutPanel
            titulo="Nueva faena"
            descripcion="Permiso por salida de pesca. Copie los datos del formulario del talonario."
        >
            <Head title="Nueva faena" />

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
                        <CardTitle>Carnet de pescador</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {/*
                            El buscador solo trae carnets VIGENTES de rubros que
                            emiten faenas. El filtro está en el servidor: ver
                            CarnetController::buscar().
                        */}
                        <BuscadorCarnet
                            permiso="faenas"
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
                        <CardTitle>Datos del permiso</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Campo
                            etiqueta="Nº de permiso"
                            htmlFor="nro_permiso"
                            obligatorio
                            error={form.errors.nro_permiso}
                            ayuda="El número que ya trae impreso el formulario del talonario."
                        >
                            <Input
                                id="nro_permiso"
                                maxLength={50}
                                value={form.data.nro_permiso}
                                onChange={(e) => form.setData('nro_permiso', e.target.value)}
                                aria-invalid={Boolean(form.errors.nro_permiso)}
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

                        <Campo
                            etiqueta={`Monto (${institucion.moneda})`}
                            htmlFor="monto"
                            error={form.errors.monto}
                            ayuda="Viene con la tarifa vigente. Cámbielo solo si el papel dice otra cosa."
                        >
                            <Input
                                id="monto"
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.data.monto}
                                onChange={(e) => form.setData('monto', e.target.value)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Cantidad autorizada (kg)"
                            htmlFor="cantidad_autorizada_kg"
                            obligatorio
                            error={form.errors.cantidad_autorizada_kg}
                            // Se aclara porque es la confusión más frecuente: el
                            // carnet ya trae un cupo anual, y este es otro tope.
                            ayuda="El tope de ESTA salida, no el cupo anual del carnet."
                        >
                            <Input
                                id="cantidad_autorizada_kg"
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={form.data.cantidad_autorizada_kg}
                                onChange={(e) => form.setData('cantidad_autorizada_kg', e.target.value)}
                                aria-invalid={Boolean(form.errors.cantidad_autorizada_kg)}
                            />
                        </Campo>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Embarcación</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Campo
                            etiqueta="Embarcación"
                            htmlFor="embarcacion"
                            obligatorio
                            error={form.errors.embarcacion}
                        >
                            <Input
                                id="embarcacion"
                                maxLength={150}
                                value={form.data.embarcacion}
                                onChange={(e) => form.setData('embarcacion', e.target.value)}
                                aria-invalid={Boolean(form.errors.embarcacion)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Comandante"
                            htmlFor="comandante_barco"
                            obligatorio
                            error={form.errors.comandante_barco}
                        >
                            <Input
                                id="comandante_barco"
                                maxLength={150}
                                value={form.data.comandante_barco}
                                onChange={(e) => form.setData('comandante_barco', e.target.value)}
                                aria-invalid={Boolean(form.errors.comandante_barco)}
                            />
                        </Campo>

                        <Campo etiqueta="Propietario" htmlFor="propietario" error={form.errors.propietario}>
                            <Input
                                id="propietario"
                                maxLength={150}
                                value={form.data.propietario}
                                onChange={(e) => form.setData('propietario', e.target.value)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Matrícula naval"
                            htmlFor="matricula_naval"
                            error={form.errors.matricula_naval}
                        >
                            <Input
                                id="matricula_naval"
                                maxLength={50}
                                value={form.data.matricula_naval}
                                onChange={(e) => form.setData('matricula_naval', e.target.value)}
                            />
                        </Campo>

                        <Campo etiqueta="Nº de kardex" htmlFor="nro_kardex" error={form.errors.nro_kardex}>
                            <Input
                                id="nro_kardex"
                                maxLength={50}
                                value={form.data.nro_kardex}
                                onChange={(e) => form.setData('nro_kardex', e.target.value)}
                            />
                        </Campo>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recorrido y fechas</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Campo
                            etiqueta="Región de salida"
                            htmlFor="region_desde"
                            obligatorio
                            error={form.errors.region_desde}
                        >
                            <Input
                                id="region_desde"
                                maxLength={150}
                                value={form.data.region_desde}
                                onChange={(e) => form.setData('region_desde', e.target.value)}
                                aria-invalid={Boolean(form.errors.region_desde)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Región de destino"
                            htmlFor="region_hasta"
                            error={form.errors.region_hasta}
                        >
                            <Input
                                id="region_hasta"
                                maxLength={150}
                                value={form.data.region_hasta}
                                onChange={(e) => form.setData('region_hasta', e.target.value)}
                            />
                        </Campo>

                        {/*
                            Las dos fechas definen la VENTANA del permiso: fuera
                            de ella no autoriza nada, y un control en el río
                            compara contra eso.
                        */}
                        <Campo
                            etiqueta="Fecha de salida"
                            htmlFor="fecha_salida"
                            obligatorio
                            error={form.errors.fecha_salida}
                        >
                            <Input
                                id="fecha_salida"
                                type="date"
                                value={form.data.fecha_salida}
                                onChange={(e) => form.setData('fecha_salida', e.target.value)}
                                aria-invalid={Boolean(form.errors.fecha_salida)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Fecha de desembarque"
                            htmlFor="fecha_desembarque"
                            obligatorio
                            error={form.errors.fecha_desembarque}
                            ayuda="Puede ser el mismo día: una salida que va y vuelve en la jornada."
                        >
                            <Input
                                id="fecha_desembarque"
                                type="date"
                                // El navegador ya impide elegir una fecha
                                // anterior; el servidor lo valida igual, porque
                                // el atributo se puede saltear.
                                min={form.data.fecha_salida || undefined}
                                value={form.data.fecha_desembarque}
                                onChange={(e) => form.setData('fecha_desembarque', e.target.value)}
                                aria-invalid={Boolean(form.errors.fecha_desembarque)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Observaciones"
                            htmlFor="observaciones"
                            className="sm:col-span-2 lg:col-span-4"
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

                    {/* Sin carnet no hay nada que emitir: el botón se apaga en
                        vez de dejar mandar algo que va a rebotar. */}
                    <Button type="submit" disabled={form.processing || !form.data.carnet_id}>
                        {form.processing ? 'Emitiendo…' : 'Emitir faena'}
                    </Button>
                </div>
            </form>
        </LayoutPanel>
    );
}

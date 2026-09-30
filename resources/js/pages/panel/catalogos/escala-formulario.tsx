import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn } from '@/lib/utils';
import type { ModalidadAprovechamiento, OpcionEnum, PageProps } from '@/types';
import type { ServicioSireb, TramoFormulario } from '@/types/catalogos';

/**
 *  Alta y edición de un tramo de la escala: la misma pantalla para los dos casos
 */
export default function EscalaFormulario({
    tramo,
    modalidades,
    serviciosSireb,
    tarifasUsadas,
}: {
    /** null = alta. */
    tramo: TramoFormulario | null;
    /** Las opciones salen del enum de PHP: escritas acá se desincronizan. */
    modalidades: OpcionEnum[];
    /** null = SIREB no respondió: el select de tarifa queda deshabilitado. */
    serviciosSireb: ServicioSireb[] | null;
    /** Id de tarifa → n° de la escala que ya la usa. */
    tarifasUsadas: Record<string, number>;
}) {
    const esAlta = tramo === null;
    const { institucion } = usePage<PageProps>().props;

    // Sin `nro_escala`: lo asigna el servidor al dar de alta.
    const form = useForm({
        // Escala general por defecto: son seis de siete tramos, y el régimen
        // especial es la excepción que se marca a propósito.
        modalidad: tramo?.modalidad ?? ('escala_general' as ModalidadAprovechamiento),
        descripcion_kg: tramo?.descripcion_kg ?? '',
        kilos_min: tramo?.kilos_min ?? '',
        kilos_max: tramo?.kilos_max ?? '',
        servicio_sireb: tramo?.servicio_sireb ?? '',
        tarifa_sireb: tramo?.tarifa_sireb ?? '',
        estado: tramo?.estado ?? true,
    });

    // La tarifa guardada ya no llega de SIREB (o SIREB no responde): se ofrece
    // igual, para que editar otro campo no la borre sin avisar.
    const tarifaActualAusente =
        tramo !== null &&
        !(serviciosSireb ?? []).some((s) => s.tarifas.some((t) => t.id === tramo.tarifa_sireb));

    // Una sola elección llena los dos ids: la tarifa y el servicio de su grupo.
    function elegirTarifa(tarifaId: string) {
        const servicio = serviciosSireb?.find((s) => s.tarifas.some((t) => t.id === tarifaId));

        form.setData((d) => ({
            ...d,
            tarifa_sireb: tarifaId,
            servicio_sireb: servicio?.id ?? tramo?.servicio_sireb ?? '',
        }));
    }

    function enviar(e: FormEvent) {
        e.preventDefault();

        if (esAlta) {
            form.post(route('categorias-aprovechamiento.store'));
        } else {
            form.put(route('categorias-aprovechamiento.update', tramo.id));
        }
    }

    const volver = (
        <Link href={route('categorias-aprovechamiento.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
            <ArrowLeft className="size-4" />
            Volver a la escala
        </Link>
    );

    return (
        <LayoutPanel
            titulo={esAlta ? 'Nuevo tramo' : 'Editar tramo'}
            descripcion={esAlta ? 'Un tramo de la escala oficial de aprovechamiento.' : tramo.descripcion_kg}
            acciones={volver}
        >
            <Head title={esAlta ? 'Nuevo tramo' : 'Editar tramo'} />

            <Card>
                <CardContent className="pt-6">
                    <form onSubmit={enviar} className="space-y-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta="Texto de la resolución"
                                htmlFor="descripcion_kg"
                                error={form.errors.descripcion_kg}
                                ayuda="Tal como figura en el documento. Es lo que se imprime, y no siempre es la lectura de los números."
                                obligatorio
                            >
                                <Input
                                    id="descripcion_kg"
                                    value={form.data.descripcion_kg}
                                    onChange={(e) => form.setData('descripcion_kg', e.target.value)}
                                    aria-invalid={Boolean(form.errors.descripcion_kg)}
                                    placeholder="201 Kg Hasta 500 Kg"
                                />
                            </Campo>

                            <Campo
                                etiqueta="Régimen"
                                htmlFor="modalidad"
                                error={form.errors.modalidad}
                                ayuda="La escala general es la progresión por kilos; la especie especial lleva tasación fija por resolución."
                                obligatorio
                            >
                                <Select
                                    id="modalidad"
                                    value={form.data.modalidad}
                                    onChange={(e) => form.setData('modalidad', e.target.value as ModalidadAprovechamiento)}
                                    aria-invalid={Boolean(form.errors.modalidad)}
                                >
                                    {modalidades.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))}
                                </Select>
                            </Campo>

                            <Campo etiqueta="Desde (kg)" htmlFor="kilos_min" error={form.errors.kilos_min} obligatorio>
                                <Input
                                    id="kilos_min"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={form.data.kilos_min}
                                    onChange={(e) => form.setData('kilos_min', e.target.value)}
                                    aria-invalid={Boolean(form.errors.kilos_min)}
                                />
                            </Campo>

                            <Campo etiqueta="Hasta (kg)" htmlFor="kilos_max" error={form.errors.kilos_max} obligatorio>
                                <Input
                                    id="kilos_max"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={form.data.kilos_max}
                                    onChange={(e) => form.setData('kilos_max', e.target.value)}
                                    aria-invalid={Boolean(form.errors.kilos_max)}
                                />
                            </Campo>

                            <div className="sm:col-span-2">
                                <Campo
                                    etiqueta="Tarifa SIREB"
                                    htmlFor="tarifa_sireb"
                                    error={form.errors.tarifa_sireb ?? form.errors.servicio_sireb}
                                    ayuda={
                                        serviciosSireb === null
                                            ? 'Recaudaciones (SIREB) no responde: no se puede elegir la tarifa ahora.'
                                            : 'De ahí sale el precio, que se congela al otorgar el cupo.'
                                    }
                                    obligatorio
                                >
                                    <Select
                                        id="tarifa_sireb"
                                        value={form.data.tarifa_sireb}
                                        onChange={(e) => elegirTarifa(e.target.value)}
                                        disabled={serviciosSireb === null}
                                        aria-invalid={Boolean(form.errors.tarifa_sireb ?? form.errors.servicio_sireb)}
                                    >
                                        <option value="">Elija una tarifa…</option>

                                        {tarifaActualAusente && (
                                            <option value={tramo.tarifa_sireb}>
                                                Tarifa actual{serviciosSireb === null ? '' : ' (no está en SIREB)'}
                                            </option>
                                        )}

                                        {(serviciosSireb ?? []).map((s) => (
                                            <optgroup
                                                key={s.id}
                                                label={`${s.nombre}${s.codigo ? ` (${s.codigo})` : ''}${s.activo ? '' : ' — de baja'}`}
                                            >
                                                {s.tarifas.map((t) => {
                                                    const usadaPor = tarifasUsadas[t.id];
                                                    // La propia tarifa del tramo no cuenta como ocupada.
                                                    const ocupada = usadaPor !== undefined && t.id !== tramo?.tarifa_sireb;

                                                    return (
                                                        <option key={t.id} value={t.id} disabled={!s.activo || ocupada}>
                                                            {t.etiqueta || 'Sin etiqueta'} — {bs(t.monto, institucion.moneda)}
                                                            {ocupada ? ` (escala ${usadaPor})` : ''}
                                                        </option>
                                                    );
                                                })}
                                            </optgroup>
                                        ))}
                                    </Select>
                                </Campo>
                            </div>

                            <Campo etiqueta="Vigente" htmlFor="estado" error={form.errors.estado}>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        id="estado"
                                        type="checkbox"
                                        checked={form.data.estado}
                                        onChange={(e) => form.setData('estado', e.target.checked)}
                                        className="size-4 rounded border-input"
                                    />
                                    Se puede elegir al otorgar un cupo
                                </label>
                            </Campo>
                        </div>

                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing}>
                                {esAlta ? 'Registrar' : 'Guardar cambios'}
                            </Button>

                            <Link
                                href={route('categorias-aprovechamiento.index')}
                                className={cn(buttonVariants({ variant: 'outline' }))}
                            >
                                Cancelar
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </LayoutPanel>
    );
}

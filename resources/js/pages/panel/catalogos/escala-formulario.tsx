import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { SelectorTarifaSireb } from '@/components/panel/catalogos/selector-tarifa-sireb';
import { Button, buttonVariants } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { cn } from '@/lib/utils';
import type { ModalidadAprovechamiento, OpcionEnum } from '@/types';
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
    /** Id de tarifa → qué tramo ya la usa. */
    tarifasUsadas: Record<string, string>;
}) {
    const esAlta = tramo === null;

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
                                <SelectorTarifaSireb
                                    tarifa={form.data.tarifa_sireb}
                                    tarifaGuardada={tramo?.tarifa_sireb ?? null}
                                    servicioGuardado={tramo?.servicio_sireb ?? null}
                                    serviciosSireb={serviciosSireb}
                                    tarifasUsadas={tarifasUsadas}
                                    error={form.errors.tarifa_sireb ?? form.errors.servicio_sireb}
                                    onElegir={(tarifa, servicio) =>
                                        form.setData((d) => ({ ...d, tarifa_sireb: tarifa, servicio_sireb: servicio }))
                                    }
                                />
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

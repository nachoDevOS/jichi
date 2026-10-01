import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { SelectorTarifaSireb } from '@/components/panel/catalogos/selector-tarifa-sireb';
import { Button, buttonVariants } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import LayoutPanel from '@/layouts/layout-panel';
import { cn } from '@/lib/utils';
import type { ProductoFormulario, ServicioSireb } from '@/types/catalogos';

/**
 *  Alta y edición de un producto del cuadro D: la misma pantalla para los dos casos
 */
export default function ProductosFormulario({
    producto,
    serviciosSireb,
    tarifasUsadas,
}: {
    /** null = alta. */
    producto: ProductoFormulario | null;
    /** null = SIREB no respondió: el select de tarifa queda deshabilitado. */
    serviciosSireb: ServicioSireb[] | null;
    /** Id de tarifa → qué productos ya la usan. */
    tarifasUsadas: Record<string, string>;
}) {
    const esAlta = producto === null;

    const form = useForm({
        nombre: producto?.nombre ?? '',
        servicio_sireb: producto?.servicio_sireb ?? '',
        tarifa_sireb: producto?.tarifa_sireb ?? '',
        estado: producto?.estado ?? true,
    });

    function enviar(e: FormEvent) {
        e.preventDefault();

        if (esAlta) {
            form.post(route('productos.store'));
        } else {
            form.put(route('productos.update', producto.id));
        }
    }

    const titulo = esAlta ? 'Nuevo producto' : 'Editar producto';

    return (
        <LayoutPanel
            titulo={titulo}
            descripcion={esAlta ? 'Una especie del cuadro D de la guía.' : producto.nombre}
            acciones={
                <Link href={route('productos.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                    <ArrowLeft className="size-4" />
                    Volver a productos
                </Link>
            }
        >
            <Head title={titulo} />

            <Card>
                <CardContent className="pt-6">
                    <form onSubmit={enviar} className="space-y-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta="Nombre" htmlFor="nombre" error={form.errors.nombre} obligatorio>
                                <Input
                                    id="nombre"
                                    value={form.data.nombre}
                                    onChange={(e) => form.setData('nombre', e.target.value)}
                                    aria-invalid={Boolean(form.errors.nombre)}
                                    placeholder="Surubí"
                                />
                            </Campo>

                            <Campo etiqueta="Estado" htmlFor="estado" error={form.errors.estado}>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        id="estado"
                                        type="checkbox"
                                        checked={form.data.estado}
                                        onChange={(e) => form.setData('estado', e.target.checked)}
                                        className="size-4 rounded border-input"
                                    />
                                    Activo: se puede elegir en una guía
                                </label>
                            </Campo>

                            <div className="sm:col-span-2">
                                <SelectorTarifaSireb
                                    tarifa={form.data.tarifa_sireb}
                                    tarifaGuardada={producto?.tarifa_sireb ?? null}
                                    servicioGuardado={producto?.servicio_sireb ?? null}
                                    serviciosSireb={serviciosSireb}
                                    tarifasUsadas={tarifasUsadas}
                                    error={form.errors.tarifa_sireb ?? form.errors.servicio_sireb}
                                    onElegir={(tarifa, servicio) =>
                                        form.setData((d) => ({ ...d, tarifa_sireb: tarifa, servicio_sireb: servicio }))
                                    }
                                />
                            </div>
                        </div>

                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing}>
                                {esAlta ? 'Registrar producto' : 'Guardar cambios'}
                            </Button>

                            <Link href={route('productos.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                                Cancelar
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </LayoutPanel>
    );
}

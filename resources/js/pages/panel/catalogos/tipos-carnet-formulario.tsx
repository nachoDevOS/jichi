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
import type { OpcionEnum, TipoActor } from '@/types';
import type { ServicioSireb, TipoCarnetFormulario } from '@/types/catalogos';

/**
 *  CORRECCIÓN de un tipo de carnet. No hay alta: el catálogo sale de la resolución
 */
export default function TiposCarnetFormulario({
    tipo,
    actores,
    serviciosSireb,
    tarifasUsadas,
}: {
    tipo: TipoCarnetFormulario;
    actores: OpcionEnum[];
    /** null = SIREB no respondió: el select de tarifa queda deshabilitado. */
    serviciosSireb: ServicioSireb[] | null;
    /** Id de tarifa → qué tipo ya la usa. */
    tarifasUsadas: Record<string, string>;
}) {
    const form = useForm({
        nombre: tipo.nombre,
        tipo_actor: tipo.tipo_actor,
        servicio_sireb: tipo.servicio_sireb ?? '',
        tarifa_sireb: tipo.tarifa_sireb ?? '',
        estado: tipo.estado,
    });

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.put(route('tipos-carnet.update', tipo.id));
    }

    return (
        <LayoutPanel
            titulo="Editar tipo de carnet"
            descripcion={tipo.nombre}
            acciones={
                <Link href={route('tipos-carnet.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                    <ArrowLeft className="size-4" />
                    Volver a tipos de carnet
                </Link>
            }
        >
            <Head title="Editar tipo de carnet" />

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
                                    placeholder="Carnet de Pescador"
                                />
                            </Campo>

                            <Campo
                                etiqueta="Actividad"
                                htmlFor="tipo_actor"
                                error={form.errors.tipo_actor}
                                ayuda="Define en qué carnets se puede elegir este tipo."
                                obligatorio
                            >
                                <Select
                                    id="tipo_actor"
                                    value={form.data.tipo_actor}
                                    onChange={(e) => form.setData('tipo_actor', e.target.value as TipoActor)}
                                    aria-invalid={Boolean(form.errors.tipo_actor)}
                                >
                                    <option value="">Elija una actividad…</option>
                                    {actores.map((a) => (
                                        <option key={a.value} value={a.value}>
                                            {a.label}
                                        </option>
                                    ))}
                                </Select>
                            </Campo>

                            <Campo etiqueta="Vigente" htmlFor="estado" error={form.errors.estado}>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        id="estado"
                                        type="checkbox"
                                        checked={form.data.estado}
                                        onChange={(e) => form.setData('estado', e.target.checked)}
                                        className="size-4 rounded border-input"
                                    />
                                    Se puede elegir al emitir un carnet
                                </label>
                            </Campo>

                            <div className="sm:col-span-2">
                                <SelectorTarifaSireb
                                    tarifa={form.data.tarifa_sireb}
                                    tarifaGuardada={tipo.tarifa_sireb}
                                    servicioGuardado={tipo.servicio_sireb}
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
                                Guardar cambios
                            </Button>

                            <Link href={route('tipos-carnet.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                                Cancelar
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </LayoutPanel>
    );
}

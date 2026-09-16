import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import LayoutPanel from '@/layouts/layout-panel';
import type { OpcionEnum, PageProps } from '@/types';
import type { FormularioRubro, RubroFila } from '@/types/rubros';

/**
 * Alta y edición de un rubro del catálogo.
 *
 * Un solo componente para las dos cosas, por lo mismo que en beneficiarios: los
 * campos son idénticos y partirlo en dos archivos garantiza que algún día se
 * agregue uno solo en la mitad.
 */
export function FormularioRubroComponente({
    rubro,
    estados,
    tramitesAbiertos,
}: {
    rubro?: (Partial<RubroFila> & { id: number }) | null;
    estados: OpcionEnum[];
    /** Cuántos expedientes pendientes usan este rubro. Solo en edición. */
    tramitesAbiertos?: number;
}) {
    const editando = Boolean(rubro?.id);
    const { institucion } = usePage<PageProps>().props;

    const form = useForm<FormularioRubro>({
        nombre: rubro?.nombre ?? '',
        descripcion: rubro?.descripcion ?? '',
        costo: rubro?.costo !== undefined ? String(rubro.costo) : '',
        estado: rubro?.estado ?? 'activo',
        ...(editando ? { _method: 'put' as const } : {}),
    });

    function enviar(e: FormEvent) {
        e.preventDefault();

        form.post(editando ? route('rubros.update', rubro!.id) : route('rubros.store'));
    }

    return (
        <LayoutPanel
            titulo={editando ? 'Editar rubro' : 'Nuevo rubro'}
            descripcion="El catálogo de actividades que un carnet puede habilitar."
        >
            <form onSubmit={enviar} className="mx-auto max-w-2xl space-y-6">
                <Card>
                    <CardContent className="space-y-4 pt-5">
                        <Campo etiqueta="Nombre" htmlFor="nombre" obligatorio error={form.errors.nombre}>
                            <Input
                                id="nombre"
                                maxLength={50}
                                value={form.data.nombre}
                                onChange={(e) => form.setData('nombre', e.target.value)}
                                aria-invalid={Boolean(form.errors.nombre)}
                            />
                        </Campo>

                        <Campo etiqueta="Descripción" htmlFor="descripcion" error={form.errors.descripcion}>
                            <Textarea
                                id="descripcion"
                                rows={3}
                                value={form.data.descripcion}
                                onChange={(e) => form.setData('descripcion', e.target.value)}
                            />
                        </Campo>

                        <Campo
                            etiqueta={`Costo (${institucion.moneda})`}
                            htmlFor="costo"
                            obligatorio
                            error={form.errors.costo}
                            // Se permite 0: hay rubros exentos por ordenanza, y un
                            // trámite de costo cero se aprueba sin ningún depósito.
                            ayuda="Escriba 0 si el rubro es gratuito por ordenanza."
                        >
                            <Input
                                id="costo"
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.data.costo}
                                onChange={(e) => form.setData('costo', e.target.value)}
                                aria-invalid={Boolean(form.errors.costo)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Estado"
                            htmlFor="estado"
                            obligatorio
                            error={form.errors.estado}
                            ayuda="Un rubro inactivo no se puede solicitar, pero sigue visible en los carnets que ya lo tienen."
                        >
                            <Select
                                id="estado"
                                value={form.data.estado}
                                onChange={(e) =>
                                    form.setData('estado', e.target.value as FormularioRubro['estado'])
                                }
                            >
                                {estados.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </Select>
                        </Campo>
                    </CardContent>
                </Card>

                {/*
                    EL AVISO QUE EVITA UNA LLAMADA A SOPORTE.
                    Sin él, la primera reacción al ver que un trámite viejo sigue
                    con el precio anterior es pensar que el sistema falló.
                */}
                {editando && (tramitesAbiertos ?? 0) > 0 && (
                    <Card className="border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
                        <CardContent className="pt-5 text-sm text-amber-900 dark:text-amber-200">
                            Hay {tramitesAbiertos} trámite(s) pendiente(s) con este rubro. Cambiar el
                            costo <strong>no los modifica</strong>: cada expediente conserva el monto que
                            se le copió el día que se presentó.
                        </CardContent>
                    </Card>
                )}

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Guardando…' : editando ? 'Guardar cambios' : 'Crear rubro'}
                    </Button>
                </div>
            </form>
        </LayoutPanel>
    );
}

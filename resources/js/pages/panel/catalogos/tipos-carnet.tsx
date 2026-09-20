import { Head, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Tags, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { TipoCarnetFila } from '@/types/catalogos';

/**
 *  CATÁLOGO DE TIPOS DE CARNET
 */
export default function CatalogoTiposCarnet({ tipos }: { tipos: TipoCarnetFila[] }) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [editando, setEditando] = useState<TipoCarnetFila | 'nuevo' | null>(null);

    return (
        <LayoutPanel
            titulo="Tipos de carnet"
            descripcion="El nombre de cada credencial y su arancel."
            acciones={
                puede('catalogos.gestionar') && (
                    <Button onClick={() => setEditando('nuevo')}>
                        <Plus className="size-4" />
                        Nuevo tipo
                    </Button>
                )
            }
        >
            <Head title="Tipos de carnet" />

            <div className="space-y-6">
                {editando !== null && puede('catalogos.gestionar') && (
                    <FormularioTipo
                        // Ver el comentario de la `key` en asociaciones.tsx.
                        key={editando === 'nuevo' ? 'nuevo' : editando.id}
                        tipo={editando === 'nuevo' ? null : editando}
                        onCerrar={() => setEditando(null)}
                    />
                )}

                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle>Registrados</CardTitle>
                    </CardHeader>

                    <CardContent className="p-0">
                        {tipos.length === 0 ? (
                            <EstadoVacio
                                icono={Tags}
                                titulo="Sin tipos de carnet"
                                descripcion="Cargue al menos uno: sin tipo no se puede emitir una credencial."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">Tipo</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Arancel</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Emitidos</th>
                                            <th className="px-5 py-2.5" />
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {tipos.map((t) => (
                                            <tr key={t.id} className="hover:bg-secondary/50">
                                                <td className="px-5 py-2.5">
                                                    <span className="font-medium">{t.nombre}</span>
                                                    {!t.estado && (
                                                        <Badge color="slate" className="ml-2">
                                                            Fuera de uso
                                                        </Badge>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {bs(t.precio_bs, institucion.moneda)}
                                                </td>

                                                {/*
                                                    Es lo que explica por qué no hay papelera: con
                                                    carnets emitidos colgando, borrar la fila los
                                                    dejaría sin tipo.
                                                */}
                                                <td className="px-5 py-2.5 text-right tabular-nums text-muted-foreground">
                                                    {t.carnets_count || '—'}
                                                </td>

                                                <td className="px-5 py-2.5 text-right">
                                                    {puede('catalogos.gestionar') && (
                                                        <Button
                                                            variant="editar"
                                                            size="sm"
                                                            title="Editar"
                                                            onClick={() => setEditando(t)}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

            </div>
        </LayoutPanel>
    );
}

/** Alta y edición de un tipo. El mismo formulario para los dos casos. */
function FormularioTipo({ tipo, onCerrar }: { tipo: TipoCarnetFila | null; onCerrar: () => void }) {
    const esAlta = tipo === null;

    const form = useForm({
        nombre: tipo?.nombre ?? '',
        precio_bs: tipo?.precio_bs ?? '',
        estado: tipo?.estado ?? true,
    });

    function enviar(e: FormEvent) {
        e.preventDefault();

        const opciones = { preserveScroll: true, onSuccess: () => onCerrar() };

        if (esAlta) {
            form.post(route('tipos-carnet.store'), opciones);
        } else {
            form.put(route('tipos-carnet.update', tipo.id), opciones);
        }
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                <CardTitle>{esAlta ? 'Nuevo tipo' : 'Editar tipo'}</CardTitle>

                <Button variant="ghost" size="sm" onClick={onCerrar} aria-label="Cerrar">
                    <X className="size-4" />
                </Button>
            </CardHeader>

            <CardContent>
                <form onSubmit={enviar} className="space-y-4">
                    {/* A lo ancho los tres campos entran en una fila. */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
                        etiqueta="Arancel (Bs)"
                        htmlFor="precio_bs"
                        error={form.errors.precio_bs}
                        ayuda="Cambiarlo NO toca lo ya cobrado, pero sí el saldo de los carnets que aún no están pagados."
                        obligatorio
                    >
                        <Input
                            id="precio_bs"
                            type="number"
                            step="0.01"
                            min={0}
                            value={form.data.precio_bs}
                            onChange={(e) => form.setData('precio_bs', e.target.value)}
                            aria-invalid={Boolean(form.errors.precio_bs)}
                        />
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
                    </div>

                    <div className="flex gap-2 pt-1">
                        <Button type="submit" disabled={form.processing}>
                            {esAlta ? 'Registrar' : 'Guardar cambios'}
                        </Button>

                        <Button type="button" variant="outline" onClick={onCerrar}>
                            Cancelar
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

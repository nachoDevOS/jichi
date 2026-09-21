import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Search, Tags, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { OpcionEnum, PageProps, Paginado, TipoActor } from '@/types';
import type { TipoCarnetFila } from '@/types/catalogos';

/**
 *  CATÁLOGO DE TIPOS DE CARNET
 */
export default function CatalogoTiposCarnet({
    tipos,
    filtros,
    actores,
    opcionesPorPagina,
}: {
    tipos: Paginado<TipoCarnetFila>;
    filtros: { buscar: string | null; actor: string | null; por_pagina: number };
    actores: OpcionEnum[];
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    /*
     * Solo EDICIÓN: los tipos de carnet salen de la resolución, así que el
     * catálogo se corrige pero no se le agregan filas desde la pantalla.
     */
    const [editando, setEditando] = useState<TipoCarnetFila | null>(null);

    function filtrar(valores: Record<string, string | number | null> = {}) {
        router.get(
            route('tipos-carnet.index'),
            { buscar, actor: filtros.actor, ...valores },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Tipos de carnet"
            descripcion="El nombre de cada credencial y su arancel. Se corrigen; no se agregan."
        >
            <Head title="Tipos de carnet" />

            <div className="space-y-6">
                {editando !== null && puede('catalogos.gestionar') && (
                    <FormularioTipo
                        // Ver el comentario de la `key` en asociaciones.tsx.
                        key={editando.id}
                        tipo={editando}
                        actores={actores}
                        onCerrar={() => setEditando(null)}
                    />
                )}

                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle>Registrados</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4 p-0">
                        {/*
                            LA BARRA DE ARRIBA DE LA TABLA, la misma del padrón
                            de beneficiarios: «Mostrar N» a la izquierda y los
                            filtros pegados al buscador a la derecha.
                        */}
                        <div className="grid grid-cols-1 gap-3 border-y border-border p-4 sm:grid-cols-12 sm:items-end">
                            <label className="flex items-center gap-2 text-sm text-muted-foreground sm:col-span-4">
                                Mostrar
                                <Select
                                    className="w-auto"
                                    value={filtros.por_pagina}
                                    onChange={(e) => filtrar({ por_pagina: Number(e.target.value) })}
                                    aria-label="Registros por página"
                                >
                                    {opcionesPorPagina.map((n) => (
                                        <option key={n} value={n}>
                                            {n}
                                        </option>
                                    ))}
                                </Select>
                                registros
                            </label>

                            <div className="flex flex-wrap items-center justify-end gap-2 sm:col-span-8">
                                <Select
                                    className="w-auto min-w-36"
                                    value={filtros.actor ?? ''}
                                    onChange={(e) => filtrar({ actor: e.target.value || null })}
                                    aria-label="Filtrar por actividad"
                                >
                                    <option value="">Toda actividad</option>
                                    {actores.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))}
                                </Select>

                                {/* El buscador es un <form> propio para que el
                                    Enter lo envíe: no busca al teclear. */}
                                <form
                                    className="relative min-w-48 flex-1 sm:max-w-sm"
                                    onSubmit={(e: FormEvent) => {
                                        e.preventDefault();
                                        filtrar();
                                    }}
                                >
                                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        className="pl-9"
                                        placeholder="Buscar…"
                                        value={buscar}
                                        onChange={(e) => setBuscar(e.target.value)}
                                        aria-label="Buscar por nombre del tipo"
                                    />
                                </form>
                            </div>
                        </div>

                        {tipos.data.length === 0 ? (
                            <EstadoVacio
                                icono={Tags}
                                titulo="Sin tipos de carnet"
                                descripcion={
                                    filtros.buscar || filtros.actor
                                        ? 'Ninguno coincide con los filtros.'
                                        : 'El catálogo está vacío: sin tipo no se puede emitir una credencial.'
                                }
                            />
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-5 py-2.5 font-medium">Tipo</th>
                                                <th className="px-5 py-2.5 font-medium">Actividad</th>
                                                <th className="px-5 py-2.5 text-right font-medium">Arancel</th>
                                                <th className="px-5 py-2.5 text-right font-medium">Emitidos</th>
                                                <th className="px-5 py-2.5" />
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {tipos.data.map((t) => (
                                                <tr key={t.id} className="hover:bg-secondary/50">
                                                    <td className="px-5 py-2.5">
                                                        <span className="font-medium">{t.nombre}</span>
                                                        {!t.estado && (
                                                            <Badge color="slate" className="ml-2">
                                                                Fuera de uso
                                                            </Badge>
                                                        )}
                                                    </td>

                                                    {/* Es lo que decide en qué carnets se puede
                                                        elegir: un tipo de comercializador no aparece
                                                        emitiendo uno de pescador. */}
                                                    <td className="px-5 py-2.5">
                                                        <Badge color={t.tipo_actor_color}>
                                                            {t.tipo_actor_etiqueta}
                                                        </Badge>
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

                                <Paginacion paginado={tipos} />
                            </>
                        )}
                    </CardContent>
                </Card>

            </div>
        </LayoutPanel>
    );
}

/** CORRECCIÓN de un tipo. No hay alta: el catálogo sale de la resolución. */
function FormularioTipo({
    tipo,
    actores,
    onCerrar,
}: {
    tipo: TipoCarnetFila;
    actores: OpcionEnum[];
    onCerrar: () => void;
}) {
    const form = useForm({
        nombre: tipo.nombre,
        tipo_actor: tipo.tipo_actor,
        precio_bs: String(tipo.precio_bs),
        estado: tipo.estado,
    });

    function enviar(e: FormEvent) {
        e.preventDefault();

        form.put(route('tipos-carnet.update', tipo.id), {
            preserveScroll: true,
            onSuccess: () => onCerrar(),
        });
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                <CardTitle>Editar tipo</CardTitle>

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
                            Guardar cambios
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

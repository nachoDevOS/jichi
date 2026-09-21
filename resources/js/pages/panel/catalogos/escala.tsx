import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Ruler, Search, TriangleAlert, X } from 'lucide-react';
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
import type { ModalidadAprovechamiento, OpcionEnum, PageProps, Paginado } from '@/types';
import type { EscalaFila, HuecoEscala } from '@/types/catalogos';

/**
 *  LA ESCALA OFICIAL DE APROVECHAMIENTO
 */
export default function CatalogoEscala({
    escala,
    filtros,
    huecos,
    siguienteNumero,
    modalidades,
    opcionesPorPagina,
}: {
    escala: Paginado<EscalaFila>;
    filtros: { buscar: string | null; modalidad: string | null; por_pagina: number };
    huecos: HuecoEscala[];
    /** El número que sigue, calculado sobre TODOS los tramos y no sobre la página. */
    siguienteNumero: number;
    /** Las opciones salen del enum de PHP: escritas acá se desincronizan. */
    modalidades: OpcionEnum[];
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const [editando, setEditando] = useState<EscalaFila | 'nueva' | null>(null);

    function filtrar(valores: Record<string, string | number | null> = {}) {
        router.get(
            route('categorias-aprovechamiento.index'),
            { buscar, modalidad: filtros.modalidad, ...valores },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Escala de aprovechamiento"
            descripcion="Los tramos oficiales: a tantos kilos autorizados, tantos bolivianos."
            acciones={
                puede('catalogos.gestionar') && (
                    <Button onClick={() => setEditando('nueva')}>
                        <Plus className="size-4" />
                        Nuevo tramo
                    </Button>
                )
            }
        >
            <Head title="Escala de aprovechamiento" />

            <div className="space-y-6">
                {huecos.length > 0 && <AvisoHuecos huecos={huecos} />}

                {editando !== null && puede('catalogos.gestionar') && (
                    <FormularioTramo
                        // Ver el comentario de la `key` en asociaciones.tsx:
                        // sin ella el useForm conservaría los valores del
                        // tramo anterior al cambiar de fila.
                        key={editando === 'nueva' ? 'nueva' : editando.id}
                        tramo={editando === 'nueva' ? null : editando}
                        modalidades={modalidades}
                        siguienteNumero={siguienteNumero}
                        onCerrar={() => setEditando(null)}
                    />
                )}

                <Card className="min-w-0">
                        <CardHeader>
                            <CardTitle>Tramos</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-4 p-0">
                            {/*
                                LA BARRA DE ARRIBA DE LA TABLA, la misma del
                                padrón de beneficiarios: «Mostrar N» a la
                                izquierda y los filtros pegados al buscador.
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
                                        value={filtros.modalidad ?? ''}
                                        onChange={(e) => filtrar({ modalidad: e.target.value || null })}
                                        aria-label="Filtrar por modalidad"
                                    >
                                        <option value="">Toda modalidad</option>
                                        {modalidades.map((o) => (
                                            <option key={o.value} value={o.value}>
                                                {o.label}
                                            </option>
                                        ))}
                                    </Select>

                                    {/* El buscador es un <form> propio para que
                                        el Enter lo envíe: no busca al teclear. */}
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
                                            aria-label="Buscar por tramo de kilos"
                                        />
                                    </form>
                                </div>
                            </div>

                            {escala.data.length === 0 ? (
                                <EstadoVacio
                                    icono={Ruler}
                                    titulo="Sin tramos cargados"
                                    descripcion={
                                        filtros.buscar || filtros.modalidad
                                            ? 'Ninguno coincide con los filtros.'
                                            : 'Cargue la escala de la resolución: sin ella no se puede otorgar ningún cupo de pesca.'
                                    }
                                />
                            ) : (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                                <tr>
                                                    <th className="px-5 py-2.5 font-medium">N°</th>
                                                    <th className="px-5 py-2.5 font-medium">Rango</th>
                                                    <th className="px-5 py-2.5 font-medium">Régimen</th>
                                                    <th className="px-5 py-2.5 text-right font-medium">Valor</th>
                                                    <th className="px-5 py-2.5 text-right font-medium">Otorgados</th>
                                                    <th className="px-5 py-2.5" />
                                                </tr>
                                            </thead>

                                            <tbody className="divide-y divide-border">
                                                {escala.data.map((t) => (
                                                    <tr key={t.id} className="hover:bg-secondary/50">
                                                        <td className="px-5 py-2.5 tabular-nums">
                                                            <span className="font-semibold">{t.nro_escala}</span>
                                                            {!t.estado && (
                                                                <Badge color="slate" className="ml-2">
                                                                    Derogado
                                                                </Badge>
                                                            )}
                                                        </td>

                                                        <td className="px-5 py-2.5">
                                                            {/*
                                                                El TEXTO OFICIAL primero y los números
                                                                debajo: no siempre dicen lo mismo —el
                                                                tramo más alto agrega «PAICHE»— y lo que
                                                                se imprime es el texto.
                                                            */}
                                                            <p>{t.descripcion_kg}</p>
                                                            <p className="text-xs tabular-nums text-muted-foreground">
                                                                {t.kilos_min} – {t.kilos_max} kg
                                                            </p>
                                                        </td>

                                                        {/*
                                                            EL RÉGIMEN VA EN SU PROPIA COLUMNA porque no
                                                            se lee en ningún número: el tramo del paiche
                                                            se ve igual que los otros seis, y su valor
                                                            no sale de la progresión por kilos.
                                                        */}
                                                        <td className="px-5 py-2.5">
                                                            <Badge color={t.modalidad_color}>
                                                                {t.modalidad_etiqueta}
                                                            </Badge>

                                                        </td>

                                                        <td className="px-5 py-2.5 text-right tabular-nums">
                                                            {bs(t.valor_bs, institucion.moneda)}
                                                        </td>

                                                        <td className="px-5 py-2.5 text-right tabular-nums text-muted-foreground">
                                                            {t.aprovechamientos_count || '—'}
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

                                    <Paginacion paginado={escala} />
                                </>
                            )}
                        </CardContent>
                    </Card>

            </div>
        </LayoutPanel>
    );
}

/**
 * El aviso de rangos sin cubrir.
 */
function AvisoHuecos({ huecos }: { huecos: HuecoEscala[] }) {
    return (
        <Card className="border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10">
            <CardContent className="flex items-start gap-3 pt-5">
                <TriangleAlert className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />

                <div className="min-w-0 text-sm">
                    <p className="font-medium text-amber-900 dark:text-amber-200">
                        La escala tiene {huecos.length} hueco(s)
                    </p>

                    <p className="text-amber-800/80 dark:text-amber-200/80">
                        Un cupo que caiga en estos rangos no va a encontrar ninguna escala, y el
                        formulario de aprovechamiento no va a ofrecer nada — sin ningún error que lo
                        explique.
                    </p>

                    <ul className="mt-2 flex flex-wrap gap-1">
                        {huecos.map((h) => (
                            <li key={`${h.desde}-${h.hasta}`}>
                                <Badge color="amber">
                                    {h.desde} – {h.hasta} kg
                                </Badge>
                            </li>
                        ))}
                    </ul>
                </div>
            </CardContent>
        </Card>
    );
}

/** Alta y edición de un tramo. El mismo formulario para los dos casos. */
function FormularioTramo({
    tramo,
    modalidades,
    siguienteNumero,
    onCerrar,
}: {
    tramo: EscalaFila | null;
    modalidades: OpcionEnum[];
    /** Para proponer el número en un alta, sin que el operador tenga que contarlos. */
    siguienteNumero: number;
    onCerrar: () => void;
}) {
    const esAlta = tramo === null;

    const form = useForm({
        // Va como TEXTO y no como número: es lo que devuelve un <input>, y
        // useForm fija el tipo con el valor inicial. Arrancando en number, el
        // primer tecleo no compila.
        nro_escala: String(tramo?.nro_escala ?? siguienteNumero),
        // Escala general por defecto: son seis de siete tramos, y el régimen
        // especial es la excepción que se marca a propósito.
        modalidad: tramo?.modalidad ?? ('escala_general' as ModalidadAprovechamiento),
        descripcion_kg: tramo?.descripcion_kg ?? '',
        kilos_min: tramo?.kilos_min ?? '',
        kilos_max: tramo?.kilos_max ?? '',
        valor_bs: tramo?.valor_bs ?? '',
        estado: tramo?.estado ?? true,
    });

    function enviar(e: FormEvent) {
        e.preventDefault();

        const opciones = { preserveScroll: true, onSuccess: () => onCerrar() };

        if (esAlta) {
            form.post(route('categorias-aprovechamiento.store'), opciones);
        } else {
            form.put(route('categorias-aprovechamiento.update', tramo.id), opciones);
        }
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                <CardTitle>{esAlta ? 'Nuevo tramo' : `Editar escala ${tramo.nro_escala}`}</CardTitle>

                <Button variant="ghost" size="sm" onClick={onCerrar} aria-label="Cerrar">
                    <X className="size-4" />
                </Button>
            </CardHeader>

            <CardContent>
                <form onSubmit={enviar} className="space-y-4">
                    {/* A lo ancho los seis campos entran en dos filas. */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Campo etiqueta="N° de escala" htmlFor="nro_escala" error={form.errors.nro_escala} obligatorio>
                        <Input
                            id="nro_escala"
                            type="number"
                            min={1}
                            value={form.data.nro_escala}
                            onChange={(e) => form.setData('nro_escala', e.target.value)}
                            aria-invalid={Boolean(form.errors.nro_escala)}
                        />
                    </Campo>

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
                            onChange={(e) =>
                                form.setData('modalidad', e.target.value as ModalidadAprovechamiento)
                            }
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

                    <Campo
                        etiqueta="Valor (Bs)"
                        htmlFor="valor_bs"
                        error={form.errors.valor_bs}
                        ayuda="Lo que se cobra por este cupo. Se copia al otorgarlo, así que cambiarlo no toca los ya otorgados."
                        obligatorio
                    >
                        <Input
                            id="valor_bs"
                            type="number"
                            step="0.01"
                            min={0}
                            value={form.data.valor_bs}
                            onChange={(e) => form.setData('valor_bs', e.target.value)}
                            aria-invalid={Boolean(form.errors.valor_bs)}
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
                            Se puede elegir al otorgar un cupo
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

import { Head, Link, router } from '@inertiajs/react';
import { BadgeCheck, Search } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { fecha } from '@/lib/utils';
import type { OpcionEnum, Paginado } from '@/types';
import type { CarnetFila } from '@/types/carnets';

/**
 * Los carnets emitidos.
 *
 * NO HAY BOTÓN DE «NUEVO CARNET», y es a propósito: un carnet nace dentro del
 * trámite, cuando el sistema detecta que la persona no tenía uno de esta
 * gestión. Un alta suelta permitiría emitir documentos sin expediente que los
 * respalde, y sin cobrar.
 */
export default function IndiceCarnets({
    carnets,
    filtros,
    estados,
    gestiones,
    rubros,
    opcionesPorPagina,
}: {
    carnets: Paginado<CarnetFila>;
    filtros: {
        buscar: string | null;
        estado: string | null;
        gestion: number | null;
        /** Con un carnet por actividad, «mostrame los de Pescador» es corriente. */
        rubro: number | null;
        por_pagina: number;
    };
    estados: OpcionEnum[];
    gestiones: number[];
    /** Todos, incluso los dados de baja: sus carnets emitidos siguen existiendo. */
    rubros: { id: number; nombre: string }[];
    opcionesPorPagina: number[];
}) {
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null>) {
        router.get(
            route('carnets.index'),
            {
                buscar,
                estado: filtros.estado,
                gestion: filtros.gestion,
                rubro: filtros.rubro,
                por_pagina: filtros.por_pagina,
                ...valores,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel titulo="Carnets" descripcion="Documentos emitidos, uno por persona, rubro y gestión.">
            <Head title="Carnets" />

            <Card>
                {/* La misma barra de todos los listados: «Mostrar N» a la
                    izquierda, buscador a la derecha en cuatro columnas. */}
                <div className="grid grid-cols-1 gap-3 border-b border-border p-4 sm:grid-cols-12 sm:items-end">
                    <label className="flex items-center gap-2 text-sm text-muted-foreground sm:col-span-3">
                        Mostrar
                        <Select
                            className="w-auto"
                            value={filtros.por_pagina}
                            onChange={(e) => filtrar({ por_pagina: e.target.value })}
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

                    <Select
                        className="sm:col-span-3"
                        value={filtros.estado ?? ''}
                        onChange={(e) => filtrar({ estado: e.target.value || null })}
                        aria-label="Estado"
                    >
                        <option value="">Todos los estados</option>
                        {estados.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </Select>

                    <Select
                        className="sm:col-span-2"
                        value={filtros.gestion ?? ''}
                        onChange={(e) => filtrar({ gestion: e.target.value || null })}
                        aria-label="Gestión"
                    >
                        <option value="">Todas las gestiones</option>
                        {gestiones.map((g) => (
                            <option key={g} value={g}>
                                {g}
                            </option>
                        ))}
                    </Select>

                    {/*
                        FILTRO POR ACTIVIDAD. Llegó con el modelo nuevo: antes un
                        carnet tenía varios rubros y filtrar por uno devolvía
                        documentos que además habilitaban otras tres cosas. Hoy el
                        carnet ES la actividad, así que la lista filtrada responde
                        exactamente «quiénes están habilitados para pescar».
                    */}
                    <Select
                        className="sm:col-span-2"
                        value={filtros.rubro ?? ''}
                        onChange={(e) => filtrar({ rubro: e.target.value || null })}
                        aria-label="Rubro"
                    >
                        <option value="">Todos los rubros</option>
                        {rubros.map((r) => (
                            <option key={r.id} value={r.id}>
                                {r.nombre}
                            </option>
                        ))}
                    </Select>

                    <form
                        className="relative sm:col-span-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            filtrar({});
                        }}
                    >
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="pl-9"
                            placeholder="Buscar…"
                            value={buscar}
                            onChange={(e) => setBuscar(e.target.value)}
                            aria-label="Buscar por número de registro, firma o titular"
                        />
                    </form>
                </div>

                {carnets.data.length === 0 ? (
                    <EstadoVacio
                        icono={BadgeCheck}
                        titulo="Sin carnets"
                        descripcion="Los carnets se emiten al registrar el primer trámite de cada persona en la gestión."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3 font-medium">Registro</th>
                                    <th className="px-5 py-3 font-medium">Titular</th>
                                    <th className="px-5 py-3 font-medium">Gestión</th>
                                    <th className="px-5 py-3 font-medium">Estado</th>
                                    <th className="px-5 py-3 font-medium">Rubro</th>
                                    <th className="px-5 py-3 font-medium">Vence</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border">
                                {carnets.data.map((c) => (
                                    <tr key={c.id} className="hover:bg-secondary/50">
                                        <td className="px-5 py-3">
                                            {/* font-mono y tabular-nums: el
                                                registro se dicta dígito por dígito
                                                y se compara entre filas, y en
                                                monoespaciado los ceros de relleno
                                                quedan alineados en la columna. */}
                                            <Link
                                                href={route('carnets.show', c.id)}
                                                className="font-mono text-sm font-medium tabular-nums text-primary hover:underline"
                                            >
                                                {c.registro}
                                            </Link>
                                        </td>
                                        <td className="px-5 py-3">{c.beneficiario ?? '—'}</td>
                                        <td className="px-5 py-3 tabular-nums">{c.gestion}</td>
                                        <td className="px-5 py-3">
                                            {/*
                                                Se muestra el estado guardado, pero si la fecha ya
                                                pasó y el comando programado todavía no corrió, se
                                                avisa: el estado puede estar desfasado hasta un día.
                                            */}
                                            <Badge color={c.vigente ? 'emerald' : c.estado_color}>
                                                {c.vigente ? 'Vigente' : c.estado_etiqueta}
                                            </Badge>
                                        </td>
                                        {/*
                                            LA ACTIVIDAD, no un contador de rubros.
                                            Con un carnet por rubro, «cuántos rubros
                                            tiene» siempre daría uno; lo que hace
                                            falta saber es CUÁL, porque una misma
                                            persona aparece varias veces en la lista.
                                        */}
                                        <td className="px-5 py-3">
                                            <span className="font-medium">{c.rubro ?? '—'}</span>
                                            {c.capacidad && (
                                                <p className="text-xs text-muted-foreground">
                                                    {c.capacidad}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-5 py-3 text-muted-foreground">
                                            {fecha(c.fecha_vencimiento)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Paginacion paginado={carnets} />
            </Card>
        </LayoutPanel>
    );
}

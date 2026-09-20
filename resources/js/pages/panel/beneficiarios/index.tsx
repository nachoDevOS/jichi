import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Search, Users } from 'lucide-react';
import { useState } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { fecha } from '@/lib/utils';
import type { Paginado } from '@/types';
import type { BeneficiarioFila } from '@/types/beneficiarios';

/**
 * El padrón de beneficiarios.
 */
export default function IndiceBeneficiarios({
    beneficiarios,
    filtros,
    opcionesPorPagina,
}: {
    beneficiarios: Paginado<BeneficiarioFila>;
    filtros: { buscar: string | null; por_pagina: number };
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();

    // El buscador se escribe acá y se manda al servidor al enviar el formulario.
    // No busca mientras se teclea a propósito: cada tecla sería una consulta
    // que recorre la tabla entera (ver Beneficiario::scopeBuscar).
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null>) {
        router.get(route('beneficiarios.index'), { buscar, ...valores }, {
            preserveState: true,
            preserveScroll: true,
            // replace evita llenar el historial del navegador con una entrada
            // por cada búsqueda: el botón «atrás» debe volver a la pantalla
            // anterior, no a la búsqueda anterior.
            replace: true,
        });
    }

    return (
        <LayoutPanel
            titulo="Beneficiarios"
            descripcion="Padrón de personas que tramitan carnet."
            acciones={
                puede('beneficiarios.crear') && (
                    <Button onClick={() => router.visit(route('beneficiarios.create'))}>
                        <Plus className="size-4" />
                        Nuevo beneficiario
                    </Button>
                )
            }
        >
            <Head title="Beneficiarios" />

            <Card>
                {/*
                    LA BARRA DE ARRIBA DE LA TABLA: «Mostrar N» a la izquierda y
                    el buscador a la derecha, ocupando cuatro de las doce
                    columnas.
                */}
                <div className="grid grid-cols-1 gap-3 border-b border-border p-4 sm:grid-cols-12 sm:items-end">
                    <label className="flex items-center gap-2 text-sm text-muted-foreground sm:col-span-4">
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

                    {/*
                        El buscador es un <form> propio para que el Enter lo
                        envíe. No busca mientras se teclea a propósito: cada
                        tecla sería una consulta que recorre la tabla entera,
                        porque el nombre se compara concatenado y ningún índice
                        puede ayudar.
                    */}
                    <form
                        className="relative sm:col-span-4 sm:col-start-9"
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
                            aria-label="Buscar por cédula, nombre, teléfono o correo"
                        />
                    </form>
                </div>

                {beneficiarios.data.length === 0 ? (
                    <EstadoVacio
                        icono={Users}
                        titulo={filtros.buscar ? 'Sin coincidencias' : 'El padrón está vacío'}
                        descripcion={
                            filtros.buscar
                                ? `No se encontró a nadie con «${filtros.buscar}».`
                                : 'Registre al primer beneficiario para poder iniciar trámites.'
                        }
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3 font-medium">ID</th>
                                    <th className="px-5 py-3 font-medium">Beneficiario</th>
                                    <th className="px-5 py-3 font-medium">Género</th>
                                    <th className="px-5 py-3 font-medium">Celular</th>
                                    <th className="px-5 py-3 font-medium">Nacimiento</th>
                                    <th className="px-5 py-3 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border">
                                {beneficiarios.data.map((b) => (
                                    <tr key={b.id} className="hover:bg-secondary/50">
                                        {/* --- ID */}
                                        <td className="px-5 py-3 tabular-nums text-muted-foreground">
                                            {b.id}
                                        </td>

                                        {/* --- Foto, nombre y cédula, juntos */}
                                        <td className="px-5 py-3">
                                            <div className="flex items-center gap-3">
                                                <Retrato url={b.foto_url} nombre={b.nombreCompleto} />

                                                <div className="min-w-0">
                                                    <Link
                                                        href={route('beneficiarios.show', b.id)}
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {b.nombreCompleto}
                                                    </Link>
                                                    {/* tabular-nums: los dígitos ocupan lo mismo y
                                                        las cédulas quedan alineadas entre filas. */}
                                                    <p className="tabular-nums text-xs text-muted-foreground">
                                                        {b.documento_identidad}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        {/* --- Género */}
                                        <td className="px-5 py-3">
                                            {/* capitalize: en la base se guarda en minúscula
                                                ('femenino') porque así lo valida el enum del
                                                formulario, pero en pantalla se lee mejor con
                                                mayúscula inicial. Se resuelve con CSS y no
                                                cambiando el dato guardado. */}
                                            <span className="capitalize">{b.genero ?? '—'}</span>
                                        </td>

                                        {/* --- Celular */}
                                        <td className="px-5 py-3 tabular-nums text-muted-foreground">
                                            {b.telefono ?? '—'}
                                        </td>

                                        {/* --- Nacimiento, con la edad debajo */}
                                        <td className="px-5 py-3">
                                            <p className="tabular-nums">{fecha(b.fechaNacimiento)}</p>

                                            {/*
                                                La edad acompaña a la fecha en la misma celda y no
                                                en una columna propia: es una LECTURA de ese dato,
                                                no un dato aparte. En una columna suelta, el
                                                operador tendría que cruzar la vista entre dos
                                                lugares de la tabla para entender un solo hecho.
                                            */}
                                            {b.edad !== null && (
                                                <p className="text-xs text-muted-foreground">
                                                    {b.edad} años
                                                </p>
                                            )}
                                        </td>

                                        {/* --- Ver y editar */}
                                        <td className="px-5 py-3">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ver"
                                                    size="sm"
                                                    title="Ver ficha"
                                                    onClick={() =>
                                                        router.visit(route('beneficiarios.show', b.id))
                                                    }
                                                >
                                                    <Eye className="size-4" />
                                                    Ver
                                                </Button>

                                                {/*
                                                    El botón de editar solo aparece si el usuario
                                                    puede. Es comodidad, NO seguridad: quien
                                                    realmente bloquea es el middleware `permiso:`
                                                    de routes/panel.php, porque cualquiera puede
                                                    escribir la URL a mano.
                                                */}
                                                {puede('beneficiarios.editar') && (
                                                    <Button
                                                        variant="editar"
                                                        size="sm"
                                                        title="Editar ficha"
                                                        onClick={() =>
                                                            router.visit(route('beneficiarios.edit', b.id))
                                                        }
                                                    >
                                                        <Pencil className="size-4" />
                                                        Editar
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Paginacion paginado={beneficiarios} />
            </Card>
        </LayoutPanel>
    );
}

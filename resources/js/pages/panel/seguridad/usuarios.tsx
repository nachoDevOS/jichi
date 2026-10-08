import { Head, router } from '@inertiajs/react';
import { Eye, Search, UserCog } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { EnlacePermitido } from '@/components/panel/comunes/enlace-permitido';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { cn, fechaHora, hace } from '@/lib/utils';
import type { OpcionEnum, Paginado } from '@/types';
import type { UsuarioFila } from '@/types/seguridad';

/**
 *  Usuarios — todas las cuentas: funcionarios y beneficiarios
 */
export default function Usuarios({
    usuarios,
    filtros,
    opcionesPorPagina,
    tipos,
    estados,
}: {
    usuarios: Paginado<UsuarioFila>;
    filtros: { buscar: string | null; tipo: string | null; estado: string | null; por_pagina: number };
    opcionesPorPagina: number[];
    tipos: Omit<OpcionEnum, 'color'>[];
    estados: Omit<OpcionEnum, 'color'>[];
}) {
    const { puede } = usePermisos();
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    function filtrar(valores: Record<string, string | number | null> = {}) {
        router.get(
            route('usuarios.index'),
            { buscar, tipo: filtros.tipo, estado: filtros.estado, ...valores },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Usuarios"
            descripcion="Las cuentas de acceso al sistema: funcionarios y beneficiarios. Al beneficiario se le da, resetea y desactiva el acceso desde su ficha."
        >
            <Head title="Usuarios" />

            <div className="space-y-6">
                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle>Registrados</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4 p-0">
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
                                    value={filtros.tipo ?? ''}
                                    onChange={(e) => filtrar({ tipo: e.target.value || null })}
                                    aria-label="Filtrar por tipo de usuario"
                                >
                                    <option value="">Todos</option>
                                    {tipos.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))}
                                </Select>

                                <Select
                                    className="w-auto min-w-40"
                                    value={filtros.estado ?? ''}
                                    onChange={(e) => filtrar({ estado: e.target.value || null })}
                                    aria-label="Filtrar por estado"
                                >
                                    <option value="">Todos los estados</option>
                                    {estados.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))}
                                </Select>

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
                                        placeholder="Buscar por nombre, C.I. o correo…"
                                        value={buscar}
                                        onChange={(e) => setBuscar(e.target.value)}
                                        aria-label="Buscar por nombre, C.I. o correo"
                                    />
                                </form>
                            </div>
                        </div>

                        {usuarios.data.length === 0 ? (
                            <EstadoVacio
                                icono={UserCog}
                                titulo="Sin usuarios"
                                descripcion={
                                    filtros.buscar || filtros.estado
                                        ? 'Ninguno coincide con los filtros.'
                                        : 'Todavía no hay cuentas de acceso.'
                                }
                            />
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-5 py-2.5 font-medium">Usuario</th>
                                                <th className="px-5 py-2.5 font-medium">Rol</th>
                                                <th className="px-5 py-2.5 font-medium">Estado</th>
                                                <th className="px-5 py-2.5 font-medium">Último ingreso</th>
                                                <th className="px-5 py-2.5 font-medium">Cuenta desde</th>
                                                <th className="px-5 py-2.5" />
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {usuarios.data.map((c) => (
                                                <tr key={c.id} className="hover:bg-secondary/50">
                                                    <td className="px-5 py-2.5">
                                                        <div className="flex items-center gap-3">
                                                            <Retrato url={c.foto_url} nombre={c.nombre} className="size-9" />
                                                            <div className="min-w-0">
                                                                {c.beneficiario_id !== null ? (
                                                                    <EnlacePermitido
                                                                        permiso="beneficiarios.ver"
                                                                        href={route('beneficiarios.show', c.beneficiario_id)}
                                                                        className="font-medium text-primary hover:underline"
                                                                    >
                                                                        {c.nombre}
                                                                    </EnlacePermitido>
                                                                ) : (
                                                                    <span className="font-medium">{c.nombre}</span>
                                                                )}
                                                                <p className="text-xs tabular-nums text-muted-foreground">{c.detalle ?? '—'}</p>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="px-5 py-2.5">
                                                        <Badge color={c.beneficiario_id !== null ? 'sky' : 'indigo'} className="whitespace-nowrap">{c.rol}</Badge>
                                                    </td>

                                                    <td className="px-5 py-2.5">
                                                        <Badge color={c.estado_color} className="whitespace-nowrap">{c.estado_etiqueta}</Badge>
                                                    </td>

                                                    {/* Nunca entró = la clave temporal sigue sin estrenar. */}
                                                    <td className="px-5 py-2.5 text-xs text-muted-foreground">
                                                        {c.ultimo_acceso ? (
                                                            <>
                                                                {fechaHora(c.ultimo_acceso)}
                                                                <span className="block">{hace(c.ultimo_acceso)}</span>
                                                            </>
                                                        ) : (
                                                            'Nunca entró'
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-2.5 text-xs text-muted-foreground">{fechaHora(c.creada)}</td>

                                                    <td className="px-5 py-2.5 text-right">
                                                        {c.beneficiario_id !== null && puede('beneficiarios.ver') && (
                                                            <a
                                                                href={`${route('beneficiarios.show', c.beneficiario_id)}?pestana=datos`}
                                                                className={cn(buttonVariants({ variant: 'ver', size: 'sm' }))}
                                                                title="Abrir su ficha: ahí se resetea o desactiva el acceso"
                                                            >
                                                                <Eye className="size-4" />
                                                            </a>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <Paginacion paginado={usuarios} />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </LayoutPanel>
    );
}

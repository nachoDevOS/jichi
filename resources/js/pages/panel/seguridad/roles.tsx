import { Head, Link, router } from '@inertiajs/react';
import { Lock, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarAccion } from '@/components/ui/confirmar-accion';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { cn } from '@/lib/utils';
import type { RolFila } from '@/types/seguridad';

/**
 *  Roles — la lista; los permisos se marcan al crear o editar
 */
export default function Roles({ roles, totalPermisos }: { roles: RolFila[]; totalPermisos: number }) {
    const { puede } = usePermisos();
    const [eliminando, setEliminando] = useState<RolFila | null>(null);
    const [procesando, setProcesando] = useState(false);

    return (
        <LayoutPanel
            titulo="Roles"
            descripcion="Los perfiles del personal y lo que puede hacer cada uno."
            acciones={
                puede('roles.crear') && (
                    <Link href={route('roles.create')} className={cn(buttonVariants())}>
                        <Plus className="size-4" />
                        Nuevo rol
                    </Link>
                )
            }
        >
            <Head title="Roles" />

            <Card className="min-w-0">
                <CardHeader>
                    <CardTitle>Registrados</CardTitle>
                </CardHeader>

                <CardContent className="p-0">
                    {roles.length === 0 ? (
                        <EstadoVacio
                            icono={ShieldCheck}
                            titulo="Sin roles"
                            descripcion="La base no tiene roles cargados: falta correr el RolPermisoSeeder."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-2.5 font-medium">Rol</th>
                                        <th className="px-5 py-2.5 font-medium">Descripción</th>
                                        <th className="px-5 py-2.5 text-right font-medium">Permisos</th>
                                        <th className="px-5 py-2.5 text-right font-medium">Usuarios</th>
                                        <th className="px-5 py-2.5" />
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-border">
                                    {roles.map((r) => (
                                        <tr key={r.id} className="hover:bg-secondary/50">
                                            <td className="px-5 py-2.5">
                                                <span className="font-medium">{r.etiqueta}</span>
                                                {r.del_sistema && (
                                                    <Badge color="amber" className="ml-2">
                                                        <Lock className="mr-1 size-3" />
                                                        Del sistema
                                                    </Badge>
                                                )}
                                            </td>

                                            <td className="px-5 py-2.5 text-muted-foreground">{r.descripcion ?? '—'}</td>

                                            <td className="px-5 py-2.5 text-right tabular-nums">
                                                {r.permisos} <span className="text-muted-foreground">de {totalPermisos}</span>
                                            </td>

                                            <td className="px-5 py-2.5 text-right tabular-nums">{r.usuarios || '—'}</td>

                                            <td className="px-5 py-2.5">
                                                <div className="flex justify-end gap-2">
                                                    {puede('roles.editar') && r.puede_editarse && (
                                                        <Link
                                                            href={route('roles.edit', r.id)}
                                                            className={cn(buttonVariants({ variant: 'editar', size: 'sm' }))}
                                                            title="Editar nombre y permisos"
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    )}
                                                    {puede('roles.eliminar') && r.puede_eliminarse && (
                                                        <Button
                                                            variant="eliminar"
                                                            size="sm"
                                                            title="Eliminar"
                                                            onClick={() => setEliminando(r)}
                                                        >
                                                            <Trash2 className="size-4" />
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
                </CardContent>
            </Card>

            <ConfirmarAccion
                abierto={eliminando !== null}
                titulo={`Eliminar el rol «${eliminando?.etiqueta ?? ''}»`}
                descripcion="El rol desaparece de la lista. Lo que tenía queda registrado en la auditoría."
                textoConfirmar="Eliminar rol"
                procesando={procesando}
                onCancelar={() => setEliminando(null)}
                onConfirmar={() =>
                    eliminando &&
                    router.delete(route('roles.destroy', eliminando.id), {
                        preserveScroll: true,
                        onStart: () => setProcesando(true),
                        onFinish: () => {
                            setProcesando(false);
                            setEliminando(null);
                        },
                    })
                }
            />
        </LayoutPanel>
    );
}

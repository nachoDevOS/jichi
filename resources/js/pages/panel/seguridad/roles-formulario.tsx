import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { cn } from '@/lib/utils';
import type { ModuloPermisos, PlantillaRol, RolFormulario } from '@/types/seguridad';

/**
 *  Alta y edición de un rol: nombre y permisos, marcados módulo por módulo
 */
export default function RolesFormulario({
    rol,
    modulos,
    requiere,
    plantillas,
}: {
    /** null = alta. */
    rol: RolFormulario | null;
    modulos: ModuloPermisos[];
    /** Permiso → lo que arrastra (`RolSistema::requiere()`). El servidor lo vuelve a completar. */
    requiere: Record<string, string[]>;
    plantillas: PlantillaRol[];
}) {
    const esAlta = rol === null;

    const form = useForm({
        nombre: rol?.nombre ?? '',
        permisos: rol?.permisos ?? ([] as string[]),
    });

    const marcados = new Set(form.data.permisos);

    /** Marcar arrastra lo que el permiso necesita; desmarcar se lleva lo que dependía de él. */
    function aplicar(agregar: string[], quitar: string[]) {
        const conjunto = new Set(form.data.permisos);
        agregar.forEach((p) => [p, ...(requiere[p] ?? [])].forEach((x) => conjunto.add(x)));
        quitar.forEach((q) => {
            conjunto.delete(q);
            Object.entries(requiere).forEach(([p, deps]) => deps.includes(q) && conjunto.delete(p));
        });
        form.setData('permisos', [...conjunto]);
    }

    function alternar(permiso: string) {
        if (marcados.has(permiso)) aplicar([], [permiso]);
        else aplicar([permiso], []);
    }

    function alternarModulo(m: ModuloPermisos, marcar: boolean) {
        const delModulo = m.permisos.map((p) => p.nombre);
        if (marcar) aplicar(delModulo, []);
        else aplicar([], delModulo);
    }

    function enviar(e: FormEvent) {
        e.preventDefault();

        if (esAlta) {
            form.post(route('roles.store'));
        } else {
            form.put(route('roles.update', rol.id));
        }
    }

    // Las secciones del menú, en orden: cada módulo trae la suya.
    const secciones = modulos.reduce<[string, ModuloPermisos[]][]>((acc, m) => {
        const ultima = acc[acc.length - 1];
        if (ultima && ultima[0] === m.grupo) ultima[1].push(m);
        else acc.push([m.grupo, [m]]);
        return acc;
    }, []);

    const titulo = esAlta ? 'Nuevo rol' : 'Editar rol';
    const total = modulos.reduce((n, m) => n + m.permisos.length, 0);

    return (
        <LayoutPanel
            titulo={titulo}
            descripcion={esAlta ? 'Un perfil del personal y lo que puede hacer.' : rol.nombre}
            acciones={
                <Link href={route('roles.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                    <ArrowLeft className="size-4" />
                    Volver a roles
                </Link>
            }
        >
            <Head title={titulo} />

            <form onSubmit={enviar} className="space-y-6">
                <Card>
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2">
                        <Campo etiqueta="Nombre" htmlFor="nombre" error={form.errors.nombre} obligatorio>
                            <Input
                                id="nombre"
                                value={form.data.nombre}
                                onChange={(e) => form.setData('nombre', e.target.value)}
                                aria-invalid={Boolean(form.errors.nombre)}
                                placeholder="Operador de ventanilla"
                                maxLength={60}
                            />
                        </Campo>

                        {plantillas.length > 0 && (
                            <Campo
                                etiqueta="Copiar permisos de"
                                htmlFor="plantilla"
                                ayuda="Reemplaza lo marcado por los permisos de ese rol; después se ajusta a mano."
                            >
                                <Select
                                    id="plantilla"
                                    value=""
                                    onChange={(e) => {
                                        const p = plantillas[Number(e.target.value)];
                                        if (p) form.setData('permisos', [...p.permisos]);
                                    }}
                                >
                                    <option value="">Elegir un rol…</option>
                                    {plantillas.map((p, i) => (
                                        <option key={p.nombre} value={i}>
                                            {p.nombre}
                                        </option>
                                    ))}
                                </Select>
                            </Campo>
                        )}
                    </CardContent>
                </Card>

                <Card className="min-w-0">
                    <CardHeader className="flex flex-row items-center justify-between gap-2">
                        <CardTitle>Permisos</CardTitle>
                        <span className="text-sm text-muted-foreground tabular-nums">
                            {marcados.size} de {total}
                        </span>
                    </CardHeader>

                    <CardContent className="space-y-6">
                        <p className="text-xs text-muted-foreground">
                            Marcar una acción marca también «Ver» de su módulo, porque al terminar vuelve a la ficha. Reponer
                            un carnet perdido va con «Registrar».
                        </p>
                        {form.errors.permisos && <p className="text-sm text-destructive">{form.errors.permisos}</p>}

                        {secciones.map(([grupo, delGrupo]) => (
                            <section key={grupo} className="space-y-2">
                                <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{grupo}</h3>
                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    {delGrupo.map((m) => {
                                        const cuantos = m.permisos.filter((p) => marcados.has(p.nombre)).length;
                                        const todos = cuantos === m.permisos.length;

                                        return (
                                            <fieldset key={m.clave} className="min-w-0 rounded-lg border border-border p-4">
                                                <legend className="sr-only">{m.etiqueta}</legend>

                                                <label className="flex cursor-pointer items-center gap-2 border-b border-border pb-2 text-sm font-semibold">
                                                    <input
                                                        type="checkbox"
                                                        className="size-4 accent-primary"
                                                        checked={todos}
                                                        ref={(el) => {
                                                            // Algunos marcados: la casilla del módulo queda a medias.
                                                            if (el) el.indeterminate = cuantos > 0 && !todos;
                                                        }}
                                                        onChange={() => alternarModulo(m, !todos)}
                                                    />
                                                    {m.etiqueta}
                                                    <span className="ml-auto text-xs font-normal text-muted-foreground tabular-nums">
                                                        {cuantos}/{m.permisos.length}
                                                    </span>
                                                </label>

                                                <div className="space-y-1.5 pt-2">
                                                    {m.permisos.map((p) => (
                                                        // El código va en el globito: a lo ancho apretaba el nombre de la acción.
                                                        <label
                                                            key={p.nombre}
                                                            title={p.nombre}
                                                            className="flex cursor-pointer items-center gap-2 text-sm"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                className="size-4 accent-primary"
                                                                checked={marcados.has(p.nombre)}
                                                                onChange={() => alternar(p.nombre)}
                                                            />
                                                            {p.accion}
                                                        </label>
                                                    ))}
                                                </div>
                                            </fieldset>
                                        );
                                    })}
                                </div>
                            </section>
                        ))}
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        {esAlta ? 'Crear rol' : 'Guardar cambios'}
                    </Button>

                    <Link href={route('roles.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                        Cancelar
                    </Link>
                </div>
            </form>
        </LayoutPanel>
    );
}

import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { cn } from '@/lib/utils';
import type { OpcionEnum } from '@/types';
import type { AsociacionFormulario } from '@/types/catalogos';

/**
 *  Alta y edición de una asociación: la misma pantalla para los dos casos
 */
export default function AsociacionesFormulario({
    asociacion,
    estados,
    camposFicha,
}: {
    /** null = alta. */
    asociacion: AsociacionFormulario | null;
    estados: OpcionEnum[];
    /** Clave → rótulo de la ficha. Sale de `Asociacion::CAMPOS`. */
    camposFicha: Record<string, string>;
}) {
    const esAlta = asociacion === null;

    /*
     * La ficha arranca con todas las claves, en cadena vacía: un `undefined`
     * en `useForm` hace que el input pase de no-controlado a controlado al
     * escribir, y React avisa en consola y pierde el primer carácter.
     */
    const fichaInicial = Object.fromEntries(Object.keys(camposFicha).map((clave) => [clave, asociacion?.datos?.[clave] ?? '']));

    const form = useForm({
        nombre: asociacion?.nombre ?? '',
        sigla: asociacion?.sigla ?? '',
        datos: fichaInicial,
        estado: asociacion?.estado ?? 'activo',
    });

    function enviar(e: FormEvent) {
        e.preventDefault();

        if (esAlta) {
            form.post(route('asociaciones.store'));
        } else {
            form.put(route('asociaciones.update', asociacion.id));
        }
    }

    const titulo = esAlta ? 'Nueva asociación' : 'Editar asociación';

    return (
        <LayoutPanel
            titulo={titulo}
            descripcion={esAlta ? 'Un gremio que certifica al beneficiario.' : asociacion.nombre}
            acciones={
                <Link href={route('asociaciones.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                    <ArrowLeft className="size-4" />
                    Volver a asociaciones
                </Link>
            }
        >
            <Head title={titulo} />

            <Card>
                <CardContent className="pt-6">
                    <form onSubmit={enviar} className="space-y-4">
                        {/* A lo ancho los tres campos entran en una fila. */}
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Campo etiqueta="Nombre" htmlFor="nombre" error={form.errors.nombre} obligatorio>
                                <Input
                                    id="nombre"
                                    value={form.data.nombre}
                                    onChange={(e) => form.setData('nombre', e.target.value)}
                                    aria-invalid={Boolean(form.errors.nombre)}
                                    placeholder="Asociación de Pescadores de Trinidad"
                                />
                            </Campo>

                            <Campo
                                etiqueta="Sigla"
                                htmlFor="sigla"
                                error={form.errors.sigla}
                                ayuda="Es lo que entra en el renglón angosto del carnet. El servidor la guarda en mayúsculas."
                            >
                                <Input
                                    id="sigla"
                                    value={form.data.sigla}
                                    onChange={(e) => form.setData('sigla', e.target.value.toUpperCase())}
                                    aria-invalid={Boolean(form.errors.sigla)}
                                    placeholder="ASOPESTRI"
                                    className="font-mono"
                                />
                            </Campo>

                            <Campo
                                etiqueta="Estado"
                                htmlFor="estado"
                                error={form.errors.estado}
                                ayuda="Una asociación inactiva no aparece al emitir, pero los documentos ya emitidos la siguen mostrando."
                                obligatorio
                            >
                                <Select
                                    id="estado"
                                    value={form.data.estado}
                                    onChange={(e) => form.setData('estado', e.target.value as typeof form.data.estado)}
                                    aria-invalid={Boolean(form.errors.estado)}
                                >
                                    {estados.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))}
                                </Select>
                            </Campo>
                        </div>

                        {/*
                        LA FICHA DEL GREMIO. Los campos se dibujan a partir de
                        los rótulos que manda el servidor —`Asociacion::CAMPOS`—
                        así que sumar un dato es una línea en el modelo: acá no
                        se toca nada, y tampoco hace falta migrar.
                    */}
                        <div className="space-y-4 border-t border-border pt-4">
                            <div>
                                <p className="text-sm font-medium">Ficha de la asociación</p>
                                <p className="text-xs text-muted-foreground">
                                    Todo opcional. Son datos de respaldo y de contacto: no deciden nada en el sistema, se guardan y se muestran.
                                </p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {Object.entries(camposFicha).map(([clave, rotulo]) => (
                                    <Campo
                                        key={clave}
                                        etiqueta={rotulo}
                                        htmlFor={`ficha-${clave}`}
                                        error={form.errors[`datos.${clave}` as keyof typeof form.errors]}
                                    >
                                        <Input
                                            id={`ficha-${clave}`}
                                            // El tipo sale de la clave y no de una
                                            // lista aparte: un campo nuevo con
                                            // «fecha» o «correo» en el nombre ya
                                            // entra con el control correcto.
                                            type={clave === 'fundacion' ? 'date' : clave === 'correo' ? 'email' : 'text'}
                                            value={form.data.datos[clave] ?? ''}
                                            onChange={(e) =>
                                                form.setData('datos', {
                                                    ...form.data.datos,
                                                    [clave]: e.target.value,
                                                })
                                            }
                                            aria-invalid={Boolean(form.errors[`datos.${clave}` as keyof typeof form.errors])}
                                        />
                                    </Campo>
                                ))}
                            </div>
                        </div>

                        <div className="flex gap-2 pt-1">
                            <Button type="submit" disabled={form.processing}>
                                {esAlta ? 'Registrar' : 'Guardar cambios'}
                            </Button>

                            <Link href={route('asociaciones.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                                Cancelar
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </LayoutPanel>
    );
}
